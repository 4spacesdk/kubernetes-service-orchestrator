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
        $userConfig = yaml_parse($config)['users'][0];

        if (isset($userConfig['user']['exec'])) {
            $cluster->withTokenFromCommandProvider(
                $userConfig['user']['exec']['command'],
                implode(' ', $userConfig['user']['exec']['args'] ?? []),
                'status.token'
            );
        }

        return $cluster;
    }

}
