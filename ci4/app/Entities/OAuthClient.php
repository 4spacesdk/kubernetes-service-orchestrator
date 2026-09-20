<?php namespace App\Entities;

use App\Models\OAuthClientModel;
use App\Core\Entity;
use App\Entities\Concerns\WriteOnlySecrets;

/**
 * Class OAuthClient
 * @package App\Entities
 * @property string $client_id
 * @property string $client_secret write-only, see WriteOnlySecrets
 * @property bool $has_client_secret
 * @property string $redirect_uri
 * @property string $grant_types
 * @property string $scope
 * @property string $user_id
 * @property User $user
 */
class OAuthClient extends Entity {

    /**
     * The secret is chosen by whoever creates the client - the dialog has a field for it -
     * so withholding it costs nobody the value they set. It was listed in full in the
     * clients table, and searchable, which is a way to confirm a guess one character at a
     * time.
     */
    public const array SecretFields = ['client_secret'];

    use WriteOnlySecrets;

    public $hiddenFields = self::SecretFields;

    public static function patch($id, $data) {
        return parent::patch($id, self::keepStoredSecrets($data));
    }

    public static function post($data) {
        if (isset($data['user_id'])) {
            $userId = $data['user_id'];
        }
        if (isset($data['user'])) {
            $user = User::post($data['user']);
            $userId = $user->id;
        }

        // Ensure unique portal oauth clients
        if (isset($userId) && isset($data['grant_types'])) {
            /** @var OAuthClient $item */
            $item = (new OAuthClientModel())
                ->where('user_id', $userId)
                ->where('grant_types', $data['grant_types'])
                ->find();
            if ($item->exists()) {
                return $item;
            }
        }

        $item = new OAuthClient();
        $item->client_id = $data['client_id'] ?? bin2hex(random_bytes(16));
        $item->client_secret = $data['client_secret'] ?? bin2hex(random_bytes(16));
        $item->grant_types = $data['grant_types'] ?? '';
        $item->redirect_uri = $data['redirect_uri'] ?? '';
        if (isset($userId)) {
            $item->user_id = $userId;
        }
        // No saving and restoring of `client_id` around the insert. That was guarding
        // against `EntityTrait::insert()` overwriting the primary key with the id the model
        // handed back - and it cannot: the model calls CodeIgniter's insert with
        // `$returnID = false`, so it always answers a bool, and the overwrite is behind
        // `if (!is_bool($result))`.
        $item->insert();

        return $item;
    }

    /**
     * @return \ArrayIterator|Entity[]|\Traversable|CronJob[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
