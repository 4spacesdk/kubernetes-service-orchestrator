<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class Domain
 * @package App\Entities
 * @property string $name
 * @property string $certificate_namespace
 * @property string $certificate_name
 * @property string $issuer_ref_name
 * @property bool $enable_istio_gateway
 * @property bool $enable_contour
 * @property string $contour_ingress_class_name
 *
 * Many
 * @property Workspace $workspaces
 * @property Deployment $deployments
 */
class Domain extends Entity {

    public function getIstioGatewayName(): string {
        return str_replace('.', '-', $this->name);
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|Domain[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
