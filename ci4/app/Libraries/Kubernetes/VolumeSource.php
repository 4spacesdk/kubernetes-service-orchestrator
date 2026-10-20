<?php namespace App\Libraries\Kubernetes;

use App\Entities\Deployment;
use App\Entities\EnvironmentVariable;

/**
 * The two fields of a volume that are text in kso and lists in the PersistentVolume: a CSI
 * volume's attributes and the mount options. Both may carry the placeholders an environment
 * variable can - `share: /${namespace}` is the one a volume per workspace on one NFS server needs.
 */
class VolumeSource {

    /**
     * `key=value`, one a line. Blank lines and lines starting with `#` are skipped, and the value
     * is everything after the first `=`, so it may hold one itself.
     *
     * @return array<string, string>
     */
    public static function Attributes(string $text, Deployment $deployment): array {
        $attributes = [];
        foreach (self::Lines(EnvironmentVariable::ApplyVariablesToString($text, $deployment)) as $line) {
            [$key, $value] = explode('=', $line, 2);
            $attributes[trim($key)] = trim($value);
        }

        return $attributes;
    }

    /**
     * Separated by commas, as fstab has them: `vers=4,noatime`.
     *
     * @return string[]
     */
    public static function MountOptions(string $text, Deployment $deployment): array {
        $options = array_map('trim', explode(',', EnvironmentVariable::ApplyVariablesToString($text, $deployment)));

        return array_values(array_filter($options, fn (string $option) => $option !== ''));
    }

    /**
     * Why the attributes cannot be read, or null when they can. Asked when the volume is checked,
     * so a line without a key is named rather than dropped.
     */
    public static function AttributesProblem(string $text): ?string {
        foreach (self::Lines($text) as $line) {
            if (!str_contains($line, '=') || trim(explode('=', $line, 2)[0]) === '') {
                return "Invalid csi volume attribute '{$line}': one key=value a line";
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private static function Lines(string $text): array {
        $lines = array_map('trim', preg_split('/\R/', $text) ?: []);

        return array_values(array_filter($lines, fn (string $line) => $line !== '' && !str_starts_with($line, '#')));
    }

}
