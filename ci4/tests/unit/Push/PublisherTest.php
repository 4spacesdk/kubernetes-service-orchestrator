<?php namespace App\Tests\Unit\Push;

use App\Libraries\Push\Publisher;
use CodeIgniter\Test\CIUnitTestCase;
use DebugTool\Data;
use phpcent\Client;

/**
 * The browser half of the publisher: what goes to Centrifugo, and what happens when it is not
 * there. The queue half needs a database and is in `EventHandlersTest`.
 *
 * Nothing here opens a connection. The client is a subclass that writes down what it was
 * asked to publish, or throws the way phpcent does when curl gets no answer.
 */
class PublisherTest extends CIUnitTestCase {

    protected function setUp(): void {
        parent::setUp();

        $store = (new \ReflectionClass(Data::class))->getProperty('store');
        $store->setValue(null, ['status' => null]);
    }

    /**
     * The channel is the event name, and the payload the shape the components have always
     * read: `{event, data}`.
     */
    public function testTheEventGoesOutOnItsOwnChannelWithTheDataInside(): void {
        $client = $this->recordingClient();

        (new Publisher($client, false))->send('events.workspace.42.changed.status', ['next' => ['id' => 42]]);

        $this->assertSame(
            [['events.workspace.42.changed.status', ['event' => 'events.workspace.42.changed.status', 'data' => ['next' => ['id' => 42]]]]],
            $client->published
        );
    }

    /**
     * A push is a notification. Centrifugo being down is a line in the log, not a failed
     * deploy or a failed migration.
     */
    public function testACentrifugoThatDoesNotAnswerIsALineInTheLog(): void {
        $client = $this->recordingClient(throw: true);

        (new Publisher($client, false))->send('events.workspace.created', []);

        $this->assertStringContainsString('failed', $this->debugLog());
    }

    /**
     * Centrifugo answers a publish it understood and refused with a 200, so the error is in
     * the body - and would be lost if only the status code were read.
     */
    public function testARefusalInTheBodyIsNotTakenForSuccess(): void {
        $client = $this->recordingClient(response: ['error' => ['code' => 102, 'message' => 'unknown channel']]);

        (new Publisher($client, false))->send('events.workspace.created', []);

        $this->assertStringContainsString('unknown channel', $this->debugLog());
    }

    /**
     * No url in the environment, no client: the migration job and the test suite.
     */
    public function testWithoutAClientNothingIsSentAndNothingFails(): void {
        (new Publisher(null, false))->send('events.workspace.created', []);

        $this->assertSame('', $this->debugLog());
    }

    private function recordingClient(bool $throw = false, array $response = ['result' => []]): Client {
        return new class('http://centrifugo.invalid/api', '', '', $throw, $response) extends Client {

            public array $published = [];

            public function __construct($url, $apikey, $secret, private readonly bool $throw, private readonly array $response) {
                parent::__construct($url, $apikey, $secret);
            }

            public function publish($channel, $data, $skipHistory = false, $tags = array(), $idempotencyKey = '', $delta = false, $version = 0, $versionEpoch = '') {
                if ($this->throw) {
                    throw new \Exception("Response code: 0\ncURL error: Connection refused");
                }
                $this->published[] = [$channel, $data];
                return $this->response;
            }

        };
    }

    private function debugLog(): string {
        return implode("\n", array_map(
            fn ($line) => is_string($line) ? $line : json_encode($line),
            array_filter(Data::getDebugger(), fn ($line) => $line !== null)
        ));
    }

}
