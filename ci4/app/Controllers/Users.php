<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Helpers\Client;
use App\Libraries\MFALib;
use DebugTool\Data;

class Users extends ResourceController {

    /**
     * @route /users/me
     * @method get
     * @custom true
     */
    public function me(): void {
        $me = Client::$user;
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
        $hasMFA = Client::$user->hasMFASecret();

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
     */
    public function mfaSetupVerify(): void {
        $code = $this->request->getGet('code');
        $mfaSecret = session()->get('mfa_secret');
        Data::debug($code, $mfaSecret);
        $mfaLib = new MFALib();
        $result = $mfaLib->verifyCode($mfaSecret, $code);

        if ($result) {
            Client::$user->updateMFASecret($mfaSecret);
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
     */
    public function mfaSetupRemove(): void {
        Client::$user->removeMFASecret();
        $this->success();
    }

}
