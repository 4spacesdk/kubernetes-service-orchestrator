<?php namespace App;

use RenokiCo\PhpK8s\KubernetesCluster;

/**
 * Everything a test needs to work against the throwaway cluster, so that two base classes
 * can have it.
 *
 * `ClusterTestCase` is one of them. The other is `ClusterControllerTestCase`, which sends
 * real requests as well - and a controller endpoint that drives the cluster needs both
 * halves at once. PHP has no multiple inheritance, and the two halves are set up by base
 * classes that both define `setUp()`, so this is a trait with **named hooks** rather than
 * a `setUp()` of its own: a class's own method always wins over a trait's, and the hook
 * would silently never run.
 *
 * `keepTheClusterOutOfIt()` is different and is here: a trait *does* win over an inherited
 * method, so this is what turns `DatabaseTestCase`'s blanket ban back off.
 */
trait RunsAgainstACluster {

    protected string $testNamespace = '';  // not $namespace: CIUnitTestCase already uses that for migrations

    /** @var string[] namespaces beyond the first, made by `anotherNamespace()` */
    private array $extraNamespaces = [];

    protected static function giveThisRunItsOwnCertificateFolder(): void {
        if (!self::clusterIsDisposable()) {
            return;
        }

        // php-k8s writes the kubeconfig's certificates to files in the temp folder, named
        // after the context and the url - and reuses a file that is already there rather
        // than the contents it was handed. Two throwaway clusters share a name and a url
        // but not a certificate authority, so the second run would be checked against the
        // first cluster's, and every call fails with a self-signed certificate. A folder
        // of our own per run is the whole fix.
        $folder = sys_get_temp_dir() . '/kso-test-certs-' . getmypid();
        @mkdir($folder);
        KubernetesCluster::setTempFolder($folder);
    }

    protected function setUpTheCluster(): void {
        if (!self::clusterIsDisposable()) {
            $this->markTestSkipped(
                'Needs a throwaway cluster. Run: npm run "Test: cluster (starts one)".'
            );
        }

        // A namespace per test, so a test that fails half way cannot leave anything for the
        // next one to trip over, and two runs never collide.
        $this->testNamespace = 'kso-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDownTheCluster(): void {
        $this->removeTheNamespaces();
        $this->removeWhatIsNotInANamespace();
    }

    /**
     * A second namespace for this test, removed with the first.
     *
     * Some of what kso builds is deliberately spread across namespaces - a gateway in one,
     * a certificate in another - and the interesting part is the permission that bridges
     * them. That cannot be arranged inside a single namespace.
     */
    protected function anotherNamespace(string $suffix): string {
        $name = $this->testNamespace . '-' . $suffix;

        $this->cluster()->namespace()->setName($name)->createOrUpdate();
        $this->extraNamespaces[] = $name;

        return $name;
    }

    /**
     * The opposite of what the name says, and on purpose: this is the one place that wants
     * a cluster. `DatabaseTestCase` cleared the credentials on the first test of the run,
     * so they are put back rather than merely left alone.
     */
    protected function keepTheClusterOutOfIt(): void {
        ClusterEnvironment::restore();
    }

    /**
     * A deployment whose namespace is this test's own, so everything it applies is removed
     * with that namespace afterwards.
     *
     * @param array<string, mixed> $overrides
     * @param array<string, mixed> $workspaceOverrides
     */
    protected function deploymentInTheTestNamespace(array $overrides = [], array $workspaceOverrides = []): \App\Entities\Deployment {
        $image = Fixtures::containerImage(['url' => 'nginx', 'default_tag' => '1.29-alpine']);
        $specification = Fixtures::deploymentSpecification([
            'name' => 'api',
            'container_image_id' => $image->id,
            'enable_internal_access' => true,
        ]);
        $workspace = Fixtures::workspace(array_merge(
            ['namespace' => $this->testNamespace, 'subdomain' => 'tenant'],
            $workspaceOverrides
        ));

        return Fixtures::deployment(array_merge([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $specification->id,
            'namespace' => $this->testNamespace,
            'name' => 'api',
            'image' => 'nginx',
            'version' => '1.29-alpine',
            'image_pull_policy' => \ImagePullPolicies::IfNotPresent,
            'replicas' => 1,
        ], $overrides));
    }

    /**
     * Wait for the cluster to catch up, then assert.
     *
     * Deleting is asynchronous: php-k8s asks for foreground propagation, so the api server
     * marks the resource and answers before it is gone. `exists()` on the very next line
     * still says true, and about two hundred milliseconds later it does not. A terminate
     * test that asserts straight away passes or fails on timing - which is worse than
     * failing, because it passes most of the time.
     *
     * @param callable(): bool $condition
     */
    protected function eventually(callable $condition, string $message = ''): void {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            if ($condition()) {
                $this->assertTrue(true);

                return;
            }

            usleep(100000);
        }

        $this->fail($message !== '' ? $message : 'The cluster never got there - waited 5 seconds.');
    }

    protected function cluster(): \RenokiCo\PhpK8s\KubernetesCluster {
        return (new \App\Libraries\Kubernetes\KubeAuth())->authenticate();
    }

    private static function clusterIsDisposable(): bool {
        return getenv('KUBERNETES_TEST_CLUSTER') === 'disposable'
            && ClusterEnvironment::isConfigured();
    }

    /**
     * Delete the namespace and everything in it. Kubernetes does the cascade; we do not
     * wait for it, because the next test uses a name of its own.
     */
    private function removeTheNamespaces(): void {
        if ($this->testNamespace === '') {
            return;
        }

        foreach ([$this->testNamespace, ...$this->extraNamespaces] as $name) {
            try {
                $cluster = (new \App\Libraries\Kubernetes\KubeAuth())->authenticate();

                // `getNamespaceByName()` fetches the real resource. A query built with
                // `namespace()->whereName()` answers `exists()` but has no id to delete
                // by, so deleting through it silently does nothing - which is how thirteen
                // namespaces piled up before anyone looked.
                $cluster->getNamespaceByName($name)->delete();
            } catch (\Throwable) {
                // Nothing was created, or the cluster has gone away. Neither leaks.
            }
        }
    }

    /**
     * Three kinds do not live in a namespace, so deleting the namespace does not take them
     * with it. Each carries the namespace in its name - `<name>.<namespace>` for the two
     * cluster roles, `<namespace>-<name>` for a persistent volume - and the namespace is
     * unique to this test, so that name is what makes them findable again.
     */
    private function removeWhatIsNotInANamespace(): void {
        if ($this->testNamespace === '') {
            return;
        }

        try {
            $cluster = (new \App\Libraries\Kubernetes\KubeAuth())->authenticate();
            $suffix = '.' . $this->testNamespace;
            $prefix = $this->testNamespace . '-';

            foreach ($cluster->getAllClusterRoleBindings() as $binding) {
                if (str_ends_with($binding->getName(), $suffix)) {
                    $binding->delete();
                }
            }
            foreach ($cluster->getAllClusterRoles() as $role) {
                if (str_ends_with($role->getName(), $suffix)) {
                    $role->delete();
                }
            }
            foreach ($cluster->getAllPersistentVolumes() as $volume) {
                if (str_starts_with($volume->getName(), $prefix)) {
                    $volume->delete();
                }
            }
        } catch (\Throwable) {
            // As above.
        }
    }

}
