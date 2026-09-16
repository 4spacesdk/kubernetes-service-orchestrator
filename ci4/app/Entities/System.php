<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class System
 * @package App\Entities
 * @property bool $is_network_nginx_ingress_supported
 * @property bool $is_network_istio_supported
 * @property bool $is_network_contour_supported
 * @property bool $is_network_gateway_api_supported
 * @property string $hosting_provider
 * @property int $github_app_id
 * @property string $github_app_client_id
 * @property string $github_app_client_secret
 * @property string $github_app_private_key
 * @property string $github_app_webhook_secret
 * @property string $github_app_slug
 * @property int $github_app_installation_id
 */
class System extends Entity {

    public static function Get(): System {
        $item = new System();
        $item->find(1);
        if (!$item->exists()) {
            $item->created = date('Y-m-d H:i:s');
            $item->save();
        }
        return $item;
    }

    /**
     * The System entity holds the GitHub App credentials: private key, client secret
     * and webhook secret. They must never leave the server.
     *
     * Use this instead of toArray() whenever a System is put into an API response.
     * It is an allow list, so a credential added to the entity later stays out of
     * responses until someone deliberately lists it here.
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(): array {
        // Cast explicitly. Reading a property gives the raw database value, so a boolean
        // column arrives as the string "0", which is truthy once it reaches the browser.
        return [
            'id' => (int)$this->id,

            // Which network types the UI offers when creating domains and specifications
            'is_network_nginx_ingress_supported' => (bool)$this->is_network_nginx_ingress_supported,
            'is_network_istio_supported' => (bool)$this->is_network_istio_supported,
            'is_network_contour_supported' => (bool)$this->is_network_contour_supported,
            'is_network_gateway_api_supported' => (bool)$this->is_network_gateway_api_supported,

            // Enables provider specific options, such as HealthCheckPolicy on GKE
            'hosting_provider' => (string)$this->hosting_provider,

            // Identifiers, not credentials. The System page shows them, and the container
            // image dialog uses the installation id to list repositories.
            'github_app_id' => (int)$this->github_app_id,
            'github_app_slug' => (string)$this->github_app_slug,
            'github_app_installation_id' => (int)$this->github_app_installation_id,
        ];
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|System[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
