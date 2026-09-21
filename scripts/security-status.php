<?php
/**
 * Writes SECURITY-STATUS.md from what three scanners found:
 *
 *   php scripts/security-status.php <directory with the reports> <output file> [image]
 *
 * The directory holds, each optional:
 *   composer-<name>.json  `composer audit --locked --format=json` for each composer.lock
 *   npm-<name>.json       `npm audit --json --package-lock-only` for each package-lock.json
 *   trivy.json            `trivy image --format json` for the published image
 *
 * The installed versions are read from the lock files, from the working directory: the
 * report for `ci4` is `ci4/composer.lock`, and so on.
 *
 * Run weekly by .github/workflows/security-status.yml. A report that is missing or cannot
 * be read is said to be missing - an empty table would read as "nothing found".
 */

const SEVERITIES = ['critical', 'high', 'medium', 'low', 'unknown'];

[$script, $directory, $output] = array_pad($argv, 3, null);
$image = $argv[3] ?? '4spaces/kubernetes-service-orchestrator:dev';

// The commit the lock files were read at: the checkout the workflow made of main.
$commit = getenv('GITHUB_SHA') ?: trim((string) shell_exec('git rev-parse HEAD 2>/dev/null'));
if ($directory === null || $output === null) {
    fwrite(STDERR, "usage: php {$script} <reports directory> <output file> [image]\n");
    exit(2);
}

/** @var array<string, array{findings: list<array<string, string>>, note: ?string}> $sources */
$sources = [];

foreach (glob("{$directory}/composer-*.json") ?: [] as $file) {
    $name = substr(basename($file, '.json'), strlen('composer-'));
    $sources["PHP · {$name}/composer.lock"] = composerFindings($file, installedComposerVersions("{$name}/composer.lock"));
}
foreach (glob("{$directory}/npm-*.json") ?: [] as $file) {
    $name = substr(basename($file, '.json'), strlen('npm-'));
    $sources["JavaScript · {$name}/package-lock.json"] = npmFindings($file, installedNpmVersions("{$name}/package-lock.json"));
}
$sources["Image · {$image}"] = trivyFindings("{$directory}/trivy.json", $commit);

file_put_contents($output, render($sources, $image, $commit));

// <editor-fold desc="Readers">

/**
 * @return array{findings: list<array<string, string>>, note: ?string}
 */
function composerFindings(string $file, array $installed): array {
    $report = readJson($file);
    if ($report === null) {
        return ['findings' => [], 'note' => 'not scanned: the report could not be read'];
    }

    $findings = [];
    // An empty list when there is nothing, an object keyed by package when there is.
    foreach ((array) ($report['advisories'] ?? []) as $package => $advisories) {
        foreach ($advisories as $advisory) {
            $findings[] = [
                'id' => $advisory['cve'] ?: ($advisory['sources'][0]['remoteId'] ?? $advisory['advisoryId']),
                'link' => $advisory['link'] ?? '',
                'package' => $package,
                'installed' => $installed[$package] ?? '',
                'fixed' => affected($advisory['affectedVersions'] ?? '', $installed[$package] ?? ''),
                'severity' => severity($advisory['severity'] ?? null),
                'title' => $advisory['title'] ?? '',
            ];
        }
    }

    $abandoned = array_keys((array) ($report['abandoned'] ?? []));
    $note = $abandoned === [] ? null : 'abandoned: ' . implode(', ', $abandoned);

    return ['findings' => $findings, 'note' => $note];
}

/**
 * @return array{findings: list<array<string, string>>, note: ?string}
 */
function npmFindings(string $file, array $installed): array {
    $report = readJson($file);
    if ($report === null || !isset($report['vulnerabilities'])) {
        return ['findings' => [], 'note' => 'not scanned: the report could not be read'];
    }

    $findings = [];
    foreach ($report['vulnerabilities'] as $package => $vulnerability) {
        // `via` holds the advisories themselves, or names of other packages this one is
        // vulnerable through. Only the advisories are findings; the rest are the same
        // finding seen from a dependant.
        foreach ($vulnerability['via'] ?? [] as $via) {
            if (!is_array($via)) {
                continue;
            }
            $url = $via['url'] ?? '';
            $findings[] = [
                'id' => preg_match('#(GHSA-[\w-]+)#', $url, $m) ? $m[1] : (string) ($via['source'] ?? ''),
                'link' => $url,
                'package' => $via['name'] ?? $package,
                'installed' => $installed[$via['name'] ?? $package] ?? '',
                'fixed' => affected($via['range'] ?? '', $installed[$via['name'] ?? $package] ?? ''),
                'severity' => severity($via['severity'] ?? null),
                'title' => $via['title'] ?? '',
            ];
        }
    }

    return ['findings' => unique($findings), 'note' => null];
}

