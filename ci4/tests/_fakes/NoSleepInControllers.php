<?php namespace App\Controllers;

/**
 * `sleep()` inside the controllers, made instant for the test suite.
 *
 * The same trick as `NoSleepInCommands.php`: PHP resolves an unqualified call against the
 * file's own namespace before the global one, so an `App\Controllers\sleep()` replaces the
 * real one for every controller - and nowhere else.
 *
 * `ZMQ::migrationJobChangedStatus()` sleeps five seconds before asking the deployment to
 * check its own status. Those five seconds are the only thing standing between a test and
 * that method, and on their own they would cost more than the rest of the database suite.
 *
 * **It applies to the whole run.** A controller that needed to actually wait would not get
 * to here.
 */
function sleep(int $seconds): int {
    return 0;
}
