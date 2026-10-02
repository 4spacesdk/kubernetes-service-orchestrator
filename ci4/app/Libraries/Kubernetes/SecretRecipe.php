<?php namespace App\Libraries\Kubernetes;

/**
 * What a secret kso makes is: how long, and of what. Written after the name the way a Helm chart
 * writes it with Sprig's functions - `${secret.token | randAlphaNum 32}` - so a chart's
 * `{{ randAlphaNum 32 }}` moves over as it is. Without one it is `randHex 64`: 32 random bytes as
 * hex.
 *
 * | Function          | Makes                                                      |
 * |-------------------|------------------------------------------------------------|
 * | `randHex N`       | N hex characters (kso's own, and the default with N = 64)  |
 * | `randAlphaNum N`  | N letters and digits                                       |
 * | `randAlpha N`     | N letters                                                  |
 * | `randNumeric N`   | N digits                                                   |
 * | `randAscii N`     | N printable ASCII characters, punctuation included         |
 * | `randBytes N`     | N random bytes, base64-encoded - as Sprig's `randBytes`    |
 * | `uuidv4`          | a random UUID                                              |
 */
final class SecretRecipe {

    public const int MaxLength = 4096;

    private const array Alphabets = [
        'randHex' => '0123456789abcdef',
        'randAlphaNum' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
        'randAlpha' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
        'randNumeric' => '0123456789',
    ];

    private function __construct(public readonly string $function, public readonly ?int $length) {
    }

    public static function Default(): self {
        return new self('randHex', 64);
    }

    /**
     * The recipe as written after the `|`, or a reason it is not one. Empty is the default.
     */
    public static function Parse(string $written): self|string {
        $written = trim($written);
        if ($written === '') {
            return self::Default();
        }
        if ($written === 'uuidv4') {
            return new self('uuidv4', null);
        }
        if (!preg_match('/^(randHex|randAlphaNum|randAlpha|randNumeric|randAscii|randBytes)\s+(\d+)$/', $written, $match)) {
            return "'{$written}' is not a recipe kso knows - randHex, randAlphaNum, randAlpha, randNumeric, randAscii or randBytes with a length, or uuidv4";
        }
        $length = (int) $match[2];
        if ($length < 1 || $length > self::MaxLength) {
            return "the length in '{$written}' is not between 1 and " . self::MaxLength;
        }
        return new self($match[1], $length);
    }

    /**
     * A new value, from the system's cryptographically secure source.
     */
    public function make(): string {
        return match ($this->function) {
            'uuidv4' => self::Uuid(),
            'randBytes' => base64_encode(random_bytes($this->length)),
            'randAscii' => self::FromAlphabet(implode('', array_map('chr', range(33, 126))), $this->length),
            default => self::FromAlphabet(self::Alphabets[$this->function], $this->length),
        };
    }

    /**
     * As it is stored, and as it is written: `randAlphaNum 32`, `uuidv4`.
     */
    public function __toString(): string {
        return $this->length === null ? $this->function : "{$this->function} {$this->length}";
    }

    private static function FromAlphabet(string $alphabet, int $length): string {
        $last = strlen($alphabet) - 1;
        $value = '';
        for ($i = 0; $i < $length; $i++) {
            $value .= $alphabet[random_int(0, $last)];
        }
        return $value;
    }

    private static function Uuid(): string {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

}
