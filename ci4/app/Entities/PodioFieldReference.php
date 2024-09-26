<?php namespace App\Entities;

use App\Models\PodioFieldReferenceModel;
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

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|PodioFieldReference[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
