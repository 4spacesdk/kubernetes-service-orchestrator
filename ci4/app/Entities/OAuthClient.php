<?php namespace App\Entities;

use App\Models\OAuthClientModel;
use RestExtension\Core\Entity;

/**
 * Class OAuthClient
 * @package App\Entities
 * @property string $client_id
 * @property string $client_secret
 * @property string $redirect_uri
 * @property string $grant_types
 * @property string $scope
 * @property string $user_id
 * @property User $user
 */
class OAuthClient extends Entity {

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
        $saveClientId = $item->client_id;
        $item->insert();
        $item->client_id = $saveClientId;
        return $item;
    }

    /**
     * @return \ArrayIterator|Entity[]|\Traversable|CronJob[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
