<?php namespace App\Entities;

use App\Entities\Concerns\EncryptsFields;
use App\Exceptions\ValidationException;
use App\Libraries\EmailLib;
use App\Models\ProjectModel;
use App\Models\UserModel;
use AuthExtension\AuthExtension;
use Config\Database;

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
 * @property string $password_reset_token_hash
 * @property string $password_reset_expires
 *
 *  Many
 * @property RbacRole $rbac_roles
 * @property Project $projects the projects relevant to the user, see Project
 *
 * OTF
 * @property bool $has_mfa_secret_hash
 */
class User extends \RestExtension\Entities\User {

    public static function post($data) {
        $data = self::withThePasswordHashed($data);

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

        return parent::post($data);
    }

    /**
     * The projects relevant to the user are these, and only these. One link at a time, as
     * RbacRole::updatePermissions() explains; an id that is no project is left out.
     *
     * @param int[] $projectIds
     */
    public function updateProjects(array $projectIds): void {
        /** @var Project $existing */
        $existing = (new ProjectModel())->whereRelated(UserModel::class, 'id', $this->id)->find();
        foreach ($existing as $project) {
            $this->delete($project);
        }

        foreach (array_unique(array_map('intval', $projectIds)) as $projectId) {
            $project = new Project();
            $project->find($projectId);
            if ($project->exists()) {
                $this->save($project);
            }
        }

        $this->projects = (new ProjectModel())->whereRelated(UserModel::class, 'id', $this->id)->find();
    }

    public static function patch($id, $data) {
        $result = parent::patch($id, self::withThePasswordHashed($data));
        self::EndEverySignInIfThePasswordChanged($id, $data);
        return $result;
    }

    public static function put($id, $data) {
        $result = parent::put($id, self::withThePasswordHashed($data));
        self::EndEverySignInIfThePasswordChanged($id, $data);
        return $result;
    }

    private static function EndEverySignInIfThePasswordChanged($id, mixed $data): void {
        if (is_array($data) && (string) ($data['password'] ?? '') !== '') {
            self::EndEverySignIn((int) $id, self::CurrentSessionRow());
        }
    }

    /**
     * Every way a user is signed in, ended: their access and refresh tokens and authorization
     * codes revoked, and their sessions - the ones `/authorize` mints new tokens from without a
     * password - removed. For a password that changed: whoever knew the old one, and signed in
     * with it, is out.
     *
     * A reset or a renewal used to leave all of it in place, so a stolen session or refresh
     * token outlived the password it was got with.
     *
     * @param string|null $keepSessionRow the session that changed the password, which stays
     */
    public static function EndEverySignIn(int $userId, ?string $keepSessionRow = null): void {
        AuthExtension::revokeUserTokens($userId);

        // The session handler stores PHP's own serialisation: `user_id|s:1:"7";`, or `|i:7;`.
        $builder = Database::connect()->table(config('Session')->savePath)
            ->groupStart()
                ->like('data', 'user_id|s:' . strlen((string) $userId) . ':"' . $userId . '";')
                ->orLike('data', 'user_id|i:' . $userId . ';')
            ->groupEnd();
        if ($keepSessionRow !== null) {
            $builder->where('id !=', $keepSessionRow);
        }
        $builder->delete();
    }

    /**
     * The row of the session this request runs in, if it has one - what `EndEverySignIn()` is
     * told to keep.
     */
    public static function CurrentSessionRow(): ?string {
        if (session_status() !== PHP_SESSION_ACTIVE || session_id() === '') {
            return null;
        }
        return config('Session')->cookieName . ':' . session_id();
    }

    /**
     * The password as it is to be written: hashed, before anything reaches the table.
     *
     * It used to be written as sent and hashed afterwards, and only when it was at least six
     * characters - so a shorter one set through the API stayed in the column in plain text,
     * and every other one was there in plain text until the second save. It is now held to
     * the same rules as a renewal and refused if it breaks one.
     *
     * An empty password means "unchanged": the form sends one only when it is filled in.
     *
     * @throws ValidationException naming the first rule the password breaks
     */
    private static function withThePasswordHashed(mixed $data): mixed {
        if (!is_array($data) || !array_key_exists('password', $data)) {
            return $data;
        }

        $password = (string) $data['password'];
        if ($password === '') {
            unset($data['password']);
            return $data;
        }

        $broken = self::FirstUnsatisfiedPasswordRule($password);
        if ($broken !== null) {
            throw new ValidationException("Password: {$broken}");
        }

        $data['password'] = self::encryptPassword($password);
        return $data;
    }

