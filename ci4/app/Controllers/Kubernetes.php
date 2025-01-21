<?php namespace App\Controllers;

use App\Libraries\Kubernetes\KubeAuth;
use App\Libraries\Kubernetes\KubeHelper;
use App\Libraries\Kubernetes\KubeLog;
use DebugTool\Data;
use PHPUnit\Exception;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sNode;
use RenokiCo\PhpK8s\Kinds\K8sPod;
use RenokiCo\PhpK8s\ResourcesList;

class Kubernetes extends \App\Core\BaseController {

    /**
     * @route /kubernetes/namespaces/{namespace}/pods
     * @method get
     * @custom true
     * @param string $namespace
     * @parameter string $app parameterType=query
     * @parameter string $role parameterType=query
     * @responseSchema KubernetesPod
     */
    public function getPods(string $namespace): void {
        try {
            $app = $this->request->getGet('app');
            $role = $this->request->getGet('role');

            $cluster = (new KubeAuth())->authenticate();
            $pods = $cluster->getAllPods(
                $namespace,
                (strlen($app) && strlen($role)) ? [
                    'labelSelector' => urldecode(http_build_query(
                        [
                            "app" => "$app,role=$role"
                        ]
                    ))
                ] : [],
            );

            $items = [];
            /** @var K8sPod $pod */
            foreach ($pods as $pod) {
                foreach ($pod->getContainers() as $container) {
                    $items[] = [
                        'namespace' => $pod->getNamespace(),
                        'pod' => $pod->getName(),
                        'container' => $container->getName(),
                        'created' => date('Y-m-d H:i:s', strtotime_($pod->getStatus('startTime'))),
                        'status' => $pod->getStatus('phase'),
                    ];
                }
            }

            Data::set('resources', $items);
        } catch (\Exception $e) {
            $this->fail(KubeHelper::PrintException($e));
            return;
        }
        $this->success();
    }

    /**
     * @route /kubernetes/namespaces/{namespace}/pods/{name}/containers/{container}/exec
     * @method put
     * @custom true
     * @param string $namespace
     * @param string $name
     * @param string $container
     * @parameter string $command parameterType=query
     * @responseSchema KubernetesExecResponse
     * @return void
     */
    public function exec(string $namespace, string $name, string $container): void {
        $command = $this->request->getGet('command');
        if (strlen($command) == 0) {
            $this->fail('missing command');
            return;
        }

        $kubeAuth = new KubeAuth();
        try {
            $cluster = $kubeAuth->authenticate();
            $pod = $cluster->getPodByName($name, $namespace);
            $messages = $pod->exec(
                ['/bin/sh', '-c', $command],
                $container
            );
            $all = collect($messages)->where('channel', 'stdout')->all();
            $lines = [];
            foreach ($all as ['channel' => $channel, 'output' => $output]) {
                if (strlen($output) > 1) {
                    $lines[] = trim($output);
                }
            }
            $lines = explode("\n", implode("\n", $lines)); // K8s returning multiple vars in single line. This will fix that.

            Data::set('resource', [
                'lines' => $lines,
            ]);

        } catch (\Exception $e) {
            $this->fail(KubeHelper::PrintException($e));
            return;
        }

        $this->success();
    }

    /**
     * @route /kubernetes/namespaces/{namespace}/pods/{pod}/containers/{container}/logs
     * @method get
     * @custom true
     * @param string $namespace
     * @param string $pod
     * @param string $container
     * @return void
     * @responseSchema KubernetesLogEntry
     */
    public function getLogs(string $namespace, string $pod, string $container): void {
        $kubeAuth = new KubeAuth();
        try {
            $kubeLog = new KubeLog($kubeAuth->authenticate());
            Data::debug($namespace, $pod, $container);
            $logs = $kubeLog->getLogs($namespace, $pod, $container);
            Data::set('resources', $logs);
        } catch (\Exception $e) {
            $this->fail(KubeHelper::PrintException($e));
            return;
        }

        $this->success();
    }

    /**
     * @route /kubernetes/namespaces/{namespace}/pods/{pod}/containers/{container}/logs/watch
     * @method put
     * @custom true
     * @param string $namespace
     * @param string $pod
     * @param string $container
     * @return void
     */
    public function watchLogs(string $namespace, string $pod, string $container): void {
        $kubeAuth = new KubeAuth();
        try {
            $kubeLog = new KubeLog($kubeAuth->authenticate());
            $kubeLog->watchLog($namespace, $pod, $container);
        } catch (\Exception $e) {
            $this->fail(KubeHelper::PrintException($e));
            return;
        }

        $this->success();
    }

    /**
     * @route /kubernetes/rbac/show
     * @method get
     * @custom true
     * @return void
     * @responseSchema KubernetesRbacShowResponse
     */
    public function rbacShow(): void {
        $clusterRoleWithEverything = yaml_parse(file_get_contents(APPPATH . '/Libraries/Kubernetes/RBAC/ClusterRoleWithEverything.yaml'));

        $rules = [];
        foreach ($clusterRoleWithEverything['rules'] as $rule) {
            $rules[] = [
                'apiGroup' => $rule['apiGroups'][0],
                'resource' => $rule['resources'][0],
                'verbs' => $rule['verbs'],
            ];
        }

        Data::set('resource', [
            'rules' => $rules,
        ]);

        $this->success();
    }

    /**
     * @route /kubernetes/node-info
     * @method get
     * @custom true
     * @return void
     * @responseSchema KubernetesNodeInfoResponse
     */
    public function nodeInfo(): void {
        $kubeAuth = new KubeAuth();
        try {
            $cluster = $kubeAuth->authenticate();
            $nodes = $cluster->node()->all();
            Data::set('resource', [
                'status' => 'success',
                'message' => '',
                'nodes' => array_map(fn(K8sNode $node) => $node->getInfo(), $nodes->all()),
            ]);
        } catch (KubernetesAPIException $e) {
            Data::set('resource', [
                'status' => 'error',
                'message' => $e->getMessage(),
                'details' => $e->getPayload(),
                'nodes' => [],
            ]);
        } catch (\Exception $e) {
            Data::set('resource', [
                'status' => 'error',
                'message' => $e->getMessage(),
                'nodes' => [],
            ]);
        }
        $this->success();
    }

    public function requireAuth(string $method): bool {
        return true;
    }
}
