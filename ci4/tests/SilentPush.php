<?php namespace App;

use App\Libraries\Push\Publisher;

/**
 * Keeps the test suite's events to itself.
 *
 * A great many entity methods raise an event, and `Publisher::getInstance()` would send each
 * one to Centrifugo and put the ones kso acts on into the job queue. In a development
 * container a worker may be reading that queue - the old push stack's subscriber was, and it
 * called back into the application under the development environment, against the
 * **production** database, with ids that only existed in `deploy_tests`. Empty deployments
 * appeared in `deploy` while the suite was green.
 *
 * So the suite gets a publisher with nowhere to send to and no queue. A test about the
 * publisher builds its own.
 */
class SilentPush {

    public static function install(): void {
        $instance = new \ReflectionProperty(Publisher::class, 'instance');
        $instance->setValue(null, new Publisher(null, false));
    }

}