    /**
     * The first rule a new password does not satisfy, or null when it satisfies all four.
     *
     * First rather than last. The four checks used to write to one variable without
     * stopping, so the message named whichever rule was checked last - and "At least one
     * letter" could never be it, because anything without a letter has no capital either
     * and the capital was checked afterwards.
     *
     * The order is from the most basic complaint to the most specific, so that a password
     * failing several rules is told the one worth fixing first.
     */
    public static function FirstUnsatisfiedPasswordRule(string $password): ?string {
        if (strlen($password) < 8) {
            return 'At least eight characters';
        }
        if (!preg_match("#[a-zA-Z]+#", $password)) {
            return 'At least one letter';
        }
        if (!preg_match("#[0-9]+#", $password)) {
            return 'At least one number';
        }
        if (!preg_match("#[A-Z]+#", $password)) {
            return 'At least one uppercase letter';
        }

        return null;
    }

    public static function encryptPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    public function getTableFields() {
        return [
            'id', 'first_name', 'last_name', 'username', 'password', 'renew_password', 'scope', 'type',
            'password_reset_token_hash', 'password_reset_expires',
        ];
    }

    public const array EncryptedFields = ['mfa_secret_hash'];

    use EncryptsFields;

    use \App\Entities\Concerns\Audited;

    public $hiddenFields = ['password', 'mfa_secret_hash', 'password_reset_token_hash', 'password_reset_expires'];

    public function getScopes(): array {
        return strlen($this->scope) ? explode(' ', $this->scope) : [];
    }

    /**
     * How long a link to choose a new password works.
     */
    public const int PasswordResetMinutes = 60;

    /**
     * Mails a link to choose a new password. The password itself is not touched: that
     * happens only when the link is followed, so asking for one on someone else's behalf
     * changes nothing for them.
     *
     * A new request replaces the token of the last one.
     *
     * @throws \Exception when the installation cannot send mail
     */
    public function sendPasswordResetEmail(): void {
        $token = bin2hex(random_bytes(32));
        $this->password_reset_token_hash = hash('sha256', $token);
        $this->password_reset_expires = date('Y-m-d H:i:s', time() + self::PasswordResetMinutes * 60);
        $this->save();

        // From BASE_URL, not from the request: someone asking on an operator's behalf with a
        // `Host` of their own would otherwise have the link point at their site. See Config\App.
        $link = base_url('login/resetPassword') . '?token=' . $token;
        $minutes = self::PasswordResetMinutes;
        (new EmailLib())->send(
            EmailLib::Subject('Choose a new password'),
            "Follow this link to choose a new password: <a href=\"{$link}\">{$link}</a><br><br>"
            . "It works once, for {$minutes} minutes. If you did not ask for it, ignore this e-mail - your password is unchanged.",
            $this->first_name,
            $this->username
        );
    }

    /**
     * The user a link to choose a new password was sent to, while it still works.
     */
    public static function FindByPasswordResetToken(string $token): ?User {
        if ($token === '') {
            return null;
        }

        /** @var User $user */
        $user = (new UserModel())
            ->where('password_reset_token_hash', hash('sha256', $token))
            ->where('password_reset_expires >', date('Y-m-d H:i:s'))
            ->find();

        return $user->exists() ? $user : null;
    }

    /**
     * Sets the password chosen through the link and spends the token.
     */
    public function resetPassword(string $password): void {
        $this->password = self::encryptPassword($password);
        $this->renew_password = false;
        $this->password_reset_token_hash = null;
        $this->password_reset_expires = null;
        $this->save();

        // Through the link, so nobody is signed in on this browser: every sign-in ends.
        self::EndEverySignIn((int) $this->id);
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
