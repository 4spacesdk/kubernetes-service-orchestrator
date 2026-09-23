<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use DateTimeInterface;

class Cookie extends BaseConfig
{
    /**
     * --------------------------------------------------------------------------
     * Cookie Prefix
     * --------------------------------------------------------------------------
     *
     * Set a cookie name prefix if you need to avoid collisions.
     */
    public string $prefix = '';

    /**
     * --------------------------------------------------------------------------
     * Cookie Expires Timestamp
     * --------------------------------------------------------------------------
     *
     * Default expires timestamp for cookies. Setting this to `0` will mean the
     * cookie will not have the `Expires` attribute and will behave as a session
     * cookie.
     *
     * @var DateTimeInterface|int|string
     */
    public $expires = 0;

    /**
     * --------------------------------------------------------------------------
     * Cookie Path
     * --------------------------------------------------------------------------
     *
     * Typically will be a forward slash.
     */
    public string $path = '/';

    /**
     * --------------------------------------------------------------------------
     * Cookie Domain
     * --------------------------------------------------------------------------
     *
     * Set to `.your-domain.com` for site-wide cookies.
     */
    public string $domain = '';

    /**
     * --------------------------------------------------------------------------
     * Cookie Secure
     * --------------------------------------------------------------------------
     *
     * Cookie will only be set if a secure HTTPS connection exists.
     */
    public bool $secure = false;

    /**
     * Secure when kso is reached over https *and* this request arrived over TLS.
     *
     * The base url alone is not enough: CodeIgniter refuses to send a Secure cookie on a request
     * it does not think is secure, and throws. A call from inside the cluster - the scheduler's
     * `curl kso.kso/api/jobby`, a probe - comes over plain HTTP to an installation whose base url
     * is https, and every one of them ended in a fatal error. The test is CodeIgniter's own
     * (`IncomingRequest::isSecure()`), so the two cannot disagree; behind a proxy, Apache sets
     * `HTTPS=on` from X-Forwarded-Proto - see `docker/apache/httpd.conf`.
     */
    public function __construct() {
        parent::__construct();
        $https = $_SERVER['HTTPS'] ?? '';
        $overTls = $https !== '' && strtolower((string) $https) !== 'off';
        $this->secure = $overTls && str_starts_with(strtolower(config('App')->baseURL), 'https://');
    }

    /**
     * --------------------------------------------------------------------------
     * Cookie HTTPOnly
     * --------------------------------------------------------------------------
     *
     * Cookie will only be accessible via HTTP(S) (no JavaScript).
     */
    public bool $httponly = true;

    /**
     * --------------------------------------------------------------------------
     * Cookie SameSite
     * --------------------------------------------------------------------------
     *
     * Configure cookie SameSite setting. Allowed values are:
     * - None
     * - Lax
     * - Strict
     * - ''
     *
     * Alternatively, you can use the constant names:
     * - `Cookie::SAMESITE_NONE`
     * - `Cookie::SAMESITE_LAX`
     * - `Cookie::SAMESITE_STRICT`
     *
     * Defaults to `Lax` for compatibility with modern browsers. Setting `''`
     * (empty string) means default SameSite attribute set by browsers (`Lax`)
     * will be set on cookies. If set to `None`, `$secure` must also be set.
     *
     * @var ''|'Lax'|'None'|'Strict'
     */
    public string $samesite = 'Lax';

    /**
     * --------------------------------------------------------------------------
     * Cookie Raw
     * --------------------------------------------------------------------------
     *
     * This flag allows setting a "raw" cookie, i.e., its name and value are
     * not URL encoded using `rawurlencode()`.
     *
     * If this is set to `true`, cookie names should be compliant of RFC 2616's
     * list of allowed characters.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie#attributes
     * @see https://tools.ietf.org/html/rfc2616#section-2.2
     */
    public bool $raw = false;
}
