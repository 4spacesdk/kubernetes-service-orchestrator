<?php namespace App\Entities;

use App\Core\Entity;
use App\Entities\Concerns\SecretEnvironmentVariable;

/**
 * Class DeploymentSpecificationEnvironmentVariable
 * @package App\Entities
 * @property int $deployment_specification_id
 * @property DeploymentSpecification $deployment_specification
 * @property string $name
 * @property string $value
 * @property bool $is_secret the value is write-only when set, see SecretEnvironmentVariable
 * @property bool $has_value
 */
class DeploymentSpecificationEnvironmentVariable extends Entity {

    use SecretEnvironmentVariable;

    public static function Create(string $name, string $value, bool $isSecret = false): DeploymentSpecificationEnvironmentVariable {
        $item = new DeploymentSpecificationEnvironmentVariable();
        $item->name = $name;
        $item->value = $value;
        $item->is_secret = $isSecret;
        $item->save();
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DeploymentSpecificationEnvironmentVariable[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
