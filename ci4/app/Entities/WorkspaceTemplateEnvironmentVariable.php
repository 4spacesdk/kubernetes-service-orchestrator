<?php namespace App\Entities;

use App\Core\Entity;
use App\Entities\Concerns\SecretEnvironmentVariable;

/**
 * Class WorkspaceTemplateEnvironmentVariable
 * @package App\Entities
 * @property int $workspace_template_id
 * @property WorkspaceTemplate $workspace_template
 * @property string $name
 * @property string $value
 * @property bool $is_secret the value is write-only when set, see SecretEnvironmentVariable
 * @property bool $has_value
 */
class WorkspaceTemplateEnvironmentVariable extends Entity {

    use SecretEnvironmentVariable;

    public static function Create(string $name, string $value, bool $isSecret = false): WorkspaceTemplateEnvironmentVariable {
        $item = new WorkspaceTemplateEnvironmentVariable();
        $item->name = $name;
        $item->value = $value;
        $item->is_secret = $isSecret;
        $item->save();
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|WorkspaceTemplateEnvironmentVariable[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
