<?php namespace App\Database\Migrations;

use App\Controllers\GithubIntegrations;
use App\Entities\GithubIntegration;
use CodeIgniter\Database\Migration;
use Config\Database;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

/**
 * The GitHub App moves off the System row into integrations of its own, one per
 * organisation. A private GitHub App can only be installed on the account that owns it, so
 * one App for the whole instance meant one organisation.
 *
 * The App already connected becomes the first integration, and every image using GitHub
 * points at it. The callback and post-install urls stay where they are: the App stored at
 * GitHub still sends the browser there.
 */
class AddGithubIntegrationEntity extends Migration {

    /**
     * Old column on systems => new column on github_integrations.
     */
    private const Moved = [
        'github_app_id' => 'app_id',
        'github_app_client_id' => 'client_id',
        'github_app_client_secret' => 'client_secret',
        'github_app_private_key' => 'private_key',
        'github_app_webhook_secret' => 'webhook_secret',
        'github_app_slug' => 'slug',
        'github_app_installation_id' => 'installation_id',
    ];

    private const Text511 = "VARCHAR(511) NOT NULL DEFAULT ''";
    private const Text127 = "VARCHAR(127) NOT NULL DEFAULT ''";
    private const Int0 = 'INT NOT NULL DEFAULT 0';
    private const LongText = 'TEXT NULL';

    public function up() {
        Table::init('github_integrations')
            ->create()
            ->column('name', self::Text511)
            ->column('organization', self::Text127)
            ->column('app_id', self::Int0)
            ->column('client_id', self::Text127)
            ->column('client_secret', self::Text127)
            ->column('private_key', self::LongText)
            ->column('webhook_secret', self::Text127)
            ->column('slug', self::Text127)
            ->column('installation_id', self::Int0)
            ->column('setup_state', self::Text127)
            ->softDelete()
            ->timestamps();

        Table::init('container_images')
            ->column('github_integration_id', ColumnTypes::INT_NULL)->addIndex('github_integration_id');

        $this->moveTheConnectedApp();

        $systems = Table::init('systems');
        foreach (array_keys(self::Moved) as $column) {
            if ($systems->hasColumn($column)) {
                $systems->dropColumn($column);
            }
        }

        ApiRoute::addResourceControllerGet(GithubIntegrations::class);
        ApiRoute::addResourceControllerPost(GithubIntegrations::class);
        ApiRoute::addResourceControllerPatch(GithubIntegrations::class);
        ApiRoute::addResourceControllerDelete(GithubIntegrations::class);
        ApiRoute::quick('github-integrations/([0-9]+)/create-app', GithubIntegrations::class, 'createApp/$1', 'post');
        ApiRoute::quick('github-integrations/([0-9]+)/install', GithubIntegrations::class, 'install/$1', 'post');
        ApiRoute::quick('github-integrations/([0-9]+)/repositories', GithubIntegrations::class, 'getRepositories/$1', 'get');

        // The manifest and the repository listing move to the integration. `repositories`
        // was public and minted a token for any installation id it was given.
        Database::connect()->table('api_routes')->whereIn('from', [
            'githubapp/manifest',
            'githubapp/repositories',
        ])->delete();
    }

    /**
     * Safe to run again: once the columns are dropped there is nothing to move.
     */
    private function moveTheConnectedApp(): void {
        if (!Table::init('systems')->hasColumn('github_app_id')) {
            return;
        }

        $db = Database::connect();
        $system = $db->table('systems')->where('id', 1)->get()->getRowArray();
        if (!$system || !(int) $system['github_app_id']) {
            return;
        }

        $values = [];
        foreach (self::Moved as $old => $new) {
            $values[$new] = $system[$old] ?? '';
        }
        $values['app_id'] = (int) $values['app_id'];
        $values['installation_id'] = (int) $values['installation_id'];

        $db->table('github_integrations')->insert([
            ...$values,
            'name' => $values['slug'] ?: 'GitHub',
            'created' => date('Y-m-d H:i:s'),
            'updated' => date('Y-m-d H:i:s'),
        ]);
        $id = $db->insertID();

        // The organisation was never stored; GitHub knows it from the installation. Best
        // effort: a migration must not fail because GitHub could not be reached.
        $integration = new GithubIntegration();
        $integration->find($id);
        $integration->updateOrganizationFromGithub();

        $db->table('container_images')
            ->where('version_control_provider', \VersionControlProviders::GitHub)
            ->where('github_integration_id', null)
            ->update(['github_integration_id' => $id]);
    }

    public function down() {

    }

}
