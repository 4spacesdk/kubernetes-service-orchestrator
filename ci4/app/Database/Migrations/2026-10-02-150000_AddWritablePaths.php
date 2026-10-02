<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use OrmExtension\Migration\Table;

/**
 * Where a container with a read-only root filesystem may still write - see `WritablePaths`. The
 * image's paths, read from its OCI label or set by hand; the specification's own on top, and the
 * size of each `emptyDir`. Empty for everything there is: nothing changes until an image says
 * where it writes and is made read-only.
 */
class AddWritablePaths extends Migration {

    public function up() {
        Table::init('container_images')
            ->column('writable_paths', 'TEXT NULL');

        Table::init('deployment_specifications')
            ->column('writable_paths', 'TEXT NULL')
            ->column('writable_paths_size_limit', 'VARCHAR(27) NULL');
    }

    public function down() {

    }

}
