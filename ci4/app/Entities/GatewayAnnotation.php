<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class GatewayAnnotation
 * @package App\Entities
 * @property int $gateway_id
 * @property Gateway $gateway
 * @property string $name
 * @property string $value
 */
class GatewayAnnotation extends Entity {

    /**
     * Set by kso on everything it applies, so it is not the user's to change.
     */
    public const ManagedBy = 'app.kubernetes.io/managed-by';

    /**
     * Refused here rather than by the api server, which would refuse the whole Gateway on
     * the next deploy with a message about the manifest, not about the field.
     *
     * A key is an optional prefix - a DNS subdomain, then a slash - and a name of at most 63
     * characters that starts and ends with a letter or digit.
     */
    public function validate(): ?string {
        $name = (string) $this->name;
        if ($name === '') {
            return 'Missing name';
        }
        if ($name === self::ManagedBy) {
            return "'" . self::ManagedBy . "' is set by kso";
        }

        $prefix = null;
        if (str_contains($name, '/')) {
            [$prefix, $name] = explode('/', $name, 2);
        }
        if ($prefix !== null && (strlen($prefix) > 253 || !preg_match('/^[a-z0-9]([-a-z0-9]*[a-z0-9])?(\.[a-z0-9]([-a-z0-9]*[a-z0-9])?)*$/', $prefix))) {
            return "Invalid prefix '{$prefix}': it must be a lowercase DNS subdomain";
        }
        if (strlen($name) > 63 || !preg_match('/^[A-Za-z0-9]([-A-Za-z0-9_.]*[A-Za-z0-9])?$/', $name)) {
            return "Invalid name '{$name}': at most 63 letters, digits, '-', '_' or '.', starting and ending with a letter or digit";
        }

        return null;
    }

}
