<?php namespace App\Entities;

use App\Interfaces\PodioIntegrationGetFieldsResponse;
use DebugTool\Data;
use App\Core\Entity;

/**
 * Class PodioIntegration
 * @package App\Entities
 * @property string $name
 * @property string $client_id
 * @property string $client_secret
 * @property string $app_id
 * @property string $app_token
 *
 * Many
 * @property PodioFieldReference $podio_field_references
 */
class PodioIntegration extends Entity {

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
