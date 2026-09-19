<?php namespace App\Libraries\Kubernetes;

/**
 * What Kubernetes accepts as a namespace name, and most other names: a DNS label. Lowercase
 * letters, digits and hyphens, at most 63, starting and ending with a letter or digit
 * (RFC 1123). The web app has the same rule in `core/kubernetesNames.ts`.
 */
class DnsLabel {

    public const int MaxLength = 63;

    public static function isValid(string $value): bool {
        return strlen($value) <= self::MaxLength && preg_match('/^[a-z0-9]([-a-z0-9]*[a-z0-9])?$/', $value) === 1;
    }

    /**
     * A readable name as a DNS label: "Øster Ås, Nord" -> "oester-aas-nord". Letters with
     * accents keep their base letter; anything else becomes a hyphen.
     */
    public static function from(string $text, int $maxLength = self::MaxLength): string {
        $text = strtr(mb_strtolower($text), ['æ' => 'ae', 'ø' => 'oe', 'å' => 'aa', 'ß' => 'ss']);
        $text = \Normalizer::normalize($text, \Normalizer::FORM_D);
        $text = preg_replace('/\p{Mn}+/u', '', $text);
        $text = trim(preg_replace('/[^a-z0-9]+/', '-', $text), '-');
        return rtrim(substr($text, 0, $maxLength), '-');
    }

}
