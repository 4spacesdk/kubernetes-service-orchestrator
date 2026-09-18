<?php namespace App;

use App\Libraries\ZMQ\ZMQProxy;

/**
 * Keeps the test suite's push events off the real socket.
 *
 * `ZMQProxy::getInstance()` connects to `tcp://localhost:9101` the first time it is asked,
 * and in a development container **something is listening**: the zmq server runs alongside
 * Apache. So an event emitted by a test - and a great many entity methods emit one - is
 * published for real, the zmq client picks it up, and it calls back into the application
 * over HTTP.
 *
 * That callback is an ordinary request. It runs outside the test, under the development
 * environment, against the **production** database. It is handed ids that exist only in
 * `deploy_tests`, fails to find them, and saves anyway - which is how empty deployments
 * appeared in `deploy` while the suite was green.
 *
 * `send()` returns immediately when there is no socket, so the fix is simply to install an
 * instance that never connected. Nothing in production changes; the tests just stop having
 * a side channel out of the harness.
 */
class SilentZmq {

    public static function install(): void {
        $proxy = new \ReflectionClass(ZMQProxy::class);

        $instance = $proxy->getProperty('instance');
        $instance->setValue(null, $proxy->newInstanceWithoutConstructor());
    }

}
