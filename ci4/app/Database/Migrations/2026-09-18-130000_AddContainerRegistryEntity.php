<?php namespace App\Database\Migrations;

use App\Controllers\AutoUpdates;
use App\Controllers\ContainerRegistries;
use CodeIgniter\Database\Migration;
use Config\Database;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

/**
 * INT-1a. Registry credentials move off the container images into a connection of their
 * own, which the images point at.
 *
 * Existing images are grouped by provider and every credential field: one connection per
 * distinct set, so four images carrying the same key end up sharing one. A connection
 * receives events if any of its images did, since Pub/Sub and webhooks were per registry
 * all along. Images without a provider get no connection.
 */
class AddContainerRegistryEntity extends Migration {

    /**
     * Old column on container_images => new column on container_registries.
     */
    private const Moved = [
        'registry_provider' => 'provider',
        'registry_provider_gcloud_project' => 'gcloud_project',
        'registry_provider_gcloud_location' => 'gcloud_location',
        'registry_provider_gcloud_registry_name' => 'gcloud_registry_name',
        'registry_provider_gcloud_credentials' => 'gcloud_credentials',
        'registry_provider_azure_registry_name' => 'azure_registry_name',
        'registry_provider_azure_tenant' => 'azure_tenant',
        'registry_provider_azure_client_id' => 'azure_client_id',
        'registry_provider_azure_client_secret' => 'azure_client_secret',
        'registry_provider_harbor_url' => 'harbor_url',
        'registry_provider_harbor_username' => 'harbor_username',
        'registry_provider_harbor_password' => 'harbor_password',
    ];

    /**
     * With their defaults in the type. `Table::column()` drops an empty-string default, and
     * AddColumnDefaults only covered the tables that existed when it ran - an insert that
     * leaves a column out would otherwise be refused under strict mode. A TEXT column cannot
     * take a literal default in MySQL, so the key is nullable instead.
     */
    private const Text511 = "VARCHAR(511) NOT NULL DEFAULT ''";
    private const Text127 = "VARCHAR(127) NOT NULL DEFAULT ''";
    private const LongText = 'TEXT NULL';

    public function up() {
        Table::init('container_registries')
            ->create()
            ->column('name', self::Text511)
            ->column('provider', self::Text127)
            ->column('gcloud_project', self::Text511)
            ->column('gcloud_location', self::Text511)
            ->column('gcloud_registry_name', self::Text511)
            ->column('gcloud_credentials', self::LongText)
            ->column('azure_registry_name', self::Text511)
            ->column('azure_tenant', self::Text511)
            ->column('azure_client_id', self::Text511)
            ->column('azure_client_secret', self::Text511)
            ->column('azure_subscription_id', self::Text511)
            ->column('azure_resource_group', self::Text511)
            ->column('harbor_url', self::Text511)
            ->column('harbor_username', self::Text511)
            ->column('harbor_password', self::Text511)
            ->column('pull_username', self::Text511)
            ->column('pull_password', self::LongText)
            ->column('events_enabled', ColumnTypes::BOOL_0)
            ->column('webhook_secret', self::Text127)
            ->softDelete()
            ->timestamps();

        Table::init('container_images')
            ->column('container_registry_id', ColumnTypes::INT_NULL)->addIndex('container_registry_id');

        $this->groupCredentialsIntoConnections();

        $images = Table::init('container_images');
        foreach ([...array_keys(self::Moved), 'registry_subscribe'] as $column) {
            $images->dropColumn($column);
        }

        ApiRoute::addResourceControllerGet(ContainerRegistries::class);
        ApiRoute::addResourceControllerPost(ContainerRegistries::class);
        ApiRoute::addResourceControllerPatch(ContainerRegistries::class);
        ApiRoute::addResourceControllerDelete(ContainerRegistries::class);
        ApiRoute::quick('container-registries/([0-9]+)/test', ContainerRegistries::class, 'test/$1', 'get');
        ApiRoute::quick('container-registries/([0-9]+)/repositories', ContainerRegistries::class, 'getRepositories/$1', 'get');
        ApiRoute::quick('container-registries/([0-9]+)/setup-events', ContainerRegistries::class, 'setupEvents/$1', 'post');

        // INT-1c. The webhooks now carry the connection they belong to, and check its secret.
        // The old urls are removed rather than kept alongside: they checked nothing, and a
        // registry still calling one is told so by a 404 instead of being trusted.
        $db = Database::connect();
        $db->table('api_routes')->whereIn('from', [
            'auto-updates/webhooks/azure-container-registry',
            'auto-updates/webhooks/harbor',
        ])->delete();
        ApiRoute::public('auto-updates/webhooks/azure-container-registry/([0-9]+)', AutoUpdates::class, 'webhooksAzureContainerRegistry/$1', 'post');
        ApiRoute::public('auto-updates/webhooks/harbor/([0-9]+)', AutoUpdates::class, 'webhooksHarbor/$1', 'post');
        ApiRoute::quick('container-registries/([0-9]+)/import', ContainerRegistries::class, 'import/$1', 'post');
    }

    /**
     * Safe to run again: once the old columns are dropped there is nothing to group, and an
     * image that already has a connection - from a run that stopped halfway - is left alone.
     */
    private function groupCredentialsIntoConnections(): void {
        if (!Table::init('container_images')->hasColumn('registry_provider')) {
            return;
        }

        $db = Database::connect();
        $images = $db->table('container_images')
            ->where('registry_provider !=', '')
            ->where('container_registry_id', null)
            ->orderBy('id')
            ->get()->getResultArray();

        $connections = [];
        $names = [];
        foreach ($images as $image) {
            $values = [];
            foreach (self::Moved as $old => $new) {
                $values[$new] = (string) $image[$old];
            }
            $key = hash('sha256', json_encode($values));

            if (!isset($connections[$key])) {
                $name = self::nameFor($values);
                $names[$name] = ($names[$name] ?? 0) + 1;
                if ($names[$name] > 1) {
                    $name .= " ({$names[$name]})";
                }

                $db->table('container_registries')->insert([
                    ...$values,
                    'name' => $name,
                    'events_enabled' => 0,
                    'created' => date('Y-m-d H:i:s'),
                    'updated' => date('Y-m-d H:i:s'),
                ]);
                $connections[$key] = $db->insertID();
            }

            if ($image['registry_subscribe']) {
                $db->table('container_registries')->where('id', $connections[$key])->update(['events_enabled' => 1]);
            }
            $db->table('container_images')->where('id', $image['id'])->update(['container_registry_id' => $connections[$key]]);
        }
    }

    /**
     * What a person would call the registry: the place the images live.
     */
    private static function nameFor(array $values): string {
        return match ($values['provider']) {
            \ContainerRegistries::ArtifactContainerRegistry => "{$values['gcloud_project']}/{$values['gcloud_registry_name']}",
            \ContainerRegistries::AzureContainerRegistry => $values['azure_registry_name'],
            \ContainerRegistries::Harbor => $values['harbor_url'],
            default => $values['provider'],
        } ?: $values['provider'];
    }

    public function down() {

    }

}
