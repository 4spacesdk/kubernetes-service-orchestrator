<?php namespace App;

/**
 * The cluster credentials as the run was started with them.
 *
 * `DatabaseTestCase` clears `KUBERNETES_AUTH` on every test, so that nothing in the
 * ordinary suite reaches a cluster by accident. That is process wide, and all three suites
 * share one process: by the time the integration suite runs, the value the run was given
 * is long gone, and every test that needs a cluster would skip itself with the cluster
 * sitting right there.
 *
 * So the value is taken once at bootstrap, before a test can have touched it, and handed
 * back to the two base classes that are allowed to have it.
 */
class ClusterEnvironment {

    private static string $auth = '';

    /**
     * Called from the test bootstrap, before anything else runs.
     */
    public static function remember(): void {
        self::$auth = (string) getenv('KUBERNETES_AUTH');
    }

    public static function isConfigured(): bool {
        return self::$auth !== '';
    }

    /**
     * `env()` reads $_ENV and $_SERVER before getenv(), so all three have to be put back.
     */
    public static function restore(): void {
        putenv('KUBERNETES_AUTH=' . self::$auth);
        $_ENV['KUBERNETES_AUTH'] = self::$auth;
        $_SERVER['KUBERNETES_AUTH'] = self::$auth;
    }

}
