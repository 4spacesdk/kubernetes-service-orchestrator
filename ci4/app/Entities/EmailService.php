<?php namespace App\Entities;

use App\Core\Entity;
use App\Entities\Concerns\EncryptsFields;
use App\Entities\Concerns\WriteOnlySecrets;

/**
 * Class EmailService
 * @package App\Entities
 * @property string $name
 * @property string $host
 * @property int $port
 * @property string $user
 * @property string $pass write-only, see WriteOnlySecrets
 * @property bool $has_pass
 * @property string $from
 *
 * Many
 * @property Workspace $workspaces
 * @property Deployment $deployments
 */
class EmailService extends Entity {

    public const array EncryptedFields = self::SecretFields;

    use EncryptsFields;

    public const array SecretFields = ['pass'];

    use WriteOnlySecrets;

    public $hiddenFields = self::SecretFields;

    public static function patch($id, $data) {
        return parent::patch($id, self::keepStoredSecrets($data));
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|EmailService[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
