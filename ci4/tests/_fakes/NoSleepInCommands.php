<?php namespace App\Commands;

/**
 * `sleep()` inside the commands, made instant for the test suite.
 *
 * PHP resolves an unqualified call in a namespaced file against that namespace first and
 * only then against the global one. Defining `App\Commands\sleep()` therefore replaces the
 * real one for every command, and nowhere else.
 *
 * `PullContainerRegistries::run()` pulls five times two seconds apart. Those ten seconds
 * are the only thing standing between a test and the whole of that method - the pull
 * itself, the failure path around it, the bookkeeping afterwards - and waiting them out
 * would cost more than the rest of the suite put together.
 *
 * **It applies to the whole run**, which is action at a distance and worth knowing about.
 * A command that needed to actually wait would not get to here.
 */
function sleep(int $seconds): int {
    return 0;
}
