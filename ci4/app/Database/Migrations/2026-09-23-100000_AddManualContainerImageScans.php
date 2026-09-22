<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use OrmExtension\Migration\Table;

/**
 * A scan asked for by hand, of a tag no deployment needs to run - an image to look at before
 * anything deploys it. The nightly scan leaves such a row for ImageScanner::ManualScansKeptFor
 * instead of removing it with the tags that stopped running.
 */
class AddManualContainerImageScans extends Migration {

    public function up() {
        Table::init('container_image_scans')
            ->column('is_manual', 'TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down() {

    }

}
