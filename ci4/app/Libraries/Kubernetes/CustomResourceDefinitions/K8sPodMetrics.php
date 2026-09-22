<?php namespace App\Libraries\Kubernetes\CustomResourceDefinitions;

use RenokiCo\PhpK8s\Contracts\InteractsWithK8sCluster;
use RenokiCo\PhpK8s\Kinds\K8sResource;

/**
 * What `kubectl top pod` reads: one of these per pod, holding each container's cpu and memory as
 * measured over the window it names. Served by metrics-server, which is not part of Kubernetes -
 * a cluster without it answers 404, and that is not an error worth shouting about.
 */
class K8sPodMetrics extends K8sResource implements InteractsWithK8sCluster {

    protected static $kind = 'PodMetrics';

    protected static $defaultVersion = 'metrics.k8s.io/v1beta1';

    protected static $namespaceable = true;

    /**
     * `pods`, not what the kind pluralises to: the api is
     * `/apis/metrics.k8s.io/v1beta1/namespaces/{namespace}/pods`, and php-k8s would otherwise
     * build `podmetrics` from the kind and get a 404 that reads like a cluster without
     * metrics-server.
     */
    public static function getPlural() {
        return 'pods';
    }

}
