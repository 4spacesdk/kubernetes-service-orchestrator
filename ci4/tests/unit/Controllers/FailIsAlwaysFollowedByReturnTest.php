<?php namespace App\Tests\Unit\Controllers;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * `fail()` no longer ends the process, so every caller has to return by itself.
 *
 * `BaseController::printResponse()` used to finish with `exit`. That ended the request
 * there and then, which meant a `fail()` in the middle of a method was also the end of the
 * method - by accident rather than by design. Several call sites relied on it: one in
 * `GithubApp::callback()` would otherwise have carried on and written null over the stored
 * GitHub App credentials.
 *
 * The `exit` is gone, because it skipped CodeIgniter's shutdown and with it the access log
 * entry for every error response. What replaces it is this rule: **a `fail()` or `error()`
 * call is immediately followed by `return`.** A reviewer cannot be expected to remember
 * that, so it is checked here instead.
 *
 * The check is deliberately literal - the next statement, textually. Anything cleverer
 * would need a parser, and the rule is only useful if it is obvious what satisfies it.
 */
class FailIsAlwaysFollowedByReturnTest extends CIUnitTestCase {

    public function testEveryFailCallIsFollowedByAReturn(): void {
        $offenders = [];

        foreach ($this->phpFilesUnderApp() as $path) {
            $lines = explode("\n", file_get_contents($path));

            foreach ($lines as $number => $line) {
                if (!preg_match('/\$this->(fail|error)\s*\(/', $line)) {
                    continue;
                }

                // The declarations of fail() and error() themselves, not calls to them.
                if (preg_match('/(public|protected|private)\s+function/', $line)) {
                    continue;
                }

                // error() is a thin wrapper that ends with fail(); its own callers are
                // what this test is about, and they are checked like any other.
                if (str_ends_with($path, 'Core/ResourceController.php') && str_contains($line, '$this->fail(')) {
                    continue;
                }

                if (!$this->nextStatementReturns($lines, $number)) {
                    $offenders[] = sprintf('%s:%d', $this->relative($path), $number + 1);
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['These refuse a request and then carry on anyway. Add `return;` after the call:'],
            $offenders
        )));
    }

    /**
     * @param string[] $lines
     */
    private function nextStatementReturns(array $lines, int $callLine): bool {
        $end = $callLine;
        while ($end < count($lines) - 1 && !str_ends_with(rtrim($lines[$end]), ';')) {
            $end++;
        }

        $next = $end + 1;
        while ($next < count($lines) && trim($lines[$next]) === '') {
            $next++;
        }

        return isset($lines[$next]) && str_starts_with(trim($lines[$next]), 'return');
    }

    /**
     * @return string[]
     */
    private function phpFilesUnderApp(): array {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(APPPATH));

        $paths = [];
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $paths[] = $file->getPathname();
            }
        }
        sort($paths);

        return $paths;
    }

    private function relative(string $path): string {
        return str_replace(APPPATH, 'app/', $path);
    }

}
