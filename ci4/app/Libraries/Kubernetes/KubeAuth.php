<?php namespace App\Libraries\Kubernetes;

use DebugTool\Data;
use RenokiCo\PhpK8s\KubernetesCluster;

class KubeAuth {

    /**
     * The cluster every `authenticate()` hands back while it is set - see `Using()`.
     *
     * Deployment steps build their own `KubeAuth`, deep inside `checkStatus()`, so this is the
     * one place that can put an `IndexedCluster` in their hands without changing all of them.
     */
    private static ?KubernetesCluster $override = null;

    public function __construct() {
    }

    /**
     * Run $work with every `authenticate()` answering $cluster - the status check reading from a
     * `ClusterIndex` rather than asking the api server per step. Put back afterwards, whatever
     * happens: a cron run that threw would otherwise leave every later request on the index.
     *
     * @template T
     * @param \Closure(): T $work
     * @return T
     */
    public static function Using(KubernetesCluster $cluster, \Closure $work): mixed {
        $previous = self::$override;
        self::$override = $cluster;
        try {
            return $work();
        } finally {
            self::$override = $previous;
        }
    }

    /**
     * @throws \Exception
     */
    public function authenticate(): KubernetesCluster {
        if (self::$override) {
            return self::$override;
        }

        switch (env('KUBERNETES_AUTH')) {
            case 'kube-config':
                return $this->authenticateWithKubeConfig();
            case 'in-cluster':
                return $this->authenticateWithInClusterConfiguration();
            default:
                throw new \Exception('missing KUBERNETES_AUTH');
        }
    }

    /**
     * Run $work against a cluster that answers from one round of lists - the cheap way to check
     * the status of more than a couple of deployments at once.
     *
     * A cluster that cannot be read leaves $work to ask it step by step, and fail the way it
     * would have: this is a shortcut, not a second answer.
     *
     * @template T
     * @param \Closure(): T $work
     * @return T
     */
    public static function UsingAnIndex(\Closure $work): mixed {
        try {
            $cluster = (new KubeAuth())->indexed(ClusterIndex::Of([]));
            $cluster->useIndex(ClusterIndex::Fetch($cluster));
        } catch (\Throwable $e) {
            Data::debug('No index, asking the cluster as usual:', KubeHelper::PrintException($e));
            return $work();
        }

        return self::Using($cluster, $work);
    }

    /**
     * The same cluster, connected the same way, that hands out log streams to read from.
     *
     * @throws \Exception
     */
    public function streaming(): StreamingCluster {
        /** @var StreamingCluster $cluster */
        $cluster = $this->buildAs(StreamingCluster::class);
        return $cluster;
    }

    /**
     * The same cluster, connected the same way, that answers what the index knows.
     *
     * @throws \Exception
     */
    public function indexed(ClusterIndex $index): IndexedCluster {
        /** @var IndexedCluster $cluster */
        $cluster = $this->buildAs(IndexedCluster::class);
        return $cluster->useIndex($index);
    }

    /**
     * Connect as one of `KubernetesCluster`'s own subclasses. The override is put aside while it
     * happens: this is asked for *because* a plain cluster is not what is wanted.
     *
     * @param class-string<KubernetesCluster> $class
     * @throws \Exception
     */
    private function buildAs(string $class): KubernetesCluster {
        $previous = self::$override;
        self::$override = null;
        try {
            $this->as = $class;
            return $this->authenticate();
        } finally {
            $this->as = KubernetesCluster::class;
            self::$override = $previous;
        }
    }

    /**
     * Which class the factories below build. They are `new static`, so asking `IndexedCluster`
     * for a kubeconfig gives an `IndexedCluster` connected exactly as the ordinary one.
     *
     * @var class-string<KubernetesCluster>
     */
    private string $as = KubernetesCluster::class;

    private function authenticateWithInClusterConfiguration(): KubernetesCluster {
        return ($this->as)::inClusterConfiguration(getenv('REMOTE_CLUSTER_URL'));
    }

    private function authenticateWithKubeConfig(): KubernetesCluster {
        if (getenv('GCLOUD_SERVICE_KEY_FILE')) {
            if (!file_exists('/tmp/gcloud-service-account.json')) {
                file_put_contents('/tmp/gcloud-service-account.json', base64_decode(getenv('GCLOUD_SERVICE_KEY_FILE')));
                putenv('GOOGLE_APPLICATION_CREDENTIALS=/tmp/gcloud-service-account.json');

                $gcloudProject = getenv('GCLOUD_PROJECT_ID');
                $gcloudLocation = getenv('GCLOUD_LOCATION');
                $gcloudCluster = getenv('GCLOUD_CLUSTER');
                putenv('HOME=/home/www-data/');
                shell_exec("gke-auth --project=$gcloudProject --location=$gcloudLocation --cluster=$gcloudCluster");
            }
            putenv('GOOGLE_APPLICATION_CREDENTIALS=/tmp/gcloud-service-account.json');
            $_SERVER['GOOGLE_APPLICATION_CREDENTIALS'] = '/tmp/gcloud-service-account.json';
        }

        if (getenv('KUBERNETES_KUBECONFIG')) {
            $config = base64_decode(getenv('KUBERNETES_KUBECONFIG'));
        } else {
            $config = file_get_contents('/home/www-data/.kube/config');
        }

        $cluster = ($this->as)::fromKubeConfigYaml($config);
        $userConfig = $this->userForTheCurrentContext(yaml_parse($config));

        if (isset($userConfig['user']['exec'])) {
            $cluster->withTokenFromCommandProvider(
                $userConfig['user']['exec']['command'],
                implode(' ', $userConfig['user']['exec']['args'] ?? []),
                'status.token'
            );
        }

        return $cluster;
    }

    /**
     * The user the kubeconfig's current context names.
     *
     * The same user php-k8s connected as: `LoadsFromKubeConfig` reads `current-context`,
     * finds that context, and takes the cluster and the user it names. This used to read
     * `users[0]` instead - so in a kubeconfig with more than one user, which is what anybody
     * who has run `gcloud container clusters get-credentials` twice has, the *first* user's
     * exec block was run to fetch a token for the *second* user's cluster.
     *
     * It failed as a 401 from an api server that had been reached correctly, which is about
     * the least informative way this could go wrong.
     *
     * @param array<string, mixed> $kubeConfig
     * @return array<string, mixed>|null the user entry, or null when the file names none
     */
    private function userForTheCurrentContext(array $kubeConfig): ?array {
        $contextName = $kubeConfig['current-context'] ?? null;

        foreach ($kubeConfig['contexts'] ?? [] as $context) {
            if (($context['name'] ?? null) !== $contextName) {
                continue;
            }

            $userName = $context['context']['user'] ?? null;
            foreach ($kubeConfig['users'] ?? [] as $user) {
                if (($user['name'] ?? null) === $userName) {
                    return $user;
                }
            }
        }

        // Nothing to hand back. php-k8s has already refused a context it cannot find, so
        // reaching this means the file names a user the `users` list does not carry - and
        // an exec block that is not there is better than the wrong one.
        return null;
    }

}
