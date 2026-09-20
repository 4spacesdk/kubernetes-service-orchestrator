<?php namespace App\Libraries\Kubernetes;

use DebugTool\Data;
use RenokiCo\PhpK8s\KubernetesCluster;

class KubeAuth {

    public function __construct() {
    }

    /**
     * @throws \Exception
     */
    public function authenticate(): KubernetesCluster {
        switch (env('KUBERNETES_AUTH')) {
            case 'kube-config':
                return $this->authenticateWithKubeConfig();
            case 'in-cluster':
                return $this->authenticateWithInClusterConfiguration();
            default:
                throw new \Exception('missing KUBERNETES_AUTH');
        }
    }

    private function authenticateWithInClusterConfiguration(): KubernetesCluster {
        return KubernetesCluster::inClusterConfiguration(getenv('REMOTE_CLUSTER_URL'));
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

        $cluster = KubernetesCluster::fromKubeConfigYaml($config);
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
