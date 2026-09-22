<?php namespace App\Controllers;

use AuthExtension\OAuth2\ServerLib;
use CodeIgniter\Cookie\Cookie;
use CodeIgniter\Cookie\CookieInterface;
use DateTime;
use DebugTool\Data;

class OAuthAgent extends \App\Core\BaseController {

    public function requireAuth(string $method): bool {
        return false;
    }

    /**
     * kso's own OAuth endpoints, called from inside the container. In development BASE_URL is
     * the port docker-compose publishes (8950), which does not exist inside the container, so
     * it becomes the port Apache listens on there.
     */
    private function getBaseUrl($relativePath = '', ?string $scheme = null): string {
        return str_replace(':8950', ':8080', base_url($relativePath, $scheme));
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

        // Error handling
        if (is_null($response) || !isset($response['access_token'])) {
            Data::debug($curlError);
            Data::debug($raw);
            $this->fail('failed to fetch access token', 400);
            return;
        }

        // `access_token` is the only field checked above and the only one a grant must
        // carry. The rest are optional in OAuth and their absence is ordinary rather than
        // an error: `client_credentials`, and `authorization_code` without the `openid`
        // scope, both answer without an `id_token`. Reading them as required turned a
        // perfectly valid grant into an uncaught `Undefined array key` - a 500 for the
        // client instead of a token.
        $this->keepTheRefreshToken($response);

        $this->response->setJSON([
            'access_token' => $response['access_token'],
            'expires_in' => $response['expires_in'] ?? null,
            'id_token' => $response['id_token'] ?? null,
            'scope' => $response['scope'] ?? null,
            'token_type' => $response['token_type'] ?? null,
        ]);
        $this->response->send();
    }

    /**
     * Signing out of this browser: its refresh token and access token revoked, and the cookie
     * that holds the refresh token gone.
     *
     * The sign-out page only ended the session. The cookie stayed for a year and the refresh
     * token in it for a week, so the next person at the same machine could post to `refresh`
     * and be signed in. The app calls this before it sends the browser to sign-out.
     *
     * Only this browser: the user's other devices keep their sign-ins. A changed password ends
     * those - see `User::EndEverySignIn()`.
     */
    public function logout(): void {
        $storage = ServerLib::getInstance()->storage;

        $refreshToken = $this->getCookieAttribute('refreshToken');
        if ($refreshToken) {
            $storage->unsetRefreshToken($refreshToken);
        }

        $authorization = $this->request->getHeaderLine('Authorization');
        if (str_starts_with($authorization, 'Bearer ')) {
            $storage->unsetAccessToken(substr($authorization, strlen('Bearer ')));
        }

        $this->response->setCookie($this->getCookie()->withValue('')->withExpired());
        $this->success();
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

        // Error handling
        if (is_null($response) || !isset($response['access_token'])) {
            Data::debug($curlError);
            Data::debug($response ?? $raw);
            $this->fail('failed to fetch access token', 400);
            return;
        }

        // As in `token()`: only `access_token` is promised. A server that does not rotate
        // refresh tokens answers a renewal without one, which is allowed, and every
        // renewal used to end in a server error rather than a token.
        $this->keepTheRefreshToken($response);

        $this->response->setJSON([
            'access_token' => $response['access_token'],
            'expires_in' => $response['expires_in'] ?? null,
            'scope' => $response['scope'] ?? null,
            'token_type' => $response['token_type'] ?? null,
            'id_token' => $response['id_token'] ?? null,
        ]);
        $this->response->send();
    }



    // <editor-fold desc="Cookie">

    /**
     * Put the grant's refresh token in the cookie, when it carried one.
     *
     * Only when it did: overwriting the cookie with nothing would throw away the token the
     * browser is holding, and the next renewal would find none and send the user back to
     * sign in. A renewal that rotates nothing means the one already there is still current.
     *
     * @param array<string, mixed> $response
     */
    private function keepTheRefreshToken(array $response): void {
        if (isset($response['refresh_token'])) {
            $this->setCookieAttribute('refreshToken', $response['refresh_token']);
        }
    }


    public static string $COOKIE_NAME = 'Tokens';
    public static string $COOKIE_PREFIX = 'OAuthAgent-';

    private Cookie $cookie;

    /**
     * Whether TLS ended in front of kso rather than at this process.
     *
     * `IncomingRequest::isSecure()` covers the second case only. It does look at
     * `X-Forwarded-Proto`, but believes it only from an IP listed in
     * `Config\App::$proxyIPs` - and kso has none to list, because TLS is terminated by an
     * ingress whose pod address the installation does not know. So it was false on every
     * request kso actually serves, and the refresh token cookie went out **without
     * `Secure`**: the browser would attach a year of access to plain http to the same host,
     * which kso answers.
     *
     * The same signal `Config\App::__construct()` falls back on for the base URL.
     */
    private function arrivedOverForwardedTls(): bool {
        return $this->request->hasHeader('X-Forwarded-Proto')
            && strtolower($this->request->header('X-Forwarded-Proto')->getValue()) === 'https';
    }

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
                    'secure' => $this->request->isSecure() || $this->arrivedOverForwardedTls(),
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
