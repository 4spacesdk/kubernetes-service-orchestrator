<?php namespace App\Database\Migrations;

use App\Entities\DeploymentSpecification;
use App\Models\ContainerImageModel;
use App\Models\DeploymentSpecificationModel;
use CodeIgniter\Database\Migration;
use Config\Database;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

class AddContainerImageCommitIdentification extends Migration {

    public function up() {
        Table::init('container_images')
            ->column('commit_identification_enabled', ColumnTypes::BOOL_0)
            ->column('commit_identification_method', ColumnTypes::VARCHAR_63)
            ->column('commit_identification_environment_variable_name', ColumnTypes::VARCHAR_511)

            ->column('version_control_enabled', ColumnTypes::BOOL_0)
            ->column('version_control_provider', ColumnTypes::VARCHAR_63)
            ->column('version_control_repository_name', ColumnTypes::VARCHAR_511)
            ->column('version_control_provider_github_auth_token', ColumnTypes::VARCHAR_511)
            ->column('version_control_provider_github_auth_user', ColumnTypes::VARCHAR_511);

        Database::connect()
            ->table('container_images')
            ->set('commit_identification_enabled', 1)
            ->set('commit_identification_method', \CommitIdentificationMethods::EnvironmentVariable)
            ->set('commit_identification_environment_variable_name', 'SHORT_SHA')
            ->set('version_control_enabled', 1)
            ->set('version_control_provider', \VersionControlProviders::GitHub)
            ->update();

        if (getenv('GITHUB_AUTH_USER') && strlen(getenv('GITHUB_AUTH_USER'))
            && getenv('GITHUB_AUTH_TOKEN') && strlen(getenv('GITHUB_AUTH_TOKEN'))) {

            Database::connect()
                ->table('container_images')
                ->set('version_control_provider_github_auth_token', getenv('GITHUB_AUTH_TOKEN'))
                ->set('version_control_provider_github_auth_user', getenv('GITHUB_AUTH_USER'))
                ->update();
        }

        // Remove after release
        /** @var DeploymentSpecification $specs */
        $specs = (new DeploymentSpecificationModel())
            ->includeRelated(ContainerImageModel::class)
            ->find();
        foreach ($specs as $spec) {
            if ($spec->container_image->exists()) {
                $spec->container_image->version_control_repository_name = $spec->git_repo;
                $spec->container_image->save();
            }
        }
    }

    public function down() {

    }

}
