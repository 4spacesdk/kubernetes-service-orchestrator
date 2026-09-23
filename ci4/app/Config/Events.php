<?php

namespace Config;

use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\FrameworkException;

/*
 * --------------------------------------------------------------------
 * Application Events
 * --------------------------------------------------------------------
 * Events allow you to tap into the execution of the program without
 * modifying or extending core files. This file provides a central
 * location to define your events, though they can always be added
 * at run-time, also, if needed.
 *
 * You create code that can execute by subscribing to events with
 * the 'on()' method. This accepts any form of callable, including
 * Closures, that will be executed when the event is triggered.
 *
 * Example:
 *      Events::on('create', [$myInstance, 'myMethod']);
 */

Events::on('pre_system', static function (): void {
    // Not measured: stock CodeIgniter, and both halves are unreachable from a test by
    // their own conditions - the first is skipped when ENVIRONMENT is `testing`, and the
    // second wants CI_DEBUG with a web request behind it.
    // @codeCoverageIgnoreStart
    if (ENVIRONMENT !== 'testing') {
        $value = ini_get('zlib.output_compression');

        if (filter_var($value, FILTER_VALIDATE_BOOLEAN) || (int) $value > 0) {
            throw FrameworkException::forEnabledZlibOutputCompression();
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_start(static fn ($buffer) => $buffer);
    }

    // No Debug Toolbar: it was drawn over the bottom of every page kso renders itself - the
    // sign-in pages among them - in development, and nothing collected for it was read.
    // @codeCoverageIgnoreEnd
});


Events::on('pre_system', [\RestExtension\Hooks::class, 'preSystem']);
Events::on('pre_system', [\OrmExtension\Hooks\PreController::class, 'execute']);
Events::on('pre_system', [\AuthExtension\Hooks\PreController::class, 'execute']);
// After RestExtension has installed its exception handler: see DebugLog::Guard().
Events::on('pre_system', static function (): void {
    set_exception_handler(\App\Helpers\DebugLog::Guard(set_exception_handler(null)));
});
Events::on('pre_command', [\RestExtension\Hooks::class, 'preSystem']);
Events::on('pre_command', [\OrmExtension\Hooks\PreController::class, 'execute']);
Events::on('pre_command', [\AuthExtension\Hooks\PreController::class, 'execute']);

/*
 * Drop the cached model definitions after a spark command.
 *
 * A migration changes the schema, and ModelDefinitionCache would otherwise keep telling
 * the next request what the tables looked like before it ran.
 *
 * This used to sit at the bottom of `spark`. Here the file stays as CodeIgniter ships it,
 * and there is nothing to merge on the next framework upgrade.
 *
 * `post_command` fires after every command, which is what the old code did. There is also
 * a narrower `migrate` event if this ever needs to be limited to migrations alone - it
 * fires once per migration run rather than once per command.
 */
Events::on('post_command', static function (): void {
    \OrmExtension\DataMapper\ModelDefinitionCache::getInstance()->clearCache();
});
