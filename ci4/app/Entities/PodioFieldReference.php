<?php namespace App\Entities;

use App\Models\PodioFieldReferenceModel;
use DebugTool\Data;
use App\Core\Entity;

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

    /**
     * The value of this field on the item the url points at.
     *
     * The url is a Podio task link; everything after `items/` is the id. A url without
     * that segment is not handled - see FEAT-11.
     */
    public function getFieldValue(string $url): ?string {
        if (!$this->podio_integration->exists()) {
            $this->podio_integration->find();
        }

        [$_, $itemId] = explode('items/', $url);

        return service('integrations')->podio()->fieldValue($this->podio_integration, $this->field_id, $itemId);
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|PodioFieldReference[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
