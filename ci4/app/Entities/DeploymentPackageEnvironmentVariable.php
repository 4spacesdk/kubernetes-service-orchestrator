<?php namespace App\Entities;

use RestExtension\Core\Entity;

/**
 * Class DeploymentPackageEnvironmentVariable
 * @package App\Entities
 * @property int $deployment_package_id
 * @property DeploymentPackage $deployment_package
 * @property string $name
 * @property string $value
 */
class DeploymentPackageEnvironmentVariable extends Entity {

    public static function Create(string $name, string $value): DeploymentPackageEnvironmentVariable {
        $item = new DeploymentPackageEnvironmentVariable();
        $item->name = $name;
        $item->value = $value;
        $item->save();
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DeploymentPackageEnvironmentVariable[]
     */
    public function getIterator() {
        return parent::getIterator();
    }

}
