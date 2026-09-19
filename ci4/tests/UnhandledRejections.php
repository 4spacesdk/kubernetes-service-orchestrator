<?php namespace App;

/**
 * Promise rejections nobody handled.
 *
 * php-k8s does its websocket work - `exec`, log watching - on a ReactPHP event loop. When
 * the api server refuses, the failure arrives as a **rejected promise**, and if nothing is
 * listening react/promise writes a line with `error_log()` and moves on. The calling code
 * gets an empty result and no exception.
 *
 * Nothing in PHPUnit can see that. It is not an exception, not a PHP warning, and it goes
 * to stderr rather than to the output buffer PHPUnit watches - so a test could print a
 * five-hundred-error stack trace and still pass. One did: the shell on a pod that is not running.
 *
 * So the test bootstrap installs a handler here instead, and `DatabaseTestCase` fails any
 * test that leaves a rejection behind. A test that means to provoke one says so with
 * `expectUnhandledRejection()`.
 */
class UnhandledRejections {

    /** @var \Throwable[] */
    private static array $seen = [];

    public static function listen(): void {
        \React\Promise\set_rejection_handler(static function (\Throwable $reason): void {
            self::$seen[] = $reason;
        });
    }

    public static function record(\Throwable $reason): void {
        self::$seen[] = $reason;
    }

    /**
     * @return \Throwable[]
     */
    public static function takeAll(): array {
        $seen = self::$seen;
        self::$seen = [];

        return $seen;
    }

}
