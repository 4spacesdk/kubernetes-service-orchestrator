<?php namespace App;

/**
 * Take back the exception handler the extensions install on every `pre_system` and
 * `pre_command`.
 *
 * `RestExtension\Hooks::preSystem()` calls `set_exception_handler()` and never restores it.
 * In production that happens once per process. A test triggers the event in every
 * `setUp()`, so the handler stack grew by one per test, and PHPUnit 10 and later mark
 * every test that leaves a handler behind as risky. The handler still does its job for
 * the length of the test; it is only removed again afterwards.
 */
trait RestoresTheExceptionHandler {

    /** @var callable|null */
    private $exceptionHandlerBefore = null;

    protected function rememberTheExceptionHandler(): void {
        $this->exceptionHandlerBefore = self::currentExceptionHandler();
    }

    protected function restoreTheExceptionHandler(): void {
        // Bounded, so a handler that cannot be compared can never loop forever.
        for ($i = 0; $i < 100 && self::currentExceptionHandler() !== $this->exceptionHandlerBefore; $i++) {
            restore_exception_handler();
        }
    }

    /**
     * `get_exception_handler()` only exists from PHP 8.5; this works on every version.
     */
    private static function currentExceptionHandler(): ?callable {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        return $handler;
    }

}
