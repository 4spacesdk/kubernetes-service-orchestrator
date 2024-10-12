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
 * @property string $mfa_secret_hash
 *
 *  Many
 * @property RbacRole $rbac_roles
 *
 * OTF
 * @property bool $has_mfa_secret_hash
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

    public $hiddenFields = ['password', 'mfa_secret_hash'];

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

    public function hasMFASecret(): bool {
        return strlen($this->mfa_secret_hash) > 0;
    }

    public function updateMFASecret(string $value): void {
        $cipher = 'AES-256-CBC';
        $passPhrase = hex2bin('c17319112b52d6b472136d7938121233783d723814746a67c81a451c4d238d18');
        $nonceSize = openssl_cipher_iv_length($cipher);
        $nonce = openssl_random_pseudo_bytes($nonceSize);

        $ciphertext = openssl_encrypt(
            $value,
            $cipher,
            $passPhrase,
            OPENSSL_RAW_DATA,
            $nonce
        );

        $this->mfa_secret_hash = base64_encode($nonce . $ciphertext);
        $this->save();
    }

    /**
     * @throws \Exception
     */
    public function getMFASSecret(): string {
        $cipher = 'AES-256-CBC';
        $passPhrase = hex2bin('c17319112b52d6b472136d7938121233783d723814746a67c81a451c4d238d18');

        $message = base64_decode($this->mfa_secret_hash, true);
        if ($message === false) {
            throw new \Exception('Encryption failure');
        }

        $nonceSize = openssl_cipher_iv_length($cipher);
        $nonce = mb_substr($message, 0, $nonceSize, '8bit');
        $ciphertext = mb_substr($message, $nonceSize, null, '8bit');

        $plaintext = openssl_decrypt(
            $ciphertext,
            $cipher,
            $passPhrase,
            OPENSSL_RAW_DATA,
            $nonce
        );

        return $plaintext;
    }

    public function removeMFASecret(): void {
        $this->mfa_secret_hash = null;
        $this->save();
    }

    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false, array $fieldsFilter = null): array {
        $item = parent::toArray($onlyChanged, $cast, $recursive, $fieldsFilter);
        $item['has_mfa_secret_hash'] = $this->hasMFASecret();
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|User[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
