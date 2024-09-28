<?php namespace App\Controllers;

use CodeIgniter\Cookie\Cookie;
use CodeIgniter\Cookie\CookieInterface;
use DateTime;
use DebugTool\Data;

class OAuthAgent extends \App\Core\BaseController {

    public function requireAuth(string $method): bool {
        return false;
    }

    private function getBaseUrl($relativePath = '', ?string $scheme = null): string {
        return str_replace(':8950', '', base_url($relativePath, $scheme));
    }

    public function token(): void {
        $grantType = $this->request->getPost('grant_type');
        $code = $this->request->getPost('code');
        $redirectUri = $this->request->getPost('redirect_uri');
        $codeVerifier = $this->request->getPost('code_verifier');
        $clientId = $this->request->getPost('client_id');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->getBaseUrl('token'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'grant_type' => $grantType,
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'code_verifier' => $codeVerifier,
            'client_id' => $clientId,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $raw = curl_exec($ch);
        $response = json_decode($raw, true);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Error handling
        if (is_null($response) || !isset($response['access_token'])) {
            Data::debug($curlError);
            Data::debug($raw);
            $this->fail('failed to fetch access token', 400);
            return;
        }

        $accessToken = $response['access_token'];
        $expiresIn = $response['expires_in'];
        $idToken = $response['id_token'];
        $refreshToken = $response['refresh_token'];
        $scope = $response['scope'];
        $tokenType = $response['token_type'];

        $this->setCookieAttribute('refreshToken', $refreshToken);

        $this->response->setJSON([
            'access_token' => $accessToken,
            'expires_in' => $expiresIn,
            'id_token' => $idToken,
            'scope' => $scope,
            'token_type' => $tokenType,
        ]);
        $this->response->send();
    }

    public function refresh(): void {
        $clientId = $this->request->getPost('client_id');
        $grantType = $this->request->getPost('grant_type');
        $scope = $this->request->getPost('scope');

        $refreshToken = $this->getCookieAttribute('refreshToken');
        if (!$refreshToken) {
            $this->fail('refresh token not found');
            return;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $this->getBaseUrl('token'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'client_id' => $clientId,
            'grant_type' => $grantType,
            'refresh_token' => $refreshToken,
            'scope' => $scope,
        ]);

        $raw = curl_exec($ch);
        $response = json_decode($raw, true);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Error handling
        if (is_null($response) || !isset($response['access_token'])) {
            Data::debug($curlError);
            Data::debug($response ?? $raw);
            $this->fail('failed to fetch access token', 400);
            return;
        }

        $accessToken = $response['access_token'];
        $expiresIn = $response['expires_in'];
        $refreshToken = $response['refresh_token'];
        $scope = $response['scope'];
        $tokenType = $response['token_type'];
        $idToken = $response['id_token'];

        $this->setCookieAttribute('refreshToken', $refreshToken);

        $this->response->setJSON([
            'access_token' => $accessToken,
            'expires_in' => $expiresIn,
            'scope' => $scope,
            'token_type' => $tokenType,
            'id_token' => $idToken,
        ]);
        $this->response->send();
    }



    // <editor-fold desc="Cookie">

    public static string $COOKIE_NAME = 'Tokens';
    public static string $COOKIE_PREFIX = 'OAuthAgent-';

    private Cookie $cookie;

    private function getCookie(): Cookie {
        if (!isset($this->cookie)) {
            helper('cookie');
            $this->cookie = new Cookie(
                self::$COOKIE_NAME,
                json_encode([]),
                [
                    'expires' => new DateTime('+1 year'),
                    'prefix' => self::$COOKIE_PREFIX,
                    'path' => '/',
                    'domain' => '',
                    'secure' => $this->request->isSecure(),
                    'httponly' => true,
                    'raw' => false,
                    'samesite' => CookieInterface::SAMESITE_STRICT,
                ]
            );
            // Set value from request
            if (get_cookie(self::$COOKIE_NAME, false, self::$COOKIE_PREFIX)) {
                $this->cookie = $this->cookie->withValue(get_cookie(self::$COOKIE_NAME, false, self::$COOKIE_PREFIX));
            }
        }
        return $this->cookie;
    }

    private function setCookieAttribute(string $name, string $value): void {
        $cookie = $this->getCookie();
        $encrypter = service('encrypter');
        try {
            $cookieValue = json_decode($encrypter->decrypt($cookie->getValue()), true);
        } catch (\Exception $e) {
            $cookieValue = [];
        }
        $cookieValue[$name] = $value;
        $this->cookie = $this->cookie->withValue($encrypter->encrypt(json_encode($cookieValue)));
        $this->response->setCookie($this->cookie);
    }

    private function getCookieAttribute(string $name): ?string {
        $cookie = $this->getCookie();
        $encrypter = service('encrypter');
        try {
            $cookieValue = json_decode($encrypter->decrypt($cookie->getValue()), true);
        } catch (\Exception $e) {
            $cookieValue = [];
        }
        if ($cookieValue && isset($cookieValue[$name])) {
            return $cookieValue[$name];
        } else {
            return null;
        }
    }

    // </editor-fold>

}
