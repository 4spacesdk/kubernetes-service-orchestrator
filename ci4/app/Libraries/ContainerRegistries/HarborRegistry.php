<?php namespace App\Libraries\ContainerRegistries;

use DebugTool\Data;

class HarborRegistry extends BaseContainerRegistry {

    public function getUrlPrefix(): string {
        return $this->registry->harbor_url;
    }

    /**
     * A webhook policy on every project the connection's images live in, called `kso`.
     * One that is already there is updated rather than duplicated, so this can be run
     * again - after an import into a new project, or to rotate the secret.
     *
     * @codeCoverageIgnore
     */
    public function setupEvents(string $webhookUrl, string $secret, array $imageUrls): string {
        $projects = array_values(array_unique(array_filter(array_map(fn ($url) => $this->getProjectName($url), $imageUrls))));
        if ($projects === []) {
            throw new \Exception('No images use this connection yet. Import or create them, then set up again.');
        }

        foreach ($projects as $project) {
            $path = '/api/v2.0/projects/' . rawurlencode($project) . '/webhook/policies';
            $policy = [
                'name' => 'kso',
                'description' => 'Tells kso about pushed tags. Managed by kso.',
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

            $existing = array_values(array_filter($this->get($path), fn ($p) => ($p['name'] ?? '') === 'kso'));
            if ($existing) {
                $this->send('PUT', "{$path}/{$existing[0]['id']}", $policy);
            } else {
                $this->send('POST', $path, $policy);
            }
        }

        return 'Webhook set up on ' . implode(', ', $projects);
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
        curl_setopt($ch, CURLOPT_URL, "https://{$this->registry->harbor_url}{$path}");
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
     * Harbor pages at most a hundred at a time.
     *
     * @codeCoverageIgnore
     */
    private function getAll(string $path): array {
        $items = [];
        for ($page = 1; ; $page++) {
            $batch = $this->get("{$path}?page_size=100&page={$page}");
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
        curl_setopt($ch, CURLOPT_URL, "https://{$this->registry->harbor_url}{$path}");
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
    public function getTags(string $url): array {
        try {
            $ch = curl_init();
            $headers = [
                'accept: application/json',
                "authorization: Basic " . base64_encode("{$this->registry->harbor_username}:{$this->registry->harbor_password}"),
                'X-Accept-Vulnerabilities: application/vnd.security.vulnerability.report; version=1.1, application/vnd.scanner.adapter.vuln.report.harbor+json; version=1.0',
            ];
            $repoNameUrlEncoded = urlencode($this->getRepoName($url));
            $apiUrl = "https://{$this->registry->harbor_url}/api/v2.0/projects/{$this->getProjectName($url)}/repositories/{$repoNameUrlEncoded}/artifacts?q=tags%3D*";
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            $response = curl_exec($ch);
            $artifacts = json_decode($response, true);

            if (isset($artifacts['errors'])) {
                Data::debug('failed to get tags');
                Data::debug($artifacts);
            } else {
                $items = [];
                foreach ($artifacts as $artifact) {
                    foreach ($artifact['tags'] as $tag) {
                        $items[] = $tag['name'];
                    }
                }
                return self::sortVersions($items);
            }

        } catch (\Exception $e) {
            Data::debug($e->getMessage());
        }

        return [];
    }

}
