<?php namespace App\Libraries;

use App\Libraries\Kubernetes\KubeHelper;
use RobThree\Auth\Providers\Qr\QRServerProvider;
use RobThree\Auth\TwoFactorAuth;

class MFALib {

    private TwoFactorAuth $twoFactorAuth;

    public function __construct() {
        $name = '4 Spaces KSO';
        if (getenv('PROJECT_NAME') && strlen(getenv('PROJECT_NAME'))) {
            $name .= ' | ' . getenv('PROJECT_NAME');
        } else if (KubeHelper::GetMyNamespace() != 'default') {
            $name .= ' | ' . KubeHelper::GetMyNamespace();
        }
        $this->twoFactorAuth = new TwoFactorAuth(new QRServerProvider(), $name);
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
