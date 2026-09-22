<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

/**
 * The deployment package is called a workspace template, in the database and the API as it
 * already was in the UI.
 *
 * - The tables and the columns that point at them. `RENAME COLUMN` keeps the type, the
 *   nullability and the default as they are; the indexes are named after their column, so
 *   they follow it. There are no foreign keys to carry along.
 * - The label junction table is `labels_workspace_templates`: the ORM names a many-to-many
 *   table after both tables, sorted.
 * - `api_routes`: the resource and the custom routes under their new paths, pointing at the
 *   `WorkspaceTemplates` controller.
 * - `audit_events`: the type is the entity's class name, and a relation or a change names
 *   classes and columns in `details`. Renamed too, so a template's history reads as one.
 *
 * Every step checks first, so running it again - `app:migrate` does - changes nothing.
 */
class RenameDeploymentPackagesToWorkspaceTemplates extends Migration {

    private const array Tables = [
        'deployment_packages' => 'workspace_templates',
        'deployment_package_deployment_specifications' => 'workspace_template_deployment_specifications',
        'deployment_package_environment_variables' => 'workspace_template_environment_variables',
        'deployment_package_ds_knative_min_scale_schedules' => 'workspace_template_ds_knative_min_scale_schedules',
        'deployment_packages_labels' => 'labels_workspace_templates',
    ];

    /**
     * Table, as it is named after the rename, and its column and index of the same name.
     */
    private const array Columns = [
        ['workspaces', 'deployment_package_id', 'workspace_template_id'],
        ['workspace_template_deployment_specifications', 'deployment_package_id', 'workspace_template_id'],
        ['workspace_template_environment_variables', 'deployment_package_id', 'workspace_template_id'],
        ['labels_workspace_templates', 'deployment_package_id', 'workspace_template_id'],
        ['workspace_template_ds_knative_min_scale_schedules', 'deployment_package_deployment_specification_id', 'workspace_template_deployment_specification_id'],
    ];

    private const array Classes = [
        'DeploymentPackageDeploymentSpecification' => 'WorkspaceTemplateDeploymentSpecification',
        'DeploymentPackageEnvironmentVariable' => 'WorkspaceTemplateEnvironmentVariable',
        'DeploymentPackage' => 'WorkspaceTemplate',
    ];

    public function up() {
        $db = Database::connect();

        foreach (self::Tables as $from => $to) {
            if ($db->tableExists($from, false) && !$db->tableExists($to, false)) {
                $db->query("RENAME TABLE `{$from}` TO `{$to}`");
            }
        }

        foreach (self::Columns as [$table, $from, $to]) {
            if ($this->hasColumn($table, $from)) {
                $db->query("ALTER TABLE `{$table}` RENAME COLUMN `{$from}` TO `{$to}`");
            }
            if ($this->hasIndex($table, $from)) {
                $db->query("ALTER TABLE `{$table}` RENAME INDEX `{$from}` TO `{$to}`");
            }
        }

        $routes = $db->table('api_routes')
            ->like('to', 'App\\Controllers\\DeploymentPackages::', 'after')
            ->get()->getResultArray();
        foreach ($routes as $route) {
            $db->table('api_routes')->where('id', $route['id'])->update([
                'from' => str_replace(['deployment_packages', 'deployment-packages'], ['workspace_templates', 'workspace-templates'], $route['from']),
                'to' => str_replace('\\DeploymentPackages::', '\\WorkspaceTemplates::', $route['to']),
            ]);
        }

        foreach (self::Classes as $from => $to) {
            $db->table('audit_events')->where('resource_type', $from)->update(['resource_type' => $to]);
        }
        $db->query(
            "UPDATE audit_events
                SET details = REPLACE(REPLACE(details, '\"DeploymentPackage', '\"WorkspaceTemplate'), 'deployment_package', 'workspace_template')
              WHERE details LIKE '%DeploymentPackage%' OR details LIKE '%deployment\\_package%'"
        );
    }

    private function hasColumn(string $table, string $column): bool {
        return Database::connect()->query(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        )->getNumRows() > 0;
    }

    private function hasIndex(string $table, string $index): bool {
        return Database::connect()->query(
            'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        )->getNumRows() > 0;
    }

    public function down() {

    }

}
