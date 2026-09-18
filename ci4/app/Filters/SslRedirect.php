<?php namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Send plain HTTP to HTTPS when the deployment asks for it.
 *
 * Turned on per installation with SSL_REDIRECT, and only for the host the instance is
 * served on, so a health check or an internal call on another hostname is left alone.
 *
 * kso usually sits behind a proxy that terminates TLS, so the original scheme arrives in
 * X-Forwarded-Proto rather than in $_SERVER['HTTPS'].
 *
 * This used to live in public/index.php. It is a filter so that file stays as CodeIgniter
 * ships it and needs no merging on the next framework upgrade.
 */
class SslRedirect implements FilterInterface {

    public function before(RequestInterface $request, $arguments = null) {
        if (! getenv('SSL_REDIRECT')) {
            return null;
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '' || $host !== getenv('BASE_URL')) {
            return null;
        }

        if ($this->isHttps()) {
            return null;
        }

        return redirect()->to('https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'))
            ->setStatusCode(301);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {
        return null;
    }

    private function isHttps(): bool {
        $direct = isset($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) === 'on';
        $forwarded = isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';

        return $direct || $forwarded;
    }

}
