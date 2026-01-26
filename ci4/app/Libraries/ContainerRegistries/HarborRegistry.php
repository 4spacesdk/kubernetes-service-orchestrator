<?php namespace App\Libraries\ContainerRegistries;

use App\Entities\ContainerImage;
use DebugTool\Data;

class HarborRegistry extends BaseContainerRegistry {

    private ContainerImage $image;

    public function __construct(ContainerImage $image) {
        $this->image = $image;
    }

    public function getRepoName(): string {
        $projectAndRepo = substr($this->image->url, strlen($this->image->registry_provider_harbor_url) + 1);
        return substr($projectAndRepo, strpos($projectAndRepo, '/') + 1);
    }

    public function getProjectName(): string {
        $projectAndRepo = substr($this->image->url, strlen($this->image->registry_provider_harbor_url) + 1);
        return substr($projectAndRepo, 0, strpos($projectAndRepo, '/'));
    }

    public function getTags(): array {
        try {
            $ch = curl_init();
            $headers = [
                'accept: application/json',
                "authorization: Basic " . base64_encode("{$this->image->registry_provider_harbor_username}:{$this->image->registry_provider_harbor_password}"),
                'X-Accept-Vulnerabilities: application/vnd.security.vulnerability.report; version=1.1, application/vnd.scanner.adapter.vuln.report.harbor+json; version=1.0',
            ];
            $repoNameUrlEncoded = urlencode($this->getRepoName());
            $url = "https://{$this->image->registry_provider_harbor_url}/api/v2.0/projects/{$this->getProjectName()}/repositories/{$repoNameUrlEncoded}/artifacts?q=tags%3D*";
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $url);
            $response = curl_exec($ch);
            curl_close($ch);
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
                usort($items, fn($a, $b) => version_compare(str_replace('v', '', $a), str_replace('v', '', $b)));
                return $items;
            }

        } catch (\Exception $e) {
            Data::debug($e->getMessage());
        }

        return [];
    }

}
