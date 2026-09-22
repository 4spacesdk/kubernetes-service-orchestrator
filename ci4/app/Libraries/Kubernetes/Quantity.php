<?php namespace App\Libraries\Kubernetes;

/**
 * Kubernetes writes the same number a dozen ways: cpu as `250m`, `0.25` or `36236n`, memory as
 * `64Mi`, `65536Ki` or a plain count of bytes. metrics.k8s.io picks whichever suits it, and a
 * reader that assumes one of them is wrong by a factor of a thousand - quietly, because the
 * number still looks like a number.
 */
class Quantity {

    /** Suffix => how many of the base unit it is. */
    private const array CpuScales = [
        'n' => 1e-9,
        'u' => 1e-6,
        'm' => 1e-3,
        '' => 1.0,
    ];

    private const array MemoryScales = [
        'Ki' => 1024,
        'Mi' => 1024 ** 2,
        'Gi' => 1024 ** 3,
        'Ti' => 1024 ** 4,
        'Pi' => 1024 ** 5,
        'k' => 1000,
        'K' => 1000,
        'M' => 1000 ** 2,
        'G' => 1000 ** 3,
        'T' => 1000 ** 4,
        'P' => 1000 ** 5,
        '' => 1,
    ];

    /**
     * Cpu as millicores - the unit kso's own limits are in. Null when it cannot be read, which is
     * better shown as nothing than as zero: a pod using no cpu and a pod nobody could measure are
     * not the same thing.
     */
    public static function Millicores(?string $quantity): ?int {
        $parsed = self::Split($quantity, array_keys(self::CpuScales));
        if ($parsed === null) {
            return null;
        }
        [$number, $suffix] = $parsed;

        return (int) round($number * self::CpuScales[$suffix] * 1000);
    }

    /**
     * Memory in bytes. Null for the same reason as above.
     */
    public static function Bytes(?string $quantity): ?int {
        $parsed = self::Split($quantity, array_keys(self::MemoryScales));
        if ($parsed === null) {
            return null;
        }
        [$number, $suffix] = $parsed;

        return (int) round($number * self::MemoryScales[$suffix]);
    }

    /**
     * @param list<string> $suffixes
     * @return array{0: float, 1: string}|null
     */
    private static function Split(?string $quantity, array $suffixes): ?array {
        $quantity = trim((string) $quantity);
        if ($quantity === '') {
            return null;
        }

        // Longest first, so `Mi` is not read as `M` with an `i` left over.
        usort($suffixes, fn(string $a, string $b) => strlen($b) <=> strlen($a));

        foreach ($suffixes as $suffix) {
            if ($suffix !== '' && str_ends_with($quantity, $suffix)) {
                $number = substr($quantity, 0, -strlen($suffix));
                return is_numeric($number) ? [(float) $number, $suffix] : null;
            }
        }

        return is_numeric($quantity) ? [(float) $quantity, ''] : null;
    }

}
