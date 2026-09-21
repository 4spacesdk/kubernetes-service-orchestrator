<?php namespace App\Database\Migrations;

use App\Libraries\Crypt;
use CodeIgniter\Database\Migration;
use Config\Database;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;

/**
 * Environment variables that can be marked secret, and every value encrypted where it is
 * stored.
 *
 * Every value, not only the ones marked: a password typed straight into a variable, before
 * there was anything to mark, is protected all the same, and the entities encrypt one column
 * rather than deciding row by row.
 *
 * `environment_variables.value` is widened first - ciphertext is longer than what it
 * replaces, and it was `varchar(511)`. The rows are encrypted through the query builder, as in
 * EncryptStoredCredentials, and running twice leaves an encrypted value alone.
 */
class AddSecretEnvironmentVariables extends Migration {

    private const array Tables = [
        'environment_variables',
        'deployment_specification_environment_variables',
        'deployment_package_environment_variables',
        'init_container_environment_variables',
    ];

    public function up() {
        $this->forge->modifyColumn('environment_variables', [
            'value' => ['name' => 'value', 'type' => 'TEXT', 'null' => true],
        ]);

        $db = Database::connect();
        foreach (self::Tables as $table) {
            Table::init($table)
                ->column('is_secret', ColumnTypes::BOOL_0);

            foreach ($db->table($table)->select(['id', 'value'])->get()->getResultArray() as $row) {
                $value = (string) ($row['value'] ?? '');
                if ($value === '' || Crypt::IsEncrypted($value)) {
                    continue;
                }
                $db->table($table)->where('id', $row['id'])->update(['value' => Crypt::Encrypt($value)]);
            }
        }
    }

    public function down() {

    }

}
