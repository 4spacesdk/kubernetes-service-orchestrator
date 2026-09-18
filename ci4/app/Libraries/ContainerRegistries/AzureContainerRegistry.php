<?php namespace App\Libraries\ContainerRegistries;

use DebugTool\Data;

class AzureContainerRegistry extends BaseContainerRegistry {

    public function getUrlPrefix(): string {
        return $this->registry->azure_registry_name;
    }

    /**
     * A webhook on the registry, created through Azure's management api - the registry's
     * own api cannot. Needs the subscription and resource group, and a role on the registry
     * that may write webhooks. Named after the connection, so running it again updates the
     * same webhook.
     *
     * @codeCoverageIgnore
     */
    public function setupEvents(string $webhookUrl, string $secret, array $imageUrls): string {
        if (!strlen($this->registry->azure_subscription_id) || !strlen($this->registry->azure_resource_group)) {
            throw new \Exception('Setting up the webhook needs the subscription id and the resource group.');
        }

        // The resource name is the login server's first label: acme.azurecr.io is acme.
        $name = explode('.', $this->registry->azure_registry_name)[0];
        $registryPath = "https://management.azure.com/subscriptions/{$this->registry->azure_subscription_id}"
            . "/resourceGroups/{$this->registry->azure_resource_group}"
            . "/providers/Microsoft.ContainerRegistry/registries/{$name}";
        $token = $this->getAzureAccessToken();

        $registry = $this->management('GET', "{$registryPath}?api-version=2023-07-01", $token);
        $webhookName = "kso{$this->registry->id}";
        $this->management('PUT', "{$registryPath}/webhooks/{$webhookName}?api-version=2023-07-01", $token, [
            'location' => $registry['location'],
            'properties' => [
                'serviceUri' => $webhookUrl,
                'customHeaders' => ['Authorization' => "Bearer {$secret}"],
                'status' => 'enabled',
                'actions' => ['push'],
                'scope' => '',
            ],
        ]);

        return "Webhook {$webhookName} set up on {$name}";
    }

    /**
     * @codeCoverageIgnore
     * @throws \Exception
     */
    private function management(string $method, string $url, string $token, ?array $body = null): array {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$token}", 'Content-Type: application/json']);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $json = json_decode((string) $response, true);
        if ($response === false || $status >= 400 || !is_array($json)) {
            throw new \Exception("Azure answered {$status}: " . ($json['error']['message'] ?? curl_error($ch) ?: (string) $response));
        }
        return $json;
    }

    public function getRepoName(string $url): string {
        return substr($url, strlen($this->registry->azure_registry_name) + 1);
    }

    /**
     * The registry's catalog. Needs a token for `registry:catalog:*`, which a principal that
     * can read tags does not necessarily have - the error from Azure says so.
     *
     * @codeCoverageIgnore
     */
    public function listRepositories(): array {
        $token = $this->getRegistryAccessToken($this->getRegistryRefreshToken($this->getAzureAccessToken()), null);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$token}"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, "https://{$this->registry->azure_registry_name}/acr/v1/_catalog?n=1000");
        $response = curl_exec($ch);
        $json = json_decode((string) $response, true);
        if (!is_array($json) || !isset($json['repositories'])) {
            throw new \Exception('Azure refused to list the repositories: ' . ($json['errors'][0]['message'] ?? (string) $response));
        }

        return array_map(fn ($name) => $this->repository($name), $json['repositories']);
    }

    /**
     * @return array{name: string, url: string}
     */
    public function repository(string $name): array {
        return ['name' => $name, 'url' => "{$this->registry->azure_registry_name}/{$name}"];
    }

    /**
     * @codeCoverageIgnore
     */
    public function testConnection(): string {
        $this->getRegistryRefreshToken($this->getAzureAccessToken());
        return "Signed in to {$this->registry->azure_registry_name}";
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
            $azureAccessToken = $this->getAzureAccessToken();
            $registryRefreshToken = $this->getRegistryRefreshToken($azureAccessToken);
            $registryAccessToken = $this->getRegistryAccessToken($registryRefreshToken, $this->getRepoName($url));

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$registryAccessToken}",
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, "https://{$this->registry->azure_registry_name}/acr/v1/{$this->getRepoName($url)}/_tags");
            $response = curl_exec($ch);
            $json = json_decode($response, true);
            if ($json && isset($json['tags'])) {

                $items = [];
                foreach ($json['tags'] as $tag) {
                    $items[] = $tag['name'];
                }
                return self::sortVersions($items);
            } else {
                Data::debug('failed to get tags');
                Data::debug($response);
            }
        } catch (\Exception $e) {
            Data::debug($e->getMessage());
        }

        return [];
    }

    /**
     * @throws \Exception
     */
    /**
     * Not measured: this is the network call itself. What kso decides before and after
     * it is tested through the fake behind `BaseContainerRegistry` - see the strategy note in the
     * test setup. Marking it keeps the coverage number about code we chose to test.
     *
     * @codeCoverageIgnore
     */
    private function getAzureAccessToken(): string {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, "https://login.microsoftonline.com/{$this->registry->azure_tenant}/oauth2/v2.0/token");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'client_id' => $this->registry->azure_client_id,
            'client_secret' => $this->registry->azure_client_secret,
            'grant_type' => 'client_credentials',
            'scope' => 'https://management.azure.com/.default',
        ]);
        $response = curl_exec($ch);
        $json = json_decode($response, true);
        if ($json && isset($json['access_token'])) {
            return $json['access_token'];
        } else {
            Data::debug($response);
            throw new \Exception('Failed to get azure access token');
        }
    }

    /**
     * @throws \Exception
     */
    /**
     * Not measured: this is the network call itself. What kso decides before and after
     * it is tested through the fake behind `BaseContainerRegistry` - see the strategy note in the
     * test setup. Marking it keeps the coverage number about code we chose to test.
     *
     * @codeCoverageIgnore
     */
    private function getRegistryRefreshToken(string $azureAccessToken): string {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/x-www-form-urlencoded",
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, "https://{$this->registry->azure_registry_name}/oauth2/exchange");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'access_token',
            'service' => $this->registry->azure_registry_name,
            'tenant' => $this->registry->azure_tenant,
            'access_token' => $azureAccessToken,
        ]));
        $response = curl_exec($ch);
        $json = json_decode($response, true);
        if ($json && isset($json['refresh_token'])) {
            return $json['refresh_token'];
        } else {
            Data::debug($response);
            throw new \Exception('Failed to exchange azure access token for registry refresh token');
        }
    }

    /**
     * @throws \Exception
     */
    /**
     * Not measured: this is the network call itself. What kso decides before and after
     * it is tested through the fake behind `BaseContainerRegistry` - see the strategy note in the
     * test setup. Marking it keeps the coverage number about code we chose to test.
     *
     * @codeCoverageIgnore
     */
    private function getRegistryAccessToken(string $registryRefreshToken, ?string $repoName): string {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/x-www-form-urlencoded",
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, "https://{$this->registry->azure_registry_name}/oauth2/token");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'refresh_token' => $registryRefreshToken,
            'service' => $this->registry->azure_registry_name,
            'grant_type' => 'refresh_token',
            'scope' => $repoName === null ? 'registry:catalog:*' : "repository:{$repoName}:*",
        ]));
        $response = curl_exec($ch);
        $json = json_decode($response, true);
        if ($json && isset($json['access_token'])) {
            return $json['access_token'];
        } else {
            Data::debug($response);
            throw new \Exception('Failed to exchange registry refresh token for registry access token');
        }
    }

}
