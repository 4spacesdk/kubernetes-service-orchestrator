<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class GatewayAddress
 * @package App\Entities
 * @property int $gateway_id
 * @property string $type
 * @property string $value
 */
class GatewayAddress extends Entity {

    public static function Create(string $type, string $value): GatewayAddress {
        $item = new GatewayAddress();
        $item->type = $type;
        $item->value = $value;
        $item->save();
        return $item;
    }

    public function validate(): ?string {
        if (empty($this->type)) {
            return 'Missing type';
        }
        if (empty($this->value)) {
            return 'Missing value';
        }
        if (strlen($this->type) > 253) {
            return 'Type too long';
        }
        if (strlen($this->value) > 253) {
            return 'Value too long';
        }

        $pattern = '#^(Hostname|IPAddress|NamedAddress|([a-z0-9]+(\.[a-z0-9]+)*/)?([A-Za-z0-9/\-._~%!$&\'()+,;=:]+))$#';
        if (!preg_match($pattern, $this->type)) {
            return 'Invalid type';
        }

        return null;
    }

}
