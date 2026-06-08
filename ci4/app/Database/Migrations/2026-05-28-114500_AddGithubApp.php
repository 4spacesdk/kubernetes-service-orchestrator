<?php namespace App\Database\Migrations;

use App\Controllers\GithubApp;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

class AddGithubApp extends Migration {

    public function up() {
        // Systems: add GitHub App configuration fields
        Table::init('systems')
            ->column('github_app_id', ColumnTypes::INT)
            ->column('github_app_client_id', ColumnTypes::VARCHAR_255)
            ->column('github_app_client_secret', ColumnTypes::VARCHAR_255)
            ->column('github_app_private_key', ColumnTypes::TEXT)
            ->column('github_app_webhook_secret', ColumnTypes::VARCHAR_255)
            ->column('github_app_slug', ColumnTypes::VARCHAR_255)
            ->column('github_app_installation_id', ColumnTypes::INT);

        ApiRoute::quick('githubapp/manifest', GithubApp::class, 'manifest', 'get');
        ApiRoute::public('githubapp/callback', GithubApp::class, 'callback', 'get');
        ApiRoute::public('githubapp/repositories', GithubApp::class, 'repositories', 'get');
        ApiRoute::public('githubapp/post-install', GithubApp::class, 'post_install', 'get');

        Table::init('container_images')
            ->dropColumn('version_control_provider_github_auth_token')
            ->dropColumn('version_control_provider_github_auth_user');
    }

    public function down() {
        Table::init('systems')
            ->dropColumn('github_app_id')
            ->dropColumn('github_app_client_id')
            ->dropColumn('github_app_client_secret')
            ->dropColumn('github_app_private_key')
            ->dropColumn('github_app_webhook_secret')
            ->dropColumn('github_app_slug')
            ->dropColumn('github_app_installation_id');
    }
}
