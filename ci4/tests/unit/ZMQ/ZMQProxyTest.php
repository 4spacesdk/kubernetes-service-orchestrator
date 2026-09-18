<?php namespace App\Tests\Unit\ZMQ;

use App\Libraries\ZMQ\ZMQProxy;
use App\SilentZmq;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The push socket every entity's status change goes out on.
 *
 * This is the class `SilentZmq::install()` neuters, and for a good reason: the zmq server
 * really is listening on 9101 inside the development container, so a message sent from a
 * test is picked up by the client and called back into the application over HTTP - outside
 * the test, under the development environment, against the production database. That is
 * SEC-18, and nothing below undoes it.
 *
 * **Nothing here sends to 9101.** The send path is exercised over an `inproc://` pair
 * created in this process: a PULL socket bound to a name only this test knows and a PUSH
 * socket connected to it. The message never leaves the process, and it can be read back,
 * which is what makes the payload assertable at all.
 *
 * `connect()` and `getInstance()` do reach the socket layer, because there is nothing else
 * in them - the host and the port are literals. A ZeroMQ PUSH socket that has connected and
 * not sent puts nothing on the wire: `connect()` only registers the endpoint, and the queue
 * it would flush on shutdown is empty. The singleton is put back the way the bootstrap left
 * it before the test returns, so the rest of the run keeps the proxy that never connected.
 *
 * The `catch` inside `connect()` has no test and cannot have one. ZeroMQ resolves and dials
 * a tcp endpoint in a background thread, so `connect()` succeeds whatever is or is not on
 * the other end, and the host and the port are literals - there is no input that makes it
 * throw. The swallow-and-log on the *send* side is reachable and is covered above.
 */
class ZMQProxyTest extends CIUnitTestCase {

    /**
     * An endpoint inside this process. `inproc` needs the bind before the connect, and both
     * sockets have to come from the same context.
     *
     * A new name for every test. `ZMQContext` is persistent by default, so the tests share
     * one, and ZeroMQ releases a bound endpoint in the background after the socket is gone.
     * With a fixed name the next test could bind before that had happened - it did in
     * Cloud Build, as "Address in use", and never locally.
     */
    private string $endpoint;

    protected function setUp(): void {
        parent::setUp();

        $this->endpoint = 'inproc://kso-zmq-proxy-test-' . bin2hex(random_bytes(8));
    }

    // <editor-fold desc="Sending">

    public function testTheEventAndItsDataGoOutAsOneJsonMessage(): void {
        $context = new \ZMQContext();
        $received = $this->listenOn($context);

        $this->proxyWithSocket($this->senderOn($context))
            ->send('events.workspace.42.changed.status', ['id' => 42, 'status' => 'Active']);

        $this->assertSame(
            [
                'event' => 'events.workspace.42.changed.status',
                'data' => ['id' => 42, 'status' => 'Active'],
            ],
            json_decode($this->receive($received), true)
        );
    }

    /**
     * An empty payload still has to arrive as a json object rather than as `[]`, because
     * the client reads `data` as a map. php-k8s has the same problem the other way round -
     * see the note about `toJsonPayload()` - so it is worth pinning which way this one goes.
     */
    public function testAnEventWithNoDataStillCarriesADataField(): void {
        $context = new \ZMQContext();
        $received = $this->listenOn($context);

        $this->proxyWithSocket($this->senderOn($context))->send('events.workspace.created', []);

        $message = json_decode($this->receive($received), true);
        $this->assertSame('events.workspace.created', $message['event']);
        $this->assertSame([], $message['data']);
    }

    /**
     * The state the bootstrap puts the whole suite in: an instance that never connected. A
     * push event then costs nothing and reaches nobody, which is the only reason the rest
     * of the suite can call the entity methods that emit them.
     */
    public function testAProxyThatNeverConnectedSendsNothingAndDoesNotComplain(): void {
        $proxy = (new \ReflectionClass(ZMQProxy::class))->newInstanceWithoutConstructor();

        $this->assertNull($proxy->send('events.workspace.created', ['id' => 1]));
    }

    /**
     * A socket that refuses the send is swallowed and logged, not thrown. A UI notification
     * is not worth failing a deploy over - and a PULL socket is the cheapest way to get
     * ZeroMQ to refuse one, since the operation genuinely does not exist for that type.
     */
    public function testASocketThatRefusesTheSendIsSwallowed(): void {
        $refuses = (new \ZMQContext())->getSocket(\ZMQ::SOCKET_PULL);

        $this->assertNull($this->proxyWithSocket($refuses)->send('events.workspace.created', []));
    }

