<?php namespace App\Libraries\Kubernetes\CustomResourceDefinitions;

use RenokiCo\PhpK8s\Contracts\InteractsWithK8sCluster;
use RenokiCo\PhpK8s\Kinds\K8sResource;

/**
 * What `kubectl top node` reads: one per node, its cpu and memory as metrics-server measured them.
 * A cluster without metrics-server answers 404 - see `K8sPodMetrics`.
 */
class K8sNodeMetrics extends K8sResource implements InteractsWithK8sCluster {

    protected static $kind = 'NodeMetrics';

    protected static $defaultVersion = 'metrics.k8s.io/v1beta1';

    protected static $namespaceable = false;

    /** `nodes`, not `nodemetrics` - the api is `/apis/metrics.k8s.io/v1beta1/nodes`. */
    public static function getPlural() {
        return 'nodes';
    }

}
