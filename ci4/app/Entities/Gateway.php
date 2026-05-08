<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class Gateway
 * @package App\Entities
 * @property string $name
 * @property string $gateway_class_name
 * @property string $namespace
 *
 * Many
 * @property Domain $domains
 */
class Gateway extends Entity {

}
