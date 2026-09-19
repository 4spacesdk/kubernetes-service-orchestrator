<?php namespace App;

/**
 * Lets a deprecation fail a test when it is ours, and keeps count when it is not.
 *
 * `CODEIGNITER_SCREAM_DEPRECATIONS` is set in `phpunit.xml`, so CodeIgniter's error handler
 * turns every deprecation into an `ErrorException`. That is the point: before it was set,
 * deprecations were logged below the logger's threshold and a clean `OK` said nothing about
 * them.
 *
 * But "every" includes the vendor tree, and a deprecation in `ci4restextension` fires on
 * nearly every request - with the flag alone, 986 of 1093 tests errored on code we cannot
 * change. So this sits on top of CodeIgniter's handler and splits them by where PHP raised
 * them:
 *
 * - **Raised in `app/` or `tests/`** - passed on to CodeIgniter, which throws. The test fails,
 *   and the fix is ours to make.
 * - **Raised under `vendor/`** - recorded and not thrown. They are fixed by upgrading the
 *   package, not by editing it, and failing the suite over them would bury the ones that are
 *   ours. They are not silenced either: that would be the original problem again. They are
 *   written to `build/logs/vendor-deprecations.log`, one line per distinct site, and the
 *   count is printed when the run ends.
 *
 * Installed once, from `tests/bootstrap.php`, straight after CodeIgniter has installed its
 * own handler. Once rather than per test, so that it is also in force while data providers
 * run, and so PHPUnit does not see a handler change inside a test and call it risky.
 */
final class VendorDeprecations {

    private const Log = __DIR__ . '/../build/logs/vendor-deprecations.log';

    /** @var array<string, int> "file:line message" => how many times */
    private static array $seen = [];

    public static function install(): void {
        $vendor = realpath(__DIR__ . '/../vendor') . DIRECTORY_SEPARATOR;

        $previous = null;
        $previous = set_error_handler(
            static function (int $severity, string $message, ?string $file = null, ?int $line = null) use (&$previous, $vendor): bool {
                $isDeprecation = ($severity & (E_DEPRECATED | E_USER_DEPRECATED)) !== 0;

                if ($isDeprecation && $file !== null && str_starts_with($file, $vendor)) {
                    $site = substr($file, strlen($vendor)) . ":{$line}  {$message}";
                    self::$seen[$site] = (self::$seen[$site] ?? 0) + 1;

                    return true;
                }

                return $previous !== null ? (bool) $previous($severity, $message, $file, $line) : false;
            }
        );

        register_shutdown_function(self::report(...));
    }

    private static function report(): void {
        // Not from inside a test that runs in its own process: PHPUnit reads that process's
        // stderr as the test's result, and a summary there turns a passing test into an
        // error. The child is recognised by the function PHPUnit's isolation template
        // declares, which the main process never has.
        if (self::$seen === [] || function_exists('__phpunit_run_isolated_test')) {
            return;
        }

        ksort(self::$seen);
        @mkdir(dirname(self::Log), 0777, true);
        file_put_contents(self::Log, implode("\n", array_map(
            static fn(string $site, int $times) => "{$times}x  {$site}",
            array_keys(self::$seen),
            self::$seen,
        )) . "\n");

        fwrite(STDERR, sprintf(
            "\n%d distinct deprecation(s) raised inside vendor/, %d time(s) in all - not failed, "
            . "because they are fixed by upgrading, not editing. Listed in build/logs/vendor-deprecations.log.\n",
            count(self::$seen),
            array_sum(self::$seen),
        ));
    }

}
