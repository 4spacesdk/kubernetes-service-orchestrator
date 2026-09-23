<?php namespace App\Libraries\ContainerRegistries;

use App\Entities\System;
use App\Libraries\OutboundUrl;

class HarborRegistry extends BaseContainerRegistry {

    public function getUrlPrefix(): string {
        return $this->registry->harbor_url;
    }

    /**
     * A webhook policy on every project the connection's images live in, named after this kso -
     * `kso-<its host>`. One that is already there is updated rather than duplicated, so this can
     * be run again - after an import into a new project, or to rotate the secret.
     *
     * Every policy used to be called `kso`, and found again by that name, so two kso's using one
     * Harbor took the policy over from each other and only the last to set up heard of a push.
     * Which one is this kso's is now in its description - see `OwnPolicy()`.
     *
     * @codeCoverageIgnore
     */
    public function setupEvents(string $webhookUrl, string $secret, array $imageUrls): string {
        $projects = array_values(array_unique(array_filter(array_map(fn ($url) => $this->getProjectName($url), $imageUrls))));
        if ($projects === []) {
            throw new \Exception('No images use this connection yet. Import or create them, then set up again.');
        }

        $installationId = System::InstallationId();

        foreach ($projects as $project) {
            $path = '/api/v2.0/projects/' . rawurlencode($project) . '/webhook/policies';
            $policy = [
                'name' => self::PolicyName($webhookUrl),
                'description' => self::PolicyDescription($installationId),
                'enabled' => true,
                'event_types' => ['PUSH_ARTIFACT'],
                'targets' => [[
                    'type' => 'http',
                    'address' => $webhookUrl,
                    'auth_header' => "Bearer {$secret}",
                    'skip_cert_verify' => false,
                    'payload_format' => 'Default',
                ]],
            ];

            $existing = self::OwnPolicy($this->get($path), $installationId, $webhookUrl);
            if ($existing) {
                $this->send('PUT', "{$path}/{$existing['id']}", $policy);
            } else {
                $this->send('POST', $path, $policy);
            }
        }

        return 'Webhook ' . self::PolicyName($webhookUrl) . ' set up on ' . implode(', ', $projects);
    }

    /** What this kso's policy is called: after the host Harbor calls, so it can be told apart. */
    public static function PolicyName(string $webhookUrl): string {
        $host = parse_url($webhookUrl, PHP_URL_HOST);
        return $host ? "kso-{$host}" : 'kso';
    }

    public static function PolicyDescription(string $installationId): string {
        return "Tells kso about pushed tags. Managed by kso installation {$installationId}.";
    }

    /**
     * This kso's policy among a project's: the one its description names this installation in,
     * or - set up before that - the one called `kso` that calls this kso's own address. A `kso`
     * calling another address is another kso's, and left alone.
     *
     * @param list<array> $policies
     */
    public static function OwnPolicy(array $policies, string $installationId, string $webhookUrl): ?array {
        foreach ($policies as $policy) {
            if (str_contains((string) ($policy['description'] ?? ''), "installation {$installationId}")) {
                return $policy;
            }
        }
        foreach ($policies as $policy) {
            $addresses = array_column($policy['targets'] ?? [], 'address');
            if (($policy['name'] ?? '') === 'kso' && in_array($webhookUrl, $addresses, true)) {
                return $policy;
            }
        }
        return null;
    }

    /**
     * @codeCoverageIgnore
     * @throws \Exception
     */
    private function send(string $method, string $path, array $body): void {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'content-type: application/json',
            'authorization: Basic ' . base64_encode("{$this->registry->harbor_username}:{$this->registry->harbor_password}"),
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // The base url is typed by an operator; see OutboundUrl.
        OutboundUrl::Apply($ch, "https://{$this->registry->harbor_url}{$path}");
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($response === false || $status >= 400) {
            $json = json_decode((string) $response, true);
            throw new \Exception("Harbor answered {$status}: " . ($json['errors'][0]['message'] ?? curl_error($ch) ?: (string) $response));
        }
    }

    /**
     * The repository inside its project. Harbor takes a path there; only the first segment
     * after the host is the project.
     */
    public function getRepoName(string $url): string {
        $projectAndRepo = substr($url, strlen($this->registry->harbor_url) + 1);
        return substr($projectAndRepo, strpos($projectAndRepo, '/') + 1);
    }

    public function getProjectName(string $url): string {
        $projectAndRepo = substr($url, strlen($this->registry->harbor_url) + 1);
        return substr($projectAndRepo, 0, strpos($projectAndRepo, '/'));
    }

    /**
     * Every repository in every project the account can see. Harbor names a repository
     * with its project in front, which is also what follows the host in an image url.
     *
     * @codeCoverageIgnore
     */
    public function listRepositories(): array {
        $items = [];
        foreach ($this->getAll('/api/v2.0/projects') as $project) {
            foreach ($this->getAll('/api/v2.0/projects/' . rawurlencode($project['name']) . '/repositories') as $repository) {
                $items[] = $this->repository($repository['name']);
            }
        }
        return $items;
    }

    /**
     * @return array{name: string, url: string}
     */
    public function repository(string $name): array {
        return ['name' => $name, 'url' => "{$this->registry->harbor_url}/{$name}"];
    }

    /**
     * Harbor pages at most a hundred at a time, and ten when not told otherwise.
     *
     * @codeCoverageIgnore
     */
    private function getAll(string $path): array {
        $separator = str_contains($path, '?') ? '&' : '?';
        $items = [];
        for ($page = 1; ; $page++) {
            $batch = $this->get("{$path}{$separator}page_size=100&page={$page}");
            $items = [...$items, ...$batch];
            if (count($batch) < 100) {
                return $items;
            }
        }
    }

    /**
     * @codeCoverageIgnore
     */
    public function testConnection(): string {
        $projects = $this->get('/api/v2.0/projects?page_size=100');
        return 'Found ' . count($projects) . ' project' . (count($projects) === 1 ? '' : 's');
    }

    /**
     * @codeCoverageIgnore
     * @throws \Exception
     */
    private function get(string $path): array {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'authorization: Basic ' . base64_encode("{$this->registry->harbor_username}:{$this->registry->harbor_password}"),
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // The base url is typed by an operator; see OutboundUrl.
        OutboundUrl::Apply($ch, "https://{$this->registry->harbor_url}{$path}");
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $json = json_decode((string) $response, true);
        if ($response === false || $status >= 400 || !is_array($json)) {
            throw new \Exception("Harbor answered {$status}: " . ($json['errors'][0]['message'] ?? curl_error($ch) ?: (string) $response));
        }
        return $json;
    }

    /**
     * Not measured: this is the network call itself. What kso decides before and after
     * it is tested through the fake behind `BaseContainerRegistry` - see the strategy note in the
     * test setup. Marking it keeps the coverage number about code we chose to test.
     *
     * @codeCoverageIgnore
     */
    protected function fetchTagDetails(string $url): array {
        $path = '/api/v2.0/projects/' . rawurlencode($this->getProjectName($url))
            . '/repositories/' . urlencode($this->getRepoName($url))
            . '/artifacts?q=tags%3D*';

        $items = [];
        foreach ($this->getAll($path) as $artifact) {
            foreach ($artifact['tags'] ?? [] as $tag) {
                $items[] = [
                    'name' => $tag['name'],
                    'pushed_at' => self::isoTime($tag['push_time'] ?? $artifact['push_time'] ?? null),
                ];
            }
        }
        return $items;
    }

}
