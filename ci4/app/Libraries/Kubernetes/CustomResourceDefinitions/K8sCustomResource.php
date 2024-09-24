<?php namespace App\Libraries\Kubernetes\CustomResourceDefinitions;

use DebugTool\Data;
use RenokiCo\PhpK8s\Contracts\InteractsWithK8sCluster;
use RenokiCo\PhpK8s\Kinds\K8sResource;

class K8sCustomResource extends K8sResource implements InteractsWithK8sCluster {

    /**
     * The resource Kind parameter.
     *
     * @var null|string
     */
    protected static $kind = '';

    /**
     * The default version for the resource.
     *
     * @var string
     */
    protected static $defaultVersion = '';

    /**
     * Wether the resource has a namespace.
     *
     * @var bool
     */
    protected static $namespaceable = true;

    public function __construct($cluster = null, array $attributes = []) {
        $this::$kind = $attributes['kind'] ?? '';
        $this::$defaultVersion = $apiVersion['apiVersion'] ?? '';
        parent::__construct($cluster, $attributes);
    }

}
