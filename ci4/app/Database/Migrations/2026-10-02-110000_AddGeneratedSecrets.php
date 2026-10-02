<?php namespace App\Database\Migrations;

use App\Controllers\Deployments;
use App\Controllers\Workspaces;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

/**
 * Secrets kso makes itself: `${secret.<name>}` belongs to a deployment, `${workspace.secret.<name>}`
 * to a workspace - see `GeneratedSecrets`. A value is made the first time it is looked up and kept,
 * encrypted, one per owner and name, with the recipe it was made by (`SecretRecipe`). A rotated one
 * has no value until the next deploy makes it anew.
 *
 * Variables already written with either placeholder would change meaning: they were sent as they
 * stood, and are filled in from now on. None did in dev on 2026-10-02; the count below says the
 * same of the installation it runs in.
 */
class AddGeneratedSecrets extends Migration {

    private const array OwnerTables = [
        'deployment_secrets' => 'deployment_id',
        'workspace_secrets' => 'workspace_id',
    ];

    private const array VariableTables = [
        'environment_variables',
        'deployment_specification_environment_variables',
        'init_container_environment_variables',
        'workspace_template_environment_variables',
    ];

    public function up() {
        foreach (self::OwnerTables as $table => $owner) {
            Table::init($table)
                ->create()
                ->column($owner, ColumnTypes::INT_NOT_NULL)
                ->column('name', "VARCHAR(127) NOT NULL DEFAULT ''")
                ->column('value', 'TEXT NULL')
                ->column('recipe', 'VARCHAR(63) NULL')
                ->column('rotated', ColumnTypes::DATETIME)
                ->timestamps();
            // One value per owner and name, however many deploys ask at once.
            $this->db->query("ALTER TABLE `{$table}` ADD UNIQUE KEY `owner_name` (`{$owner}`, `name`)");
        }

        foreach (self::VariableTables as $table) {
            $count = $this->db->table($table)
                ->groupStart()
                    ->like('value', '${secret.')
                    ->orLike('value', '${workspace.secret.')
                ->groupEnd()
                ->countAllResults();
            if ($count > 0 && is_cli()) {
                CLI::write("{$count} value(s) in {$table} already contain \${secret. or \${workspace.secret. - they are filled in with generated secrets from now on", 'yellow');
            }
        }

        ApiRoute::quick('/deployments/([0-9]+)/secrets', Deployments::class, 'getSecrets/$1', 'get');
        ApiRoute::quick('/deployments/([0-9]+)/secrets/rotate', Deployments::class, 'rotateSecret/$1', 'put');
        ApiRoute::quick('/deployments/([0-9]+)/secrets/reveal', Deployments::class, 'revealSecret/$1', 'put');
        ApiRoute::quick('/workspaces/([0-9]+)/secrets', Workspaces::class, 'getSecrets/$1', 'get');
        ApiRoute::quick('/workspaces/([0-9]+)/secrets/rotate', Workspaces::class, 'rotateSecret/$1', 'put');
        ApiRoute::quick('/workspaces/([0-9]+)/secrets/reveal', Workspaces::class, 'revealSecret/$1', 'put');
    }

    public function down() {

    }

}
