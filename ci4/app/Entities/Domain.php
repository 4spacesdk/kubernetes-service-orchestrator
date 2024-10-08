<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class Domain
 * @package App\Entities
 * @property string $name
 * @property string $certificate_namespace
 * @property string $certificate_name
 * @property string $issuer_ref_name
 *
 * Many
 * @property Workspace $workspaces
 * @property Deployment $deployments
 */
class Domain extends Entity {

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|Domain[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
