<?php namespace App;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Base for tests that talk to something real: a Kubernetes cluster, a container registry,
 * an actual API.
 *
 * These are not part of the default run. They need credentials that only exist on a
 * developer machine or in a job that was given them, so each one declares what it needs
 * and skips itself when that is missing. A missing cluster then reads as "skipped", not
 * as a failure, and the suite never blocks a commit.
 *
 *     class SomethingTest extends IntegrationTestCase {
 *         protected function requiredEnvironment(): array {
 *             return ['KUBERNETES_AUTH'];
 *         }
 *     }
 *
 * Run them on their own by passing the integration testsuite to phpunit.
 *
 * **Keep them read only.** They run against whatever cluster the environment points at,
 * and nothing stops that from being a real one. A test that creates or deletes cluster
 * resources belongs behind a switch of its own, against a cluster meant to be thrown
 * away - not here.
 */
abstract class IntegrationTestCase extends CIUnitTestCase {

    /**
     * Environment variables this test cannot run without.
     *
     * @return string[]
     */
    abstract protected function requiredEnvironment(): array;

    public function setUp(): void {
        parent::setUp();

        // The ordinary suite clears the cluster credentials from the process, and it runs
        // first. Without this every test here skips itself in a combined run.
        ClusterEnvironment::restore();

        $missing = [];
        foreach ($this->requiredEnvironment() as $name) {
            if (getenv($name) === false || getenv($name) === '') {
                $missing[] = $name;
            }
        }

        if ($missing !== []) {
            $this->markTestSkipped(
                'Needs ' . implode(', ', $missing) . '. Integration tests only run where that is configured.'
            );
        }
    }

}
