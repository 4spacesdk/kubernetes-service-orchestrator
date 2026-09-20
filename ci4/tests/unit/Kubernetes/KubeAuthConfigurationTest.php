<?php namespace App\Tests\Unit\Kubernetes;

use App\ClusterEnvironment;
use App\Libraries\Kubernetes\KubeAuth;
use CodeIgniter\Test\CIUnitTestCase;
use RenokiCo\PhpK8s\KubernetesCluster;

/**
 * How kso builds the cluster object, short of talking to a cluster.
 *
 * `KubeAuthTest` in the integration suite asks the other question - do the credentials this
 * installation was given actually get in. This one is about the two branches that cannot be
 * reached that way: `in-cluster`, which needs kso to be running inside a pod, and a
 * kubeconfig whose user is an `exec` block, which is how GKE hands out a token. Neither
 * needs a network, because neither reaches one: `inClusterConfiguration()` reads service
 * account files that are not there, and the exec provider runs a command.
 *
 * The command run below is `echo`. It is the cheapest thing that can stand in for
 * `gke-gcloud-auth-plugin`, it writes exactly what a credential plugin writes, and it
 * touches nothing.
 *
 * **Nothing here holds a real credential.** The token is a literal, the server url points
 * at a name that does not resolve, and `KUBERNETES_AUTH` goes back to what the run started
 * with through `ClusterEnvironment` - which is the same thing `ClusterOutages` does, and
 * for the same reason: a variable a test writes is written for every test after it.
 *
 * The one branch of `KubeAuth` left uncovered on purpose is the `GCLOUD_SERVICE_KEY_FILE`
 * block. Entering it writes a service account key to `/tmp` and shells out to `gke-auth`,
 * which is a real process reaching a real Google endpoint - the two things a test here is
 * not allowed to do. Removing that branch therefore survives every mutation, and it can
 * only be checked against a GKE installation.
 */
class KubeAuthConfigurationTest extends CIUnitTestCase {

    // <editor-fold desc="An auth method that is not one of the two">

    /**
     * Neither `kube-config` nor `in-cluster` is a configuration mistake, and the point of
     * the `default` arm is that it says so here rather than letting an unconfigured
     * installation fall through and fail somewhere deep in php-k8s with a message about
     * a missing file.
     *
     * `KubeAuthTest` asserts the same thing, but it lives in the integration suite and
     * skips itself when there is no cluster configured - which is exactly the installation
     * that hits this arm. Nothing about the check needs a cluster, so it is also checked
     * here, where it always runs.
     */
    public function testAnAuthMethodThatIsNeitherIsRejected(): void {
        $this->withAuthMethod('something-else', function (): void {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('missing KUBERNETES_AUTH');

            (new KubeAuth())->authenticate();
        });
    }

    public function testNoAuthMethodAtAllIsRejected(): void {
        $this->withAuthMethod('', function (): void {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('missing KUBERNETES_AUTH');

            (new KubeAuth())->authenticate();
        });
    }

    // </editor-fold>

    // <editor-fold desc="Running inside the cluster">

    /**
     * kso deployed into the cluster it manages. The service account's token, certificate
     * and namespace are files the kubelet mounts, and php-k8s reads whichever of them
     * exist - so outside a pod this builds a cluster object and nothing else.
     */
    public function testInClusterAuthenticationBuildsAClusterFromTheServiceAccount(): void {
        $this->withAuthMethod('in-cluster', function (): void {
            $this->assertInstanceOf(KubernetesCluster::class, (new KubeAuth())->authenticate());
        });
    }

    /**
     * Without `REMOTE_CLUSTER_URL`, the cluster is left with no address at all.
     *
     * php-k8s defaults the api server to `https://kubernetes.default.svc`, which works from
     * inside any pod - but kso passes `getenv('REMOTE_CLUSTER_URL')` to it, and an unset
     * variable is `false`, which a string parameter takes as `''`. The default is never
     * reached.
     *
     * **Which is not the failure it looks like.** The helm chart sets both halves of this:
     * `KUBERNETES_AUTH: in-cluster` is hardcoded, and `REMOTE_CLUSTER_URL` comes from
     * `deployment.kubernetes.remoteClusterUrl` through helm's `required`, so an installation
     * without it does not install. `in-cluster` without the variable therefore means kso was
     * deployed by something other than the chart.
     *
     * Held as what the code does, so that a fallback added here is added on purpose. The
     * name is right, by the way: it is `REMOTE_CLUSTER_URL` that the chart sets, and
     * `KUBERNETES_REMOTE_CLUSTER_URL` in `ci4/env` that nothing reads.
     */
    public function testInClusterAuthenticationHasNoAddressWithoutTheVariable(): void {
        $this->withAuthMethod('in-cluster', function (): void {
            $cluster = (new KubeAuth())->authenticate();

            $this->assertSame('', $this->urlOf($cluster), 'https://kubernetes.default.svc never survives');
        });
    }

    // </editor-fold>

    // <editor-fold desc="A kubeconfig whose user is a command">

    /**
     * How GKE and every other managed cluster hand out credentials: the kubeconfig names a
     * plugin, and the plugin prints an ExecCredential with a short lived token in it. kso
     * has to pick the command, its arguments and the path to the token out of the file and
     * hand all three to php-k8s - and if it does not, every call goes out unauthenticated
     * and the api server answers 401 with nothing saying why.
     */
    public function testAnExecUserHasItsTokenFetchedByRunningTheCommand(): void {
        $this->withKubeConfig($this->kubeConfigWithExecUser(), function (): void {
            $cluster = (new KubeAuth())->authenticate();

            $this->assertSame('kso-test-token', $this->tokenOf($cluster));
        });
    }

