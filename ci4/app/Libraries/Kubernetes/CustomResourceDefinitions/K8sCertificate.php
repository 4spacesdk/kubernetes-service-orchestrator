<?php namespace App\Libraries\Kubernetes\CustomResourceDefinitions;

use RenokiCo\PhpK8s\Contracts\InteractsWithK8sCluster;
use RenokiCo\PhpK8s\Kinds\K8sResource;

class K8sCertificate extends K8sResource implements InteractsWithK8sCluster {

    /**
     * The resource Kind parameter.
     *
     * @var null|string
     */
    protected static $kind = 'Certificate';

    /**
     * The default version for the resource.
     *
     * @var string
     */
    protected static $defaultVersion = 'cert-manager.io/v1';

    /**
     * Wether the resource has a namespace.
     *
     * @var bool
     */
    protected static $namespaceable = true;

}
