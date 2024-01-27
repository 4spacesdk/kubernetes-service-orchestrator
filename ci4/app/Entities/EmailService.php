<?php namespace App\Entities;

use RestExtension\Core\Entity;

/**
 * Class EmailService
 * @package App\Entities
 * @property string $name
 * @property string $host
 * @property int $port
 * @property string $user
 * @property string $pass
 * @property string $from
 *
 * Many
 * @property Workspace $workspaces
 * @property Deployment $deployments
 */
class EmailService extends Entity {

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|EmailService[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
