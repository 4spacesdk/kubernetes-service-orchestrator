<?php namespace App\Entities;

use App\Interfaces\PodioIntegrationGetFieldsResponse;
use DebugTool\Data;
use App\Core\Entity;
use App\Entities\Concerns\EncryptsFields;
use App\Entities\Concerns\WriteOnlySecrets;

/**
 * Class PodioIntegration
 * @package App\Entities
 * @property string $name
 * @property string $client_id
 * @property string $client_secret
 * @property string $app_id
 * @property string $app_token write-only, see WriteOnlySecrets
 * @property bool $has_client_secret
 * @property bool $has_app_token
 *
 * Many
 * @property PodioFieldReference $podio_field_references
 */
class PodioIntegration extends Entity {

    public const array EncryptedFields = self::SecretFields;

    use EncryptsFields;

    public const array SecretFields = ['client_secret', 'app_token'];

    use WriteOnlySecrets;

    public $hiddenFields = self::SecretFields;

    public static function patch($id, $data) {
        return parent::patch($id, self::keepStoredSecrets($data));
    }

    /**
     * @return PodioIntegrationGetFieldsResponse[]
     */
    public function getFields(): array {
        return service('integrations')->podio()->fields($this);
    }

    public function getFieldDetails(string $fieldId): array {
        return service('integrations')->podio()->fieldDetails($this, $fieldId);
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|PodioIntegration[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
