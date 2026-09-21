<?php namespace App\Libraries\ImageScanning;

/**
 * Trivy's report cut to what kso keeps: a count per severity, and one line per finding with
 * the same fields SECURITY-STATUS.md shows for kso's own image.
 */
class TrivyReport {

    public const array Severities = ['critical', 'high', 'medium', 'low', 'unknown'];

    /** @var array<string, int> */
    public array $counts;

    /** @var list<array<string, string>> most severe first */
    public array $findings;

    public ?string $digest;

    /** What Trivy recognised the image as, e.g. "alpine 3.20.3" - null when it recognised none. */
    public ?string $operatingSystem;

    /**
     * How many parts of the image Trivy read packages from: the OS, and each language's lock or
     * install files. Zero means it found nothing to look at, which is not a clean image.
     */
    public int $targets;

    /**
     * @param array<string, mixed> $report Trivy's JSON, decoded
     */
    public function __construct(array $report) {
        $findings = [];
        foreach ($report['Results'] ?? [] as $result) {
            foreach ($result['Vulnerabilities'] ?? [] as $vulnerability) {
                $finding = [
                    'id' => (string) ($vulnerability['VulnerabilityID'] ?? ''),
                    'link' => (string) ($vulnerability['PrimaryURL'] ?? ''),
                    'package' => (string) ($vulnerability['PkgName'] ?? ''),
                    'installed' => (string) ($vulnerability['InstalledVersion'] ?? ''),
                    'fixed' => (string) ($vulnerability['FixedVersion'] ?? ''),
                    'severity' => self::severity($vulnerability['Severity'] ?? null),
                    'title' => mb_strimwidth((string) ($vulnerability['Title'] ?? ''), 0, 300, '…'),
                ];
                // The same package can reach an image by several paths, and Trivy lists it
                // once per path.
                $findings["{$finding['id']}|{$finding['package']}|{$finding['installed']}"] = $finding;
            }
        }

        $findings = array_values($findings);
        usort($findings, fn ($a, $b) => [array_search($a['severity'], self::Severities), $a['package'], $a['id']]
            <=> [array_search($b['severity'], self::Severities), $b['package'], $b['id']]);

        $this->findings = $findings;
        $this->counts = array_fill_keys(self::Severities, 0);
        foreach ($findings as $finding) {
            $this->counts[$finding['severity']]++;
        }
        $this->digest = $report['Metadata']['RepoDigests'][0] ?? null;
        $os = trim(($report['Metadata']['OS']['Family'] ?? '') . ' ' . ($report['Metadata']['OS']['Name'] ?? ''));
        $this->operatingSystem = $os !== '' ? $os : null;
        $this->targets = count($report['Results'] ?? []);
    }

    private static function severity(?string $value): string {
        $value = strtolower((string) $value);

        return in_array($value, self::Severities, true) ? $value : 'unknown';
    }

}
