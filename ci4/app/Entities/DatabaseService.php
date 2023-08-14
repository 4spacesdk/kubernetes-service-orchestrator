<?php namespace App\Entities;

use RestExtension\Core\Entity;

/**
 * Class DatabaseService
 * @package App\Entities
 * @property string $name
 * @property string $host
 * @property string $azure_host
 * @property string $port
 * @property string $user
 * @property string $pass
 *
 * Many
 * @property Workspace $workspaces
 * @property Deployment $deployments
 */
class DatabaseService extends Entity {

    public function getDatabaseUser($name): string {
        if (getenv('IS_AZURE')) {
            return  "{$name}@{$this->azure_host}";
        } else {
            return $name;
        }
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DatabaseService[]
     */
    public function getIterator() {
        return parent::getIterator();
    }

}
