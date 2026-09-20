<?php namespace App\Tests\Unit\Kubernetes;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * `catch (\Exception)` does not catch a `\TypeError`.
 *
 * PHP's `Error` and `Exception` are siblings under `Throwable`, not one inside the other,
 * so the two most common ways for kso's own code to be wrong - a null where a string was
 * declared, a `match` with no arm for the value - go straight past a `catch` that names
 * `\Exception`. Every place that does so in order to turn a failure into an answer was
 * therefore a 500 waiting for the first mistake underneath it: a status panel that died
 * rather than reporting, a nightly job that stopped at the first domain, a connection test
 * that could not say no.
 *
 * `KubeHelper::PrintException()` takes a `\Throwable` for exactly this reason. This is the
 * sweep that keeps its callers in step with it - the pattern had several sites, and the
 * next one will be added by somebody who has not read this.
 */
class ThrowableIsCaughtTest extends CIUnitTestCase {

    /**
     * The premise, stated once so the sweep below is not the only thing saying it. A test
     * that is wrong about this would pass for the wrong reason for ever.
     */
    public function testAnErrorIsNotAnException(): void {
        $this->assertNotInstanceOf(\Exception::class, new \TypeError('x'));
        $this->assertInstanceOf(\Throwable::class, new \TypeError('x'));
    }

    /**
     * Nothing catches narrowly and then asks `PrintException()` to describe what it caught.
     *
     * The helper's whole signature says the caller may hand it anything throwable; a caller
     * that can only have caught an `\Exception` has already let the interesting half past.
     */
    public function testNothingHandsPrintExceptionAFailureItCaughtTooNarrowly(): void {
        $this->assertSame([], $this->narrowCatchesFeedingPrintException());
    }

    // <editor-fold desc="Helpers">

    /**
     * @return string[] `file:line` for each offending catch, empty when there are none
     */
    private function narrowCatchesFeedingPrintException(): array {
        $offenders = [];

        foreach ($this->applicationSources() as $file) {
            $lines = explode("\n", (string) file_get_contents($file));

            foreach ($lines as $number => $line) {
                if (!preg_match('/catch\s*\(\s*\\\\Exception\s+\$(\w+)\s*\)/', $line, $matches)) {
                    continue;
                }

                // The handler, not the whole file: a `PrintException()` elsewhere in the
                // same method is a different catch's business.
                $handler = implode("\n", array_slice($lines, $number, 6));
                if (str_contains($handler, "PrintException(\${$matches[1]})")) {
                    $offenders[] = basename($file) . ':' . ($number + 1);
                }
            }
        }

        return $offenders;
    }

    /**
     * @return string[]
     */
    private function applicationSources(): array {
        $files = [];

        $directory = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(APPPATH));
        foreach ($directory as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    // </editor-fold>

}
