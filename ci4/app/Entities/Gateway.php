<?php namespace App\Entities;

use App\Core\Entity;
use App\Entities\GatewayAddress;

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
 */
class Gateway extends Entity {

    public function updateGatewayAddresses(GatewayAddress $values): void {
        $this->gateway_addresses->find()->deleteAll();
        $this->save($values);
        $this->gateway_addresses = $values;
    }

}
