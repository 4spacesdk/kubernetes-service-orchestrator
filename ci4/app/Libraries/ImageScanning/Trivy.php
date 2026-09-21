<?php namespace App\Libraries\ImageScanning;

/**
 * Runs Trivy against an image in its registry - no Docker daemon, the image is read over the
 * registry API, the same way the weekly SECURITY-STATUS.md scan reads kso's own.
 *
 * The binary is in the image (docker/Dockerfile). TRIVY_BINARY overrides it, which is how the
 * tests stand in for it. Trivy's own TRIVY_* variables pass straight through, so an
 * installation without internet access can point TRIVY_DB_REPOSITORY at a mirror.
 */
class Trivy {

    /** Longer than any image should take; a scan that hangs must not hold the cron job. */
    public const string Timeout = '15m';

    private string $binary;

    private string $cacheDirectory;

    public function __construct(?string $binary = null, ?string $cacheDirectory = null) {
        $this->binary = $binary ?? (getenv('TRIVY_BINARY') ?: 'trivy');
        // The vulnerability databases live here between scans, so they are fetched once per pod
        // and refreshed when Trivy finds them stale - not on every scan. They are large: 1.3 GB,
        // and 1.4 GB more once an image with Java in it has been scanned. The chart mounts a
        // size-limited volume and points TRIVY_CACHE_DIR at it.
        $this->cacheDirectory = $cacheDirectory ?? (getenv('TRIVY_CACHE_DIR') ?: sys_get_temp_dir() . '/kso-trivy-cache');
    }

    /**
     * @param string $reference registry/repository:tag
     * @param string|null $dockerConfig a Docker config.json with the credentials to pull with
     * @return array<string, mixed> Trivy's JSON report
     * @throws \RuntimeException with Trivy's own reason when it could not scan
     */
    public function scan(string $reference, ?string $dockerConfig): array {
        $work = sys_get_temp_dir() . '/kso-trivy-' . bin2hex(random_bytes(8));
        mkdir($work, 0700, true);
        $report = "{$work}/report.json";

        $environment = getenv();
        if ($dockerConfig !== null) {
            // A directory of its own with nothing else in it, removed below: the file holds
            // the registry password.
            mkdir("{$work}/docker", 0700);
            file_put_contents("{$work}/docker/config.json", $dockerConfig);
            chmod("{$work}/docker/config.json", 0600);
            $environment['DOCKER_CONFIG'] = "{$work}/docker";
        }

        try {
            // An argument list, not a command line: nothing in the reference reaches a shell.
            $process = proc_open(
                [
                    $this->binary, 'image',
                    '--quiet',
                    // Trivy sends usage data to Aqua by default; nothing about the customers'
                    // images leaves kso that does not have to.
                    '--disable-telemetry',
                    '--scanners', 'vuln',
                    '--format', 'json',
                    '--output', $report,
                    '--timeout', self::Timeout,
                    '--cache-dir', $this->cacheDirectory,
                    $reference,
                ],
                [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                null,
                $environment
            );
            if (!is_resource($process)) {
                throw new \RuntimeException("could not start {$this->binary}");
            }
            $errors = trim((string) stream_get_contents($pipes[2]));
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                throw new \RuntimeException($errors !== '' ? self::lastLine($errors) : "trivy exited with {$exitCode}");
            }

            $decoded = json_decode((string) @file_get_contents($report), true);
            if (!is_array($decoded)) {
                throw new \RuntimeException('trivy wrote no report');
            }

            return $decoded;
        } finally {
            self::remove($work);
        }
    }

    /**
     * Trivy logs a line per step; the last is the one that says what went wrong.
     */
    private static function lastLine(string $text): string {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text))));

        return mb_strimwidth(end($lines) ?: $text, 0, 2000, '…');
    }

    private static function remove(string $path): void {
        if (is_dir($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    self::remove("{$path}/{$entry}");
                }
            }
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }

}
