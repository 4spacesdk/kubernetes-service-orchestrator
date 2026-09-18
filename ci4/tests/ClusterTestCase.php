<?php namespace App;

/**
 * Base for tests that deploy to a real cluster and then take it down again.
 *
 * Everything else in this suite builds manifests and never sends them. This is the other
 * half: does the api server accept what we built, does the resource appear, does the step
 * report it as found, and does terminating remove it. None of that can be answered without
 * a cluster, and it is where the deployment steps spend most of their lines.
 *
 * **These tests write.** `IntegrationTestCase` asks only that a cluster is configured and
 * says its tests must stay read only. That is not enough here, so this asks for a second
 * thing on top:
 *
 *     KUBERNETES_TEST_CLUSTER=disposable
 *
 * A cluster somebody cares about will not have that set, and the tests skip. Setting it on
 * a real one is a deliberate act, and the name says what it means.
 *
 * Database rows still roll back with the transaction. **Cluster resources do not** - the
 * cluster has never heard of the transaction - so each test works inside a namespace of
 * its own and removes it on the way out.
 */
abstract class ClusterTestCase extends DatabaseTestCase {

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
