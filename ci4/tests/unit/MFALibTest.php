<?php namespace App\Tests\Unit;

use App\Libraries\MFALib;
use CodeIgniter\Test\CIUnitTestCase;
use RobThree\Auth\TwoFactorAuth;

/**
 * Time based one time passwords, and the name an authenticator app files them under.
 *
 * The QR code holds the secret itself, so it is drawn here and never fetched. It used to be
 * fetched from `api.qrserver.com`, with the secret in the query string - and this suite kept
 * away from it, because calling it sent one out.
 *
 * The rest of the class is local arithmetic - base32, an HMAC and a time slice.
 */
class MFALibTest extends CIUnitTestCase {

    // <editor-fold desc="The QR code">

    /**
     * An SVG drawn in the process, as a data uri the setup page can show as it is.
     */
    public function testTheQrCodeIsAnSvgDrawnHere(): void {
        $lib = new MFALib();

        $uri = $lib->getQRCodeImageAsDataUri($lib->createSecret());

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);
        $this->assertStringContainsString('<svg', base64_decode(substr($uri, strlen('data:image/svg+xml;base64,'))));
    }

    /**
     * And not by any of the library's providers that fetch the image from a web service -
     * every one of them puts the secret in a url.
     */
    public function testNoProviderThatFetchesTheImageIsUsed(): void {
        $inner = new \ReflectionProperty(MFALib::class, 'twoFactorAuth');
        $provider = new \ReflectionProperty(TwoFactorAuth::class, 'qrcodeprovider');

        $this->assertNotInstanceOf(
            \RobThree\Auth\Providers\Qr\BaseHTTPQRCodeProvider::class,
            $provider->getValue($inner->getValue(new MFALib()))
        );
    }

    // </editor-fold>

    // <editor-fold desc="Secrets and codes">

    /**
     * The secret an authenticator is set up with. It has to be base32 with the length the
     * library's own decoder expects, or the codes come out of a different secret than the
     * one the user scanned - and the failure is a rejected login, not an error.
     */
    public function testASecretIsBase32AndLongEnoughToBeOne(): void {
        $secret = (new MFALib())->createSecret();

        $this->assertSame(32, strlen($secret), '160 bits at five bits a character');
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret, 'base32 and nothing else');
    }

    public function testTwoSecretsAreNotTheSame(): void {
        $lib = new MFALib();

        $this->assertNotSame($lib->createSecret(), $lib->createSecret());
    }

    /**
     * `getSetupCode()` is the code the server shows during setup so the user can check
     * their app agrees, and `verifyCode()` is what a login runs. They have to be two views
     * of the same arithmetic, or setup succeeds and the first login fails.
     */
    public function testTheSetupCodeIsTheCodeThatVerifies(): void {
        $lib = new MFALib();
        $secret = $lib->createSecret();

        $code = $lib->getSetupCode($secret);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertTrue($lib->verifyCode($secret, $code));
    }

    public function testACodeFromAnotherSecretIsRefused(): void {
        $lib = new MFALib();
        $secret = $lib->createSecret();

        $this->assertFalse($lib->verifyCode($secret, $lib->getSetupCode($lib->createSecret())));
    }

    public function testNonsenseIsRefusedRatherThanThrown(): void {
        $lib = new MFALib();

        $this->assertFalse($lib->verifyCode($lib->createSecret(), '000000'));
        $this->assertFalse($lib->verifyCode($lib->createSecret(), ''));
    }

    // </editor-fold>

    // <editor-fold desc="The name the authenticator app shows">

    /**
     * The issuer is what an authenticator app lists the entry under, and a user with two
     * installations of kso has two entries that are otherwise identical. Getting it wrong
     * is not a security problem; it is a support call from someone reading six digits off
     * the wrong line.
     */
    public function testTheProjectNameIsAppendedWhenThereIsOne(): void {
        $this->withEnvironment(['PROJECT_NAME' => 'Acme', 'KUBERNETES_MY_NAMESPACE' => 'kso-prod'], function (): void {
            $this->assertSame('4 Spaces KSO | Acme', $this->issuerOf(new MFALib()));
        });
    }

    /**
     * With no project name, the namespace kso is deployed into is the next best thing to
     * tell two installations apart by - and in practice it is the customer's name.
     *
     * The guard is `getenv(...) && strlen(getenv(...))` and no test can tell the `strlen()`
     * half from its absence: `getenv()` answers `false` for a variable that is not set and
     * `''` for an empty one, and both are already falsy, so the first operand decides every
     * case. Dropping it is an equivalent change, not an untested one.
     */
    public function testTheNamespaceIsUsedWhenThereIsNoProjectName(): void {
        $this->withEnvironment(['PROJECT_NAME' => '', 'KUBERNETES_MY_NAMESPACE' => 'kso-prod'], function (): void {
            $this->assertSame('4 Spaces KSO | kso-prod', $this->issuerOf(new MFALib()));
        });
    }

    /**
     * `default` is what an unconfigured container reports, so it says nothing about which
     * installation this is and is left out rather than shown.
     */
    public function testTheDefaultNamespaceIsNotWorthShowing(): void {
        $this->withEnvironment(['PROJECT_NAME' => '', 'KUBERNETES_MY_NAMESPACE' => 'default'], function (): void {
            $this->assertSame('4 Spaces KSO', $this->issuerOf(new MFALib()));
        });
    }

    // </editor-fold>

    private function issuerOf(MFALib $lib): ?string {
        $inner = new \ReflectionProperty(MFALib::class, 'twoFactorAuth');

        $issuer = new \ReflectionProperty(TwoFactorAuth::class, 'issuer');

        return $issuer->getValue($inner->getValue($lib));
    }

    /**
     * Both variables are read with `getenv()`, and everything a test writes to the process
     * is written for every test after it - so they go back, including when the body throws.
     *
     * @param array<string, string> $values
     * @param callable(): void $body
     */
    private function withEnvironment(array $values, callable $body): void {
        $original = [];
        foreach ($values as $name => $value) {
            $original[$name] = getenv($name);
            putenv($name . '=' . $value);
        }

        try {
            $body();
        } finally {
            foreach ($original as $name => $value) {
                $value === false ? putenv($name) : putenv($name . '=' . $value);
            }
        }
    }

}
