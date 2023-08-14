<?php namespace App\Controllers;

use App\Libraries\Kubernetes\KubeAuth;
use App\Libraries\Kubernetes\KubeHelper;
use App\Libraries\Kubernetes\KubeLog;
use DebugTool\Data;
use RenokiCo\PhpK8s\Kinds\K8sPod;

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
                $items[] = [
                    'namespace' => $pod->getNamespace(),
                    'name' => $pod->getName(),
                    'created' => date('Y-m-d H:i:s', strtotime_($pod->getStatus('startTime'))),
                    'status' => $pod->getStatus('phase'),
                ];
            }

            Data::set('resources', $items);
        } catch (\Exception $e) {
            $this->fail(KubeHelper::PrintException($e));
            return;
        }
        $this->success();
    }

    /**
     * @route /kubernetes/namespaces/{namespace}/pods/{name}/exec
     * @method put
     * @custom true
     * @param string $namespace
     * @param string $name
     * @parameter string $command parameterType=query
     * @responseSchema KubernetesExecResponse
     * @return void
     */
    public function exec(string $namespace, string $name): void {
        $command = $this->request->getGet('command');
        if (strlen($command) == 0) {
            $this->fail('missing command');
            return;
        }

        $kubeAuth = new KubeAuth();
        try {
            $cluster = $kubeAuth->authenticate();
            $pod = $cluster->getPodByName($name, $namespace);
            $messages = $pod->exec([
                '/bin/sh', '-c', $command
            ]);
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
     * @route /kubernetes/namespaces/{namespace}/pods/{pod}/logs
     * @method get
     * @custom true
     * @param string $namespace
     * @param string $pod
     * @return void
     * @responseSchema KubernetesLogEntry
     */
    public function getLogs(string $namespace, string $pod): void {
        $kubeAuth = new KubeAuth();
        try {
            $kubeLog = new KubeLog($kubeAuth->authenticate());
            $logs = $kubeLog->getLogs($namespace, $pod);
            Data::set('resources', $logs);
        } catch (\Exception $e) {
            $this->fail(KubeHelper::PrintException($e));
            return;
        }

        $this->success();
    }

    /**
     * @route /kubernetes/namespaces/{namespace}/pods/{pod}/logs/watch
     * @method put
     * @custom true
     * @param string $namespace
     * @param string $pod
     * @return void
     */
    public function watchLogs(string $namespace, string $pod): void {
        $kubeAuth = new KubeAuth();
        try {
            $kubeLog = new KubeLog($kubeAuth->authenticate());
            $kubeLog->watchLog($namespace, $pod);
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

    public function requireAuth(string $method): bool {
        return true;
    }
}
