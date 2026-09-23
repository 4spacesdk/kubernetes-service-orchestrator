<?php namespace App\Libraries;

use App\Libraries\Kubernetes\KubeHelper;
use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;

class MFALib {

    private TwoFactorAuth $twoFactorAuth;

    public function __construct() {
        // What the authenticator app lists the entry under: the installation, as the mails
        // name it - or, without a project name, the namespace kso runs in, which is usually
        // the customer's name.
        $name = 'KSO';
        if (getenv('PROJECT_NAME') && strlen(getenv('PROJECT_NAME'))) {
            $name = getenv('PROJECT_NAME');
        } else if (KubeHelper::GetMyNamespace() != 'default') {
            $name = KubeHelper::GetMyNamespace();
        }
        // Drawn here, as SVG, which needs no image extension. The QR code holds the secret
        // itself - it is the second factor - and it used to be fetched from api.qrserver.com
        // with the secret in the url, into a free public service's access logs.
        $this->twoFactorAuth = new TwoFactorAuth(new BaconQrCodeProvider(format: 'svg'), $name);
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
