<?php namespace App\Database\Migrations;

use App\Controllers\ContainerImageScanRecords;
use CodeIgniter\Database\Migration;
use Config\Database;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

/**
 * The counts of every scan that succeeded, kept for a year, so the vulnerabilities of a tag
 * can be followed over time. container_image_scans only has the latest.
 *
 * Starts with the scans already made.
 */
class AddContainerImageScanRecords extends Migration {

    public function up() {
        Table::init('container_image_scan_records')
            ->create()
            ->column('container_image_id', ColumnTypes::INT_NOT_NULL)
            ->column('tag', "VARCHAR(255) NOT NULL DEFAULT ''")
            ->column('digest', 'VARCHAR(255) NULL')
            ->column('critical', 'INT NOT NULL DEFAULT 0')
            ->column('high', 'INT NOT NULL DEFAULT 0')
            ->column('medium', 'INT NOT NULL DEFAULT 0')
            ->column('low', 'INT NOT NULL DEFAULT 0')
            ->column('unknown', 'INT NOT NULL DEFAULT 0')
            ->column('scanned_at', ColumnTypes::DATETIME)
            ->timestamps()
            ->addIndex('container_image_id_tag_scanned_at', 'container_image_id', 'tag', 'scanned_at')
            ->addIndex('scanned_at');

        ApiRoute::addResourceControllerGet(ContainerImageScanRecords::class);

        $db = Database::connect();
        if ($db->table('container_image_scan_records')->countAllResults() === 0) {
            $db->query(
                'INSERT INTO container_image_scan_records
                    (container_image_id, tag, digest, critical, high, medium, low, unknown, scanned_at, created, updated)
                 SELECT container_image_id, tag, digest, critical, high, medium, low, unknown, scanned_at, NOW(), NOW()
                 FROM container_image_scans
                 WHERE status = ? AND scanned_at IS NOT NULL',
                [\ContainerImageScanStatuses::Scanned]
            );
        }
    }

    public function down() {

    }

}
