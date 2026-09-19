<?php namespace App\Entities;

use App\Core\Entity;
use App\Entities\GatewayAddress;
use App\Models\GatewayAnnotationModel;

/**
 * Class Gateway
 * @package App\Entities
 * @property string $name
 * @property string $gateway_class_name
 * @property string $namespace
 *
 * Many
 * @property Domain $domains
 * @property GatewayAddress $gateway_addresses
 * @property GatewayAnnotation $gateway_annotations
 */
class Gateway extends Entity {

    public function updateGatewayAddresses(GatewayAddress $values): void {
        $this->gateway_addresses->find()->deleteAll();
        $this->save($values);
        $this->gateway_addresses = $values;
    }

    public function updateGatewayAnnotations(GatewayAnnotation $values): void {
        $this->gateway_annotations->find()->deleteAll();
        $this->save($values);
        $this->gateway_annotations = $values;
    }

    /**
     * The annotations the Gateway resource carries, as name => value, before kso adds its own.
     *
     * @return array<string, string>
     */
    public function getAnnotations(): array {
        /** @var GatewayAnnotation $annotations */
        $annotations = (new GatewayAnnotationModel())
            ->where('gateway_id', $this->id)
            ->find();

        $values = [];
        foreach ($annotations as $annotation) {
            $values[$annotation->name] = (string) $annotation->value;
        }
        return $values;
    }

}
