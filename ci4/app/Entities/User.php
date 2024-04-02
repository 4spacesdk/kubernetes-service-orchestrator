<?php namespace App\Entities;

use App\Libraries\EmailLib;
use App\Models\UserModel;

/**
 * Class User
 * @package App\Entities
 * @property string $username
 * @property string $first_name
 * @property string $last_name
 * @property string $password
 * @property string $scope
 * @property string $type
 * @property bool $renew_password
 *
 *  Many
 * @property RbacRole $rbac_roles
 */
class User extends \RestExtension\Entities\User {

    public static function post($data) {
        if (isset($data['username'])) {
            // Ensure unique username
            /** @var User $item */
            $item = (new UserModel())
                ->where('username', $data['username'])
                ->find();
            if ($item->exists()) {
                return $item;
            }
        }

        /** @var User $item */
        $item = parent::post($data);

        $item->postSave($data);
        return $item;
    }

    public static function patch($id, $data) {
        /** @var User $item */
        $item = parent::patch($id, $data);
        $item->postSave($data);
        return $item;
    }

    public static function put($id, $data) {
        /** @var User $item */
        $item = parent::put($id, $data);
        $item->postSave($data);
        return $item;
    }

    public function postSave(array $data) {
        if (isset($data['password']) && strlen($data['password']) >= 6) {
            $this->password = self::encryptPassword($data['password']);
            $this->save();
        }
    }

    public static function encryptPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    public function getTableFields() {
        return [
            'id', 'first_name', 'last_name', 'username', 'password', 'renew_password', 'scope', 'type',
        ];
    }

    public $hiddenFields = ['password'];

    public function getScopes(): array {
        return strlen($this->scope) ? explode(' ', $this->scope) : [];
    }

    private static function generatePassword(): string {
        return bin2hex(openssl_random_pseudo_bytes(4));
    }

    /**
     * @throws \Exception
     */
    public function sendForgotPasswordEmail(): void {
        $password = self::generatePassword();
        $this->password = $this->encryptPassword($password);
        $this->renew_password = true;
        $this->save();

        $emailLib = new EmailLib();
        $emailLib->send(
            '4 Spaces KSO | New password',
            "Your new password is \"{$password}\"",
            $this->first_name,
            $this->username
        );
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|User[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
