<?php namespace App\Libraries\ContainerRegistries;

use App\Entities\ContainerImage;
use DebugTool\Data;

class AzureContainerRegistry extends BaseContainerRegistry {

    private ContainerImage $image;

    public function __construct(ContainerImage $image) {
        $this->image = $image;
    }

    public function getRepoName(): string {
        return substr($this->image->url, strlen($this->image->registry_provider_azure_registry_name) + 1);
    }

    public function getTags(): array {
        try {
            $azureAccessToken = $this->getAzureAccessToken();
            $registryRefreshToken = $this->getRegistryRefreshToken($azureAccessToken);
            $registryAccessToken = $this->getRegistryAccessToken($registryRefreshToken);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$registryAccessToken}",
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, "https://{$this->image->registry_provider_azure_registry_name}/acr/v1/{$this->image->getRepoName()}/_tags");
            $response = curl_exec($ch);
            curl_close($ch);
            $json = json_decode($response, true);
            if ($json && isset($json['tags'])) {

                $items = [];
                foreach ($json['tags'] as $tag) {
                    $items[] = $tag['name'];
                }
                usort($items, fn($a, $b) => version_compare(str_replace('v', '', $a), str_replace('v', '', $b)));

                return $items;
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
    private function getAzureAccessToken(): string {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, "https://login.microsoftonline.com/{$this->image->registry_provider_azure_tenant}/oauth2/v2.0/token");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'client_id' => $this->image->registry_provider_azure_client_id,
            'client_secret' => $this->image->registry_provider_azure_client_secret,
            'grant_type' => 'client_credentials',
            'scope' => 'https://management.azure.com/.default',
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
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
    private function getRegistryRefreshToken(string $azureAccessToken): string {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/x-www-form-urlencoded",
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, "https://{$this->image->registry_provider_azure_registry_name}/oauth2/exchange");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'access_token',
            'service' => $this->image->registry_provider_azure_registry_name,
            'tenant' => $this->image->registry_provider_azure_tenant,
            'access_token' => $azureAccessToken,
        ]));
        $response = curl_exec($ch);
        curl_close($ch);
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
    private function getRegistryAccessToken(string $registryRefreshToken): string {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/x-www-form-urlencoded",
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, "https://{$this->image->registry_provider_azure_registry_name}/oauth2/token");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'refresh_token' => $registryRefreshToken,
            'service' => $this->image->registry_provider_azure_registry_name,
            'grant_type' => 'refresh_token',
            'scope' => "repository:{$this->image->getRepoName()}:*",
        ]));
        $response = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($response, true);
        if ($json && isset($json['access_token'])) {
            return $json['access_token'];
        } else {
            Data::debug($response);
            throw new \Exception('Failed to exchange registry refresh token for registry access token');
        }
    }

}