    // </editor-fold>

    // <editor-fold desc="Connecting">

    /**
     * The linger is the whole reason `connect()` is not a one-liner. Without it ZeroMQ
     * waits forever for a queued message on shutdown, and the migration job - which runs
     * `php spark migrate` rather than the entrypoint, so nothing is listening in that pod -
     * hung after printing "Migrations complete." until the six hour deadline killed it.
     *
     * Connecting is safe: a PUSH socket that has not sent has nothing to deliver.
     *
     * The endpoint and the socket type are asserted here as well, because there is nowhere
     * else they can be: `connect()` neither returns nor throws, and a PUSH socket pointed
     * at a port nobody listens on behaves exactly like one pointed at the right port - it
     * queues. Both are read back off the socket rather than sent to, so still nothing goes
     * out. The type matters as much as the port: a PUB socket would connect and queue just
     * the same, and silently drop every event, because PUB discards what no subscriber has
     * asked for.
     */
    public function testConnectingSetsABoundedShutdownWait(): void {
        $proxy = (new \ReflectionClass(ZMQProxy::class))->newInstanceWithoutConstructor();

        $this->invokeConnect($proxy);

        $socket = $this->socketOf($proxy);
        $this->assertInstanceOf(\ZMQSocket::class, $socket);
        $this->assertSame(500, $socket->getSockOpt(\ZMQ::SOCKOPT_LINGER));
        $this->assertSame(\ZMQ::SOCKET_PUSH, $socket->getSocketType(), 'PUB would drop every event');
        $this->assertSame(
            ['tcp://localhost:9101'],
            $socket->getEndpoints()['connect'],
            'the port the zmq server binds'
        );
        $this->assertSame([], $socket->getEndpoints()['bind'], 'the proxy connects, it does not listen');
    }

    /**
     * One socket for the process, built on first use. Every entity that changes status asks
     * for it, so a fresh connection per event would be a connection per row written.
     */
    public function testTheInstanceIsBuiltOnceAndHandedOutAgain(): void {
        $instance = new \ReflectionProperty(ZMQProxy::class, 'instance');
        $silent = $instance->getValue();

        try {
            $instance->setValue(null, null);

            $first = ZMQProxy::getInstance();

            $this->assertInstanceOf(ZMQProxy::class, $first);
            $this->assertSame($first, ZMQProxy::getInstance(), 'the second call connects nothing');
            $this->assertInstanceOf(\ZMQSocket::class, $this->socketOf($first), 'and it was connected');
        } finally {
            // Back to the proxy that never connected, whatever happened above. Everything
            // after this test emits into nothing again.
            $instance->setValue(null, $silent);
            SilentZmq::install();
        }

        $this->assertNull($this->socketOf(ZMQProxy::getInstance()), 'the suite has its silent one back');
    }

    // </editor-fold>

    // <editor-fold desc="Helpers">

    private function proxyWithSocket(\ZMQSocket $socket): ZMQProxy {
        $proxy = (new \ReflectionClass(ZMQProxy::class))->newInstanceWithoutConstructor();

        $property = new \ReflectionProperty(ZMQProxy::class, 'socket');
        $property->setValue($proxy, $socket);

        return $proxy;
    }

    private function socketOf(ZMQProxy $proxy): ?\ZMQSocket {
        $property = new \ReflectionProperty(ZMQProxy::class, 'socket');

        return $property->isInitialized($proxy) ? $property->getValue($proxy) : null;
    }

    private function invokeConnect(ZMQProxy $proxy): void {
        $method = new \ReflectionMethod(ZMQProxy::class, 'connect');
        $method->invoke($proxy);
    }

    private function listenOn(\ZMQContext $context): \ZMQSocket {
        $socket = $context->getSocket(\ZMQ::SOCKET_PULL);
        $socket->bind($this->endpoint);

        return $socket;
    }

    private function senderOn(\ZMQContext $context): \ZMQSocket {
        $socket = $context->getSocket(\ZMQ::SOCKET_PUSH);
        $socket->connect($this->endpoint);

        return $socket;
    }

    /**
     * Never a blocking `recv()`: a test that hangs is worse than one that fails, and
     * `inproc` delivery is a memory copy - a few polls is already generous.
     */
    private function receive(\ZMQSocket $socket): string {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $message = $socket->recv(\ZMQ::MODE_DONTWAIT);
            if ($message !== false) {
                return $message;
            }

            usleep(10000);
        }

        $this->fail('nothing arrived on the socket within a second');
    }

    // </editor-fold>

}
