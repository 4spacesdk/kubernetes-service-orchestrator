<?php namespace App\Database\Migrations;

use App\Controllers\DeletedSecrets;
use App\Controllers\Deployments;
use App\Controllers\Workspaces;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

/**
 * A generated secret keeps the values it had - see `GeneratedSecrets`. A rotation forgot the value
 * at once, and a deleted deployment or workspace took its secrets with it, so a key data was
 * encrypted with was gone for good. The secret's row keeps the current value as before; every
 * value it no longer has is a row in `*_secret_versions`, and nothing is ever written over.
 *
 * * `version` counts the values, `origin` says where the current one came from, `made` when.
 * * `deleted`, `owner_name` and `owner_namespace`: its owner was deleted, and this is who that
 *   was - the owner's row is gone.
 */
class AddGeneratedSecretVersions extends Migration {

    private const array OwnerTables = [
        'deployment_secrets' => ['deployment_secret_versions', 'deployment_id'],
        'workspace_secrets' => ['workspace_secret_versions', 'workspace_id'],
    ];

    public function up() {
        foreach (self::OwnerTables as $table => [$versions, $owner]) {
            Table::init($table)
                ->column('version', 'INT NOT NULL DEFAULT 1')
                ->column('origin', "VARCHAR(63) NOT NULL DEFAULT 'made'")
                ->column('made', ColumnTypes::DATETIME)
                ->column('deleted', ColumnTypes::DATETIME)
                ->column('owner_name', 'VARCHAR(255) NULL')
                ->column('owner_namespace', 'VARCHAR(255) NULL');
            // The current value was made when the row was last written: at first, or after a rotation.
            $this->db->query("UPDATE `{$table}` SET `made` = COALESCE(`updated`, `created`) WHERE `value` IS NOT NULL AND `made` IS NULL");

            Table::init($versions)
                ->create()
                ->column($owner, ColumnTypes::INT_NOT_NULL)
                ->column('name', "VARCHAR(127) NOT NULL DEFAULT ''")
                ->column('version', ColumnTypes::INT_NOT_NULL)
                ->column('value', 'TEXT NULL')
                ->column('recipe', 'VARCHAR(63) NULL')
                ->column('origin', "VARCHAR(63) NOT NULL DEFAULT 'made'")
                ->column('made', ColumnTypes::DATETIME)
                ->column('retired', ColumnTypes::DATETIME)
                ->timestamps();
            $this->db->query("ALTER TABLE `{$versions}` ADD UNIQUE KEY `owner_name_version` (`{$owner}`, `name`, `version`)");
        }

        ApiRoute::quick('/deployments/([0-9]+)/secrets/restore', Deployments::class, 'restoreSecret/$1', 'put');
        ApiRoute::quick('/deployments/([0-9]+)/secrets/set', Deployments::class, 'setSecret/$1', 'put');
        ApiRoute::quick('/workspaces/([0-9]+)/secrets/restore', Workspaces::class, 'restoreSecret/$1', 'put');
        ApiRoute::quick('/workspaces/([0-9]+)/secrets/set', Workspaces::class, 'setSecret/$1', 'put');
        ApiRoute::quick('/deleted-secrets', DeletedSecrets::class, 'getDeleted', 'get');
        ApiRoute::quick('/deleted-secrets/reveal', DeletedSecrets::class, 'reveal', 'put');
        ApiRoute::quick('/deleted-secrets/purge', DeletedSecrets::class, 'purge', 'put');
    }

    public function down() {

    }

}
