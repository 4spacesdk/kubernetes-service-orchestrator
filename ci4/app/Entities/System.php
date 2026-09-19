<?php namespace App\Entities;

use App\Core\Entity;
use App\Models\SystemModel;

/**
 * Class System
 * @package App\Entities
 * @property bool $is_network_nginx_ingress_supported
 * @property bool $is_network_istio_supported
 * @property bool $is_network_contour_supported
 * @property bool $is_network_gateway_api_supported
 * @property string $hosting_provider
 */
class System extends Entity {

    /**
     * The one System row. There is exactly one, and it has id 1.
     *
     * It is created once, when the table is empty - that is a fresh installation, and
     * somebody has to write the first row. A migration seeds it too, so in practice this
     * branch only runs on an installation older than that migration.
     *
     * **Rows present but none with id 1 means something is wrong**, and this says so
     * rather than papering over it. It used to create a row whenever id 1 was missing,
     * which turned a lost row into an installation that silently ran unconfigured and grew
     * the table by one row per request. The test database spent months in exactly that
     * state without anyone noticing.
     *
     * @throws \RuntimeException when the row is gone but the table is not empty
     */
    public static function Get(): System {
        $item = new System();
        $item->find(1);
        if ($item->exists()) {
            return $item;
        }

        $model = new SystemModel();
        if ($model->countAllResults() > 0) {
            throw new \RuntimeException(
                'The systems table has rows but none with id 1. kso reads its own '
                . 'configuration from that row, so it will not guess. Restore it, or '
                . 'move the surviving row to id 1.'
            );
        }

        // Fresh installation. The id is written explicitly: on a table that once had rows
        // the auto increment has moved on, and a row at id 2 or 530 would never be found
        // again by the lookup above.
        $model->db->table('systems')->insert([
            'id' => 1,
            'created' => date('Y-m-d H:i:s'),
        ]);

        $item = new System();
        $item->find(1);

        return $item;
    }

    /**
     * What an API response may carry of the System row. It held the GitHub App credentials
     * until they moved to `GithubIntegration`, and they went out over the API.
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
        ];
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|System[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
