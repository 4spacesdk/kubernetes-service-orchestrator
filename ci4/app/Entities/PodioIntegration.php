<?php namespace App\Entities;

use App\Interfaces\PodioIntegrationGetFieldsResponse;
use DebugTool\Data;
use RestExtension\Core\Entity;

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
        $client = new \PodioClient($this->client_id, $this->client_secret);
        $client->authenticate_with_app($this->app_id, $this->app_token);
        $app = \PodioApp::get($client, $this->app_id);
        $fields = [];
        foreach ($app->fields as $field) {
            Data::debug($field->config);
            $fields[] = [
                'id' => (string)$field->id,
                'name' => $field->label,
                'type' => $field->type,
            ];
        }
        return $fields;
    }

    public function getFieldDetails(string $fieldId): array {
        $client = new \PodioClient($this->client_id, $this->client_secret);
        $client->authenticate_with_app($this->app_id, $this->app_token);
        $appField = \PodioAppField::get($client, $this->app_id, $fieldId);

        return [
            'id' => (string)$appField->id,
            'name' => $appField->name,
            'type' => $appField->type,
            'options' => array_map(
                fn($option) => [
                    'id' => (string)$option['id'],
                    'text' => (string)$option['text'],
                    'color' => (string)$option['color'],
                ],
                array_values(array_filter(
                    $appField->config['settings']['options'] ?? [],
                    fn($option) => $option['status'] == 'active'
                ))
            )
        ];
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|PodioIntegration[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
