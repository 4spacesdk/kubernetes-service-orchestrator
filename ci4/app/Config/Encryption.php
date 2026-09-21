<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Encryption configuration.
 *
 * These are the settings used for encryption, if you don't pass a parameter
 * array to the encrypter for creation/initialization.
 */
class Encryption extends BaseConfig
{
    /**
     * --------------------------------------------------------------------------
     * Encryption Key Starter
     * --------------------------------------------------------------------------
     *
     * If you use the Encryption class you must set an encryption key (seed).
     * You need to ensure it is long enough for the cipher and mode you plan to use.
     * See the user guide for more info.
     */
    public string $key = '';

    /**
     * --------------------------------------------------------------------------
     * Previous Encryption Keys
     * --------------------------------------------------------------------------
     *
     * When rotating encryption keys, add old keys here to maintain ability
     * to decrypt data encrypted with previous keys. Encryption always uses
     * the current $key. Decryption tries current key first, then falls back
     * to previous keys if decryption fails.
     *
     * In .env file, use comma-separated string:
     *   encryption.previousKeys = hex2bin:9be8c64fcea509867...,hex2bin:3f5a1d8e9c2b7a4f6...
     *
     * @var list<string>|string
     */
    public array|string $previousKeys = '';

    /**
     * --------------------------------------------------------------------------
     * Encryption Driver to Use
     * --------------------------------------------------------------------------
     *
     * One of the supported encryption drivers.
     *
     * Available drivers:
     * - OpenSSL
     * - Sodium
     */
    public string $driver = 'OpenSSL';

    /**
     * --------------------------------------------------------------------------
     * SodiumHandler's Padding Length in Bytes
     * --------------------------------------------------------------------------
     *
     * This is the number of bytes that will be padded to the plaintext message
     * before it is encrypted. This value should be greater than zero.
     *
     * See the user guide for more information on padding.
     */
    public int $blockSize = 16;

    /**
     * --------------------------------------------------------------------------
     * Encryption digest
     * --------------------------------------------------------------------------
     *
     * HMAC digest to use, e.g. 'SHA512' or 'SHA256'. Default value is 'SHA512'.
     */
    public string $digest = 'SHA512';

    /**
     * Whether the cipher-text should be raw. If set to false, then it will be base64 encoded.
     * This setting is only used by OpenSSLHandler.
     *
     * Set to false for CI3 Encryption compatibility.
     */
    public bool $rawData = true;

    /**
     * Encryption key info.
     * This setting is only used by OpenSSLHandler.
     *
     * Set to 'encryption' for CI3 Encryption compatibility.
     */
    public string $encryptKeyInfo = '';

    /**
     * Authentication key info.
     * This setting is only used by OpenSSLHandler.
     *
     * Set to 'authentication' for CI3 Encryption compatibility.
     */
    public string $authKeyInfo = '';

    /**
     * Cipher to use.
     * This setting is only used by OpenSSLHandler.
     *
     * Set to 'AES-128-CBC' to decrypt encrypted data that encrypted
     * by CI3 Encryption default configuration.
     */
    public string $cipher = 'AES-256-CTR';

    /**
     * The key comes from the environment, like the database credentials do.
     *
     * It used to be written here, committed, so every installation shared one key and anyone
     * with the repository had it. (Not repeated here: `EncryptionKeyTest` sweeps this file
     * for it, and git has the value.) That key is what
     * the OAuth agent's refresh-token cookie is encrypted with, and now what every stored
     * credential is encrypted with, so it has to be the installation's own.
     *
     * Missing is a failure, not a default. Encrypting with the empty string would produce
     * values nothing can read back once a real key is set, and the installation would only
     * find out when somebody tried to deploy.
     *
     * `ENCRYPTION_PREVIOUS_KEYS` is how a key is rotated: put the old one there, and
     * CodeIgniter falls back to it for anything the current key cannot read.
     */
    public function __construct() {
        parent::__construct();

        $key = (string) getenv('ENCRYPTION_KEY');
        if ($key === '') {
            throw new \RuntimeException(
                'ENCRYPTION_KEY is not set. It is what the stored credentials and the '
                . 'refresh-token cookie are encrypted with, and there is no safe default: '
                . 'an empty key writes values that nothing can read back once a real one is set.'
            );
        }

        $this->key = $key;

        // A list: CodeIgniter only splits the comma separated string when it reads it from
        // .env itself, and handed the string it failed on every encrypt and decrypt - so
        // setting this, which is what rotating a key means, took kso down.
        $previous = array_values(array_filter(array_map('trim', explode(',', (string) getenv('ENCRYPTION_PREVIOUS_KEYS')))));
        if ($previous !== []) {
            $this->previousKeys = $previous;
        }
    }
}
