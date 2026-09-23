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
                    'link' => self::linkOf($vulnerability),
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

    /**
     * Where to read about a finding. Trivy's own link goes to Aqua's database, which has no
     * page for a CVE that is only reserved yet - and a distribution often ships the fix before
     * the CVE is published. The tracker of the distribution Trivy found it through has a page
     * for every entry it knows, with the versions that fix it. An advisory from GitHub goes to
     * GitHub. Anything else keeps Trivy's link.
     *
     * @param array<string, mixed> $vulnerability
     */
    public static function linkOf(array $vulnerability): string {
        $id = (string) ($vulnerability['VulnerabilityID'] ?? '');
        $source = (string) ($vulnerability['DataSource']['ID'] ?? '');

        if (str_starts_with($id, 'GHSA-')) {
            return "https://github.com/advisories/{$id}";
        }
        if (str_starts_with($id, 'CVE-') && isset(self::Trackers[$source])) {
            return sprintf(self::Trackers[$source], $id);
        }

        return (string) ($vulnerability['PrimaryURL'] ?? '');
    }

    /** A distribution's tracker page for a CVE, by Trivy's DataSource ID. */
    private const array Trackers = [
        'alpine' => 'https://security.alpinelinux.org/vuln/%s',
        'debian' => 'https://security-tracker.debian.org/tracker/%s',
        'ubuntu' => 'https://ubuntu.com/security/%s',
        'redhat' => 'https://access.redhat.com/security/cve/%s',
    ];

    private static function severity(?string $value): string {
        $value = strtolower((string) $value);

        return in_array($value, self::Severities, true) ? $value : 'unknown';
    }

}
