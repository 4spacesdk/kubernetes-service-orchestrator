<?php namespace App\Entities;

use App\Models\PodioFieldReferenceModel;
use DebugTool\Data;
use RestExtension\Core\Entity;

/**
 * Class PodioFieldReference
 * @package App\Entities
 * @property int $podio_integration_id
 * @property PodioIntegration $podio_integration
 * @property string $field_id
 */
class PodioFieldReference extends Entity {

    public static function Create(int $integrationId, string $fieldId): PodioFieldReference {
        /** @var PodioFieldReference $item */
        $item = (new PodioFieldReferenceModel())
            ->where('podio_integration_id', $integrationId)
            ->where('field_id', $fieldId)
            ->find();
        if (!$item->exists()) {
            $item->podio_integration_id = $integrationId;
            $item->field_id = $fieldId;
            $item->save();
        }
        return $item;
    }

    public function getFieldValue(string $url): ?string {
        if (!$this->podio_integration->exists()) {
            $this->podio_integration->find();
        }

        [$_, $itemId] = explode('items/', $url);
        try {
            $client = new \PodioClient($this->podio_integration->client_id, $this->podio_integration->client_secret);
            $client->authenticate_with_app($this->podio_integration->app_id, $this->podio_integration->app_token);
            $podioItem = \PodioItem::get_by_app_item_id($client, $this->podio_integration->app_id, $itemId);
            foreach ($podioItem->fields as &$field) {
                if ($field->field_id == $this->field_id) {
                    return is_array($field->values) ? (count($field->values) ? $field->values[0]['id'] : '') : $field->values;
                }
            }
        } catch (\Exception $e) {
            Data::debug(get_class($this), $e);
        }
        return null;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|PodioFieldReference[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
