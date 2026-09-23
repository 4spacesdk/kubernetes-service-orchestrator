<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use OrmExtension\Migration\Table;

/**
 * Which kso installation this is - see `System::InstallationId()`. Made here for the installation
 * being upgraded, so it is there before the first deploy marks anything with it.
 */
class AddInstallationId extends Migration {

    public function up() {
        Table::init('systems')
            ->column('installation_id', 'VARCHAR(32)');

        $this->db->table('systems')
            ->where('id', 1)
            ->groupStart()->where('installation_id', null)->orWhere('installation_id', '')->groupEnd()
            ->update(['installation_id' => bin2hex(random_bytes(16))]);
    }

    public function down() {

    }

}
