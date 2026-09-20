<?php namespace App\Entities;

use App\Entities\Concerns\EncryptsFields;
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

    public const array EncryptedFields = ['mfa_secret_hash'];

    use EncryptsFields;

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

    /**
     * What to call this user on screen.
     *
     * The auth extension's own user entity has this, and `AuthExtension::checkSession()`
     * is typed for that one - but in kso the model hands back this entity instead, so
     * anything greeting the signed-in user was a fatal error. `Login::success()` was the
     * one page that did.
     */
    public function name(): string {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function hasMFASecret(): bool {
        return strlen((string) $this->mfa_secret_hash) > 0;
    }

    /**
     * The second factor, which is a secret and not a hash whatever the column is called: a
     * TOTP code is checked by computing it, so the value has to be recoverable.
     *
     * It was encrypted here with a passphrase written into the method -
     * `hex2bin('c17319112b52...')`, the same on every installation and in the repository -
     * which is encoding, not encryption. It now goes through the same key as every other
     * stored credential, so rotating that one rotates this too.
     */
    public function updateMFASecret(string $value): void {
        $this->mfa_secret_hash = $value;
        $this->save();
    }

    public function getMFASSecret(): string {
        return (string) $this->mfa_secret_hash;
    }

    public function removeMFASecret(): void {
        $this->mfa_secret_hash = null;
        $this->save();
    }

    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false, ?array $fieldsFilter = null): array {
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
