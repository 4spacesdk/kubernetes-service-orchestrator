<?php namespace App\Entities;

use App\Core\Entity;
use App\Entities\Concerns\SecretEnvironmentVariable;

/**
 * Class DeploymentPackageEnvironmentVariable
 * @package App\Entities
 * @property int $deployment_package_id
 * @property DeploymentPackage $deployment_package
 * @property string $name
 * @property string $value
 * @property bool $is_secret the value is write-only when set, see SecretEnvironmentVariable
 * @property bool $has_value
 */
class DeploymentPackageEnvironmentVariable extends Entity {

    use SecretEnvironmentVariable;

    public static function Create(string $name, string $value, bool $isSecret = false): DeploymentPackageEnvironmentVariable {
        $item = new DeploymentPackageEnvironmentVariable();
        $item->name = $name;
        $item->value = $value;
        $item->is_secret = $isSecret;
        $item->save();
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DeploymentPackageEnvironmentVariable[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