/**
 * @return array{findings: list<array<string, string>>, note: ?string}
 */
function trivyFindings(string $file, string $mainCommit): array {
    $report = readJson($file);
    if ($report === null) {
        return ['findings' => [], 'note' => 'not scanned: no report'];
    }

    $findings = [];
    foreach ($report['Results'] ?? [] as $result) {
        foreach ($result['Vulnerabilities'] ?? [] as $vulnerability) {
            $findings[] = [
                'id' => $vulnerability['VulnerabilityID'] ?? '',
                'link' => $vulnerability['PrimaryURL'] ?? '',
                'package' => $vulnerability['PkgName'] ?? '',
                'installed' => $vulnerability['InstalledVersion'] ?? '',
                'fixed' => $vulnerability['FixedVersion'] ?? '',
                'severity' => severity($vulnerability['Severity'] ?? null),
                'title' => $vulnerability['Title'] ?? '',
            ];
        }
    }

    // Which commit the image was built from: it carries its own SHORT_SHA (docker/Dockerfile).
    // `:dev` is built on every push to main, so it is normally the same commit - but a push
    // that has not been built yet leaves it one behind, and that is said.
    $built = null;
    foreach ($report['Metadata']['ImageConfig']['config']['Env'] ?? [] as $variable) {
        if (str_starts_with($variable, 'SHORT_SHA=') && strlen($variable) > strlen('SHORT_SHA=')) {
            $built = substr($variable, strlen('SHORT_SHA='));
        }
    }
    $builtFrom = match (true) {
        $built === null => null,
        $mainCommit !== '' && str_starts_with($mainCommit, $built) => "built from {$built}, the commit above",
        default => "built from {$built} - behind main, whose latest push is not built yet",
    };
    $os = trim(($report['Metadata']['OS']['Family'] ?? '') . ' ' . ($report['Metadata']['OS']['Name'] ?? ''));
    $digest = $report['Metadata']['RepoDigests'][0] ?? null;
    $note = implode(' · ', array_filter([$builtFrom, $os ?: null, $digest])) ?: null;

    return ['findings' => unique($findings), 'note' => $note];
}

// </editor-fold>

// <editor-fold desc="Rendering">

/**
 * @param array<string, array{findings: list<array<string, string>>, note: ?string}> $sources
 */
function render(array $sources, string $image, string $commit): string {
    $lines = [];
    $lines[] = '# Security status';
    $lines[] = '';
    $lines[] = 'Known vulnerabilities in **main**: its lock files, and the `:dev` image built from it,';
    $lines[] = 'found by `composer audit`, `npm audit` and [Trivy](https://trivy.dev). A release carries';
    $lines[] = 'this file as it was when it was tagged. Written every week by';
    $lines[] = '[a scheduled workflow](.github/workflows/security-status.yml) - do not edit by hand.';
    $lines[] = '';
    $lines[] = 'Updated: ' . gmdate('Y-m-d H:i') . ' UTC';
    $lines[] = '';
    $lines[] = 'Main at: ' . ($commit !== '' ? '`' . substr($commit, 0, 7) . '`' : 'unknown');
    $lines[] = '';
    $lines[] = '| Source | Critical | High | Medium | Low | Unknown |';
    $lines[] = '|--------|---------:|-----:|-------:|----:|--------:|';
    foreach ($sources as $name => $source) {
        if (str_starts_with((string) $source['note'], 'not scanned')) {
            $lines[] = "| {$name} | {$source['note']} ||||| ";
            continue;
        }
        $counts = array_fill_keys(SEVERITIES, 0);
        foreach ($source['findings'] as $finding) {
            $counts[$finding['severity']]++;
        }
        $lines[] = "| {$name} | " . implode(' | ', array_map(fn ($s) => (string) $counts[$s], SEVERITIES)) . ' |';
    }

    foreach ($sources as $name => $source) {
        $lines[] = '';
        $lines[] = "## {$name}";
        $lines[] = '';
        if ($source['note'] !== null) {
            $lines[] = "*{$source['note']}*";
            $lines[] = '';
        }
        if ($source['findings'] === []) {
            if (!str_starts_with((string) $source['note'], 'not scanned')) {
                $lines[] = 'Nothing known.';
            }
            continue;
        }

        usort($source['findings'], fn ($a, $b) => [array_search($a['severity'], SEVERITIES), $a['package'], $a['id']]
            <=> [array_search($b['severity'], SEVERITIES), $b['package'], $b['id']]);

        // Critical, high and medium in the open; low and unknown folded away, since an image
        // on a full distribution carries many and they would bury the ones that matter.
        $open = array_filter($source['findings'], fn ($f) => in_array($f['severity'], ['critical', 'high', 'medium'], true));
        $folded = array_filter($source['findings'], fn ($f) => !in_array($f['severity'], ['critical', 'high', 'medium'], true));

        if ($open !== []) {
            array_push($lines, ...table($open));
        }
        if ($folded !== []) {
            $lines[] = '';
            $lines[] = '<details><summary>' . count($folded) . ' low or unknown</summary>';
            $lines[] = '';
            array_push($lines, ...table($folded));
            $lines[] = '';
            $lines[] = '</details>';
        }
    }

    return implode("\n", $lines) . "\n";
}

