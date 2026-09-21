<?php namespace App\Entities;

use App\Core\Entity;
use App\Entities\Concerns\SecretEnvironmentVariable;

/**
 * Class InitContainerEnvironmentVariable
 * @package App\Entities
 * @property int $init_container_id
 * @property InitContainer $init_container
 * @property string $name
 * @property string $value
 * @property bool $is_secret the value is write-only when set, see SecretEnvironmentVariable
 * @property bool $has_value
 */
class InitContainerEnvironmentVariable extends Entity {

    use SecretEnvironmentVariable;

    public static function Create(string $name, string $value, bool $isSecret = false): InitContainerEnvironmentVariable {
        $item = new InitContainerEnvironmentVariable();
        $item->name = $name;
        $item->value = $value;
        $item->is_secret = $isSecret;
        $item->save();
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|InitContainerEnvironmentVariable[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
