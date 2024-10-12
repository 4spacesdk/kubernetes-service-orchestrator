<?php namespace App\Libraries;

use RobThree\Auth\Providers\Qr\QRServerProvider;
use RobThree\Auth\TwoFactorAuth;

class MFALib {

    private TwoFactorAuth $twoFactorAuth;

    public function __construct() {
        $this->twoFactorAuth = new TwoFactorAuth(new QRServerProvider(), '4 Spaces KSO');
    }

    public function createSecret(): string {
        return $this->twoFactorAuth->createSecret();
    }

    public function getQRCodeImageAsDataUri(string $secret): string {
        return $this->twoFactorAuth->getQRCodeImageAsDataUri('Secret', $secret);
    }

    public function getSetupCode(string $secret): string {
        return $this->twoFactorAuth->getCode($secret);
    }

    public function verifyCode(string $secret, string $code): bool {
        return $this->twoFactorAuth->verifyCode($secret, $code);
    }

}
