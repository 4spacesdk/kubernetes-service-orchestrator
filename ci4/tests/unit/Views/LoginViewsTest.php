<?php namespace App\Tests\Unit\Views;

use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The sign-in pages are where a password and a second-factor code are typed, so everything
 * they load comes from kso itself.
 *
 * They used to load jQuery, Popper and Bootstrap from three CDNs and Font Awesome Pro from a
 * fourth, and ran their own code as inline scripts. With the files served from `public/` and
 * the code in `assets/login/login.js`, a Content-Security-Policy of `script-src 'self'` fits
 * them as they are - and this keeps it that way.
 */
class LoginViewsTest extends CIUnitTestCase {

    #[DataProvider('theViews')]
    public function testNothingIsLoadedFromAnotherHost(string $view): void {
        preg_match_all('#<(?:script|link|img)\b[^>]*\b(?:src|href)="([^"]+)"#i', $this->source($view), $matches);

        foreach ($matches[1] as $url) {
            $this->assertDoesNotMatchRegularExpression('#^(https?:)?//#i', $url, "{$view} loads {$url}");
        }
    }

    #[DataProvider('theViews')]
    public function testThereIsNoInlineScript(string $view): void {
        $source = $this->source($view);

        $this->assertDoesNotMatchRegularExpression('#<script(?![^>]*\bsrc=)[^>]*>#i', $source, "{$view} has an inline <script>");
        $this->assertDoesNotMatchRegularExpression('#\son[a-z]+\s*=\s*["\']#i', $source, "{$view} has an inline event handler");
    }

    /**
     * The files the views name are there to be served.
     */
    public function testTheSelfHostedFilesExist(): void {
        foreach (['bootstrap-4.1.3.min.css', 'login.js', 'four-spaces-white.svg'] as $file) {
            $this->assertFileExists(FCPATH . 'assets/login/' . $file);
        }
    }

    public static function theViews(): array {
        return [
            'sign-in' => ['Login'],
            'second factor' => ['MFA'],
            'renewal' => ['PasswordRenewal'],
            'forgotten password' => ['ForgotPassword'],
        ];
    }

    private function source(string $view): string {
        return (string) file_get_contents(APPPATH . "Views/Login/{$view}.php");
    }

}