/**
 * @param array<array<string, string>> $findings
 * @return list<string>
 */
function table(array $findings): array {
    $lines = [
        '| Severity | Id | Package | Installed | Fix | Title |',
        '|----------|----|---------|-----------|-----|-------|',
    ];
    foreach ($findings as $f) {
        $id = $f['link'] !== '' ? "[{$f['id']}]({$f['link']})" : $f['id'];
        $lines[] = '| ' . implode(' | ', array_map('cell', [
            $f['severity'], $id, $f['package'], $f['installed'], $f['fixed'], $f['title'],
        ])) . ' |';
    }

    return $lines;
}

function cell(string $value): string {
    return str_replace(['|', "\n", "\r"], ['\\|', ' ', ''], mb_strimwidth($value, 0, 120, '…'));
}

// </editor-fold>

/**
 * @return array<string, string> package name => installed version
 */
function installedComposerVersions(string $lockFile): array {
    $lock = readJson($lockFile) ?? [];
    $versions = [];
    foreach ([...($lock['packages'] ?? []), ...($lock['packages-dev'] ?? [])] as $package) {
        $versions[$package['name']] = $package['version'];
    }

    return $versions;
}

/**
 * The top-level copy of each package. A package installed at several versions shows the one
 * most of the tree uses.
 *
 * @return array<string, string> package name => installed version
 */
function installedNpmVersions(string $lockFile): array {
    $lock = readJson($lockFile) ?? [];
    $versions = [];
    foreach ($lock['packages'] ?? [] as $path => $package) {
        if (preg_match('#^node_modules/((?:@[^/]+/)?[^/]+)$#', $path, $m)) {
            $versions[$m[1]] = $package['version'] ?? '';
        }
    }

    return $versions;
}

function readJson(string $file): ?array {
    if (!is_file($file)) {
        return null;
    }
    $decoded = json_decode((string) file_get_contents($file), true);

    return is_array($decoded) ? $decoded : null;
}

/**
 * What an advisory says is affected, cut to the part that concerns the installed version.
 *
 * Composer lists a range per major version (`>=5.0.0,<5.1.0|>=5.1.0,<5.2.0|...`), which does
 * not fit in a table and is mostly about versions nobody here runs. The Fix column says
 * "not <range>": upgrade out of it.
 */
function affected(string $ranges, string $installed): string {
    if ($ranges === '') {
        return '';
    }
    $version = ltrim($installed, 'v');
    $parts = preg_split('/\s*\|\|?\s*/', $ranges) ?: [$ranges];
    if ($version !== '' && count($parts) > 1) {
        foreach ($parts as $part) {
            if (inRange($version, $part)) {
                return "not {$part}";
            }
        }
    }

    return 'not ' . $ranges;
}

/**
 * Whether `$version` meets every constraint in one range: `>=1.2,<1.3`, `>=1.1.0 <=1.8.3`.
 */
function inRange(string $version, string $range): bool {
    if (!preg_match_all('/(>=|<=|>|<|=)\s*v?([\w.-]+)/', $range, $constraints, PREG_SET_ORDER)) {
        return false;
    }
    foreach ($constraints as [, $operator, $bound]) {
        if (!version_compare($version, $bound, $operator === '=' ? '==' : $operator)) {
            return false;
        }
    }

    return true;
}

function severity(?string $value): string {
    $value = strtolower((string) $value);

    return match ($value) {
        'critical', 'high', 'low' => $value,
        'medium', 'moderate' => 'medium',
        default => 'unknown',
    };
}

/**
 * The same advisory can reach a lock file or an image by several paths.
 *
 * @param list<array<string, string>> $findings
 * @return list<array<string, string>>
 */
function unique(array $findings): array {
    $seen = [];
    foreach ($findings as $finding) {
        $seen[$finding['id'] . '|' . $finding['package'] . '|' . $finding['installed']] = $finding;
    }

    return array_values($seen);
}