    /**
     * A kubeconfig whose user is a plain token needs no command, and asking for one would
     * run whatever happened to be in the file.
     */
    public function testAUserWithoutAnExecBlockIsLeftAlone(): void {
        $this->withKubeConfig($this->kubeConfigWithTokenUser(), function (): void {
            $cluster = (new KubeAuth())->authenticate();

            $this->assertSame('kso-test-static-token', $this->tokenOf($cluster));
        });
    }

    /**
     * The token comes from the user the **current context** names, which is the same user
     * php-k8s connected as.
     *
     * kso used to reach back into the file and read `users[0]` to look for an exec block. In
     * a kubeconfig with more than one user - which is what any developer who has ever run
     * `gcloud container clusters get-credentials` twice has - those are different people,
     * and the token of the first was applied to a connection to the second's cluster.
     *
     * It failed as a 401 from an api server that had been reached correctly, which is about
     * the least informative way this could go wrong. The kubeconfig below lists the wrong
     * user first on purpose.
     */
    public function testTheExecBlockIsTakenFromTheUserTheContextNames(): void {
        $this->withKubeConfig($this->kubeConfigWhereTheContextNamesTheSecondUser(), function (): void {
            $cluster = (new KubeAuth())->authenticate();

            $this->assertSame(
                'kso-test-token',
                $this->tokenOf($cluster),
                'the first user in the file was run instead of the one the context names'
            );
        });
    }

    // </editor-fold>

    // <editor-fold desc="Kubeconfigs">

    /**
     * `echo` standing in for a credential plugin: it prints the ExecCredential object the
     * real ones print, and `status.token` is the path kso pulls the token out of.
     */
    private function kubeConfigWithExecUser(): string {
        return $this->kubeConfig([
            [
                'name' => 'kso-test-user',
                'user' => [
                    'exec' => [
                        'apiVersion' => 'client.authentication.k8s.io/v1beta1',
                        'command' => 'echo',
                        'args' => ['\'{"status":{"token":"kso-test-token"}}\''],
                    ],
                ],
            ],
        ]);
    }

    private function kubeConfigWithTokenUser(): string {
        return $this->kubeConfig([
            ['name' => 'kso-test-user', 'user' => ['token' => 'kso-test-static-token']],
        ]);
    }

    private function kubeConfigWhereTheContextNamesTheSecondUser(): string {
        return $this->kubeConfig(
            [
                [
                    'name' => 'someone-else',
                    'user' => [
                        'exec' => [
                            'apiVersion' => 'client.authentication.k8s.io/v1beta1',
                            'command' => 'echo',
                            'args' => ['\'{"status":{"token":"the-other-clusters-token"}}\''],
                        ],
                    ],
                ],
                [
                    'name' => 'kso-test-user',
                    'user' => [
                        'exec' => [
                            'apiVersion' => 'client.authentication.k8s.io/v1beta1',
                            'command' => 'echo',
                            'args' => ['\'{"status":{"token":"kso-test-token"}}\''],
                        ],
                    ],
                ],
            ]
        );
    }

    /**
     * A kubeconfig with no certificates in it, so nothing is written to disk. The server
     * name does not resolve and is never called.
     *
     * @param array<int, array<string, mixed>> $users the context always names the last one
     */
    private function kubeConfig(array $users): string {
        return base64_encode(yaml_emit([
            'apiVersion' => 'v1',
            'kind' => 'Config',
            'current-context' => 'kso-test',
            'clusters' => [
                ['name' => 'kso-test-cluster', 'cluster' => ['server' => 'https://kso-test.invalid:6443']],
            ],
            'contexts' => [
                [
                    'name' => 'kso-test',
                    'context' => ['cluster' => 'kso-test-cluster', 'user' => end($users)['name']],
                ],
            ],
            'users' => $users,
        ]));
    }

    // </editor-fold>

    // <editor-fold desc="Environment and reflection">

    /**
     * @param callable(): void $body
     */
    private function withAuthMethod(string $method, callable $body): void {
        // env() reads $_ENV and $_SERVER before getenv(), so all three have to be set.
        putenv('KUBERNETES_AUTH=' . $method);
        $_ENV['KUBERNETES_AUTH'] = $method;
        $_SERVER['KUBERNETES_AUTH'] = $method;

        try {
            $body();
        } finally {
            // Back to the value the run was started with, taken at bootstrap.
            ClusterEnvironment::restore();
        }
    }

    /**
     * @param callable(): void $body
     */
    private function withKubeConfig(string $base64, callable $body): void {
        $original = getenv('KUBERNETES_KUBECONFIG');

        putenv('KUBERNETES_KUBECONFIG=' . $base64);

        try {
            $this->withAuthMethod('kube-config', $body);
        } finally {
            putenv('KUBERNETES_KUBECONFIG=' . ($original === false ? '' : $original));
        }
    }

    private function tokenOf(KubernetesCluster $cluster): ?string {
        $property = new \ReflectionProperty(KubernetesCluster::class, 'token');

        return $property->getValue($cluster);
    }

    private function urlOf(KubernetesCluster $cluster): ?string {
        $property = new \ReflectionProperty(KubernetesCluster::class, 'url');

        return $property->getValue($cluster);
    }

    // </editor-fold>

}
