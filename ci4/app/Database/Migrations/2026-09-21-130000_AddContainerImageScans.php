<?php namespace App\Database\Migrations;

use App\Controllers\ContainerImages;
use App\Controllers\ContainerImageScans;
use CodeIgniter\Database\Migration;
use Config\Database;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

/**
 * What Trivy found in the customers' images: one row per image and tag that a deployment
 * runs, overwritten by each scan.
 *
 * Two cron jobs: a nightly scan of every tag that is running - new vulnerabilities are
 * published for images that have not changed, which is the point - and a job every minute
 * that picks up the scans the "Scan now" button queued, so the button needs no process
 * started from a web request.
 */
class AddContainerImageScans extends Migration {

    public function up() {
        Table::init('container_image_scans')
            ->create()
            ->column('container_image_id', ColumnTypes::INT_NOT_NULL)
            ->column('tag', "VARCHAR(255) NOT NULL DEFAULT ''")
            ->column('image_reference', "VARCHAR(1023) NOT NULL DEFAULT ''")
            ->column('status', "VARCHAR(27) NOT NULL DEFAULT 'queued'")
            ->column('error', 'TEXT NULL')
            ->column('digest', 'VARCHAR(255) NULL')
            ->column('operating_system', 'VARCHAR(127) NULL')
            ->column('targets', 'INT NOT NULL DEFAULT 0')
            ->column('critical', 'INT NOT NULL DEFAULT 0')
            ->column('high', 'INT NOT NULL DEFAULT 0')
            ->column('medium', 'INT NOT NULL DEFAULT 0')
            ->column('low', 'INT NOT NULL DEFAULT 0')
            ->column('unknown', 'INT NOT NULL DEFAULT 0')
            ->column('findings', 'MEDIUMTEXT NULL')
            ->column('scanned_at', ColumnTypes::DATETIME)
            ->timestamps()
            ->addIndex('container_image_id_tag', 'container_image_id', 'tag')
            ->addIndex('status');

        ApiRoute::addResourceControllerGet(ContainerImageScans::class);
        ApiRoute::quick('container-images/([0-9]+)/scan', ContainerImages::class, 'scan/$1', 'put');

        $db = Database::connect();
        foreach ([
            [\CronJobIds::ScanContainerImages, 'app:scan-container-images', '30 2 * * *', 'app:scan-container-images'],
            [\CronJobIds::ScanQueuedContainerImages, 'app:scan-container-images queued', '* * * * *', 'app:scan-container-images queued'],
        ] as [$id, $name, $schedule, $command]) {
            if ($db->table('cron_jobs')->where('id', $id)->countAllResults() > 0) {
                continue;
            }
            $db->table('cron_jobs')->insert([
                'id' => $id,
                'name' => $name,
                'schedule' => $schedule,
                'command' => $command,
                'duplicates' => 1,
            ]);
        }
    }

    public function down() {

    }

}
