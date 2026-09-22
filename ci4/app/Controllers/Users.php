<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Exceptions\ValidationException;
use App\Entities\User;
use App\Helpers\Client;
use App\Models\UserModel;
use App\Libraries\MFALib;
use DebugTool\Data;

class Users extends ResourceController {

    /**
     * No blanket replace. The generic `put()` writes every column of the row, and the ones a
     * request leaves out become null - on this resource that is the user's password and MFA secret. kso updates
     * with PATCH everywhere, and nothing ever called this; it existed only because the route
     * generator finds the inherited method.
     *
     * @ignore true
     * @param $id
     * @return void
     */
    public function put($id = 0) {
    }

    /**
     * A password that breaks a rule is refused with the rule, rather than as a server error.
     * @audit entity
     */
    public function post() {
        try {
            parent::post();
        } catch (ValidationException $e) {
            $this->fail($e->getMessage());
            return;
        }
    }

    /**
     * @audit entity
     */
    public function patch($id = 0) {
        try {
            parent::patch($id);
        } catch (ValidationException $e) {
            $this->fail($e->getMessage());
            return;
        }
    }

    /**
     * @route /users/me
     * @method get
     * @custom true
     */
    public function me(): void {
        $me = $this->signedInUser();
        $me->rbac_roles->find();
        foreach ($me->rbac_roles as $role) {
            $role->rbac_permissions->find();
        }
        $this->_setResource($me);
        $this->success();
    }

    /**
     * @route /users/mfa/setup/prepare
     * @method get
     * @custom true
     * @responseSchema UsersMFASetupPrepareResponse
     */
    public function mfaSetupPrepare(): void {
        $hasMFA = $this->signedInUser()->hasMFASecret();

        if ($hasMFA) {
            Data::set('resource', [
                'hasMFA' => true,
            ]);
        } else {
            $mfaLib = new MFALib();
            $secret = $mfaLib->createSecret();
            session()->set('mfa_secret', $secret);
            Data::set('resource', [
                'hasMFA' => false,
                'qrCodeDataUri' => $mfaLib->getQRCodeImageAsDataUri($secret),
                'setupCode' => $mfaLib->getSetupCode($secret),
            ]);
        }

        $this->success();
    }

    /**
     * @route /users/mfa/setup/verify
     * @method put
     * @custom true
     * @parameter string $code parameterType=query
     * @responseSchema BoolInterface
     * @audit entity
     */
    public function mfaSetupVerify(): void {
        $code = (string) $this->request->getGet('code');
        $mfaSecret = session()->get('mfa_secret');
        $user = $this->signedInUser();

        // Replacing a second factor goes through turning it off. Verifying a new one on top
        // used to overwrite the one the user was signing in with.
        if ($user->hasMFASecret()) {
            $this->fail('Two-factor authentication is already on. Turn it off first to set up another.');
            return;
        }
        if (!is_string($mfaSecret) || $mfaSecret === '') {
            $this->fail('No two-factor setup is in progress');
            return;
        }

        $result = (new MFALib())->verifyCode($mfaSecret, $code);

        if ($result) {
            $user->updateMFASecret($mfaSecret);
            session()->remove('mfa_secret');
        }

        Data::set('resource', [
            'value' => $result,
        ]);
        $this->success();
    }

    /**
     * @route /users/mfa/setup/remove
     * @method put
     * @custom true
     * @audit entity
     */
    public function mfaSetupRemove(): void {
        $this->signedInUser()->removeMFASecret();
        $this->success();
    }

    /**
     * The signed-in user as stored, not as the token describes them.
     *
     * `Client::$user` is built from the token's `user_data`, which is the user's `toArray()` -
     * with the hidden fields stripped. It has no second factor whatever is stored, so
     * `has_mfa_secret_hash` was false for everybody, and setting up two-factor authentication
     * again handed a user who had it a fresh secret to scan.
     */
    private function signedInUser(): User {
        /** @var User $user */
        $user = (new UserModel())->find(Client::$user->id);

        return $user;
    }

}
