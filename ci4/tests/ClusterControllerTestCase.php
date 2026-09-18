<?php namespace App;

/**
 * Real requests through the whole stack, against a real throwaway cluster.
 *
 * Several endpoints exist only to drive the cluster - apply a gateway, ask a deployment
 * step for its status, fetch a certificate's events. `ControllerTestCase` cannot reach
 * them because `DatabaseTestCase` deliberately cuts the cluster off for everything that is
 * not a cluster test, and `ClusterTestCase` cannot reach them because it does not send
 * requests. This is both halves.
 *
 * Skips itself without a throwaway cluster, exactly as `ClusterTestCase` does, so it lives
 * in the integration suite.
 */
abstract class ClusterControllerTestCase extends ControllerTestCase {

    use RunsAgainstACluster;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();

        self::giveThisRunItsOwnCertificateFolder();
    }

    public function setUp(): void {
        parent::setUp();

        $this->setUpTheCluster();
    }

    public function tearDown(): void {
        $this->tearDownTheCluster();

        parent::tearDown();
    }

}
