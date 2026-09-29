<?php namespace App\Database\Migrations;

use App\Controllers\ContainerImages;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

/**
 * Security a container image gets when it is made, which its specifications inherit and may
 * override - see `SecurityContext`.
 *
 * * On the image, `run_as_non_root`, `drop_all_capabilities` and `seccomp_runtime_default`, off
 *   for every image there is: an image that runs as root today still starts. A new image is
 *   stamped when it is made, from the `USER` its registry says it runs as (`image_user`, read
 *   from `image_user_tag`) - and again whenever somebody reads it anew.
 * * On the specification, the same three as `SecurityContextOverrides` - empty takes the image's -
 *   and one `fs_group` for all its pods, kept across versions so a new uid can still write what an
 *   old one left on a volume. Empty is the image's.
 * * On a scan, the user the scanned tag runs as, so a newer version that changed it is seen.
 */
class AddSpecificationSecurityContext extends Migration {

    public function up() {
        Table::init('container_images')
            ->column('security_context_run_as_non_root', ColumnTypes::BOOL_0)
            ->column('security_context_drop_all_capabilities', ColumnTypes::BOOL_0)
            ->column('security_context_seccomp_runtime_default', ColumnTypes::BOOL_0)
            ->column('image_user', 'VARCHAR(255) NULL')
            ->column('image_user_tag', 'VARCHAR(255) NULL')
            ->column('image_user_read_at', ColumnTypes::DATETIME)
            ->column('image_user_error', ColumnTypes::VARCHAR_1023_NULL);

        Table::init('deployment_specifications')
            ->column('security_context_run_as_non_root', "VARCHAR(7) NOT NULL DEFAULT ''")
            ->column('security_context_drop_all_capabilities', "VARCHAR(7) NOT NULL DEFAULT ''")
            ->column('security_context_seccomp_runtime_default', "VARCHAR(7) NOT NULL DEFAULT ''")
            ->column('security_context_fs_group', 'VARCHAR(127) NULL');

        Table::init('container_image_scans')
            ->column('image_user', 'VARCHAR(255) NULL');

        ApiRoute::quick('/container-images/([0-9]+)/read-user', ContainerImages::class, 'readUser/$1', 'put');
    }

    public function down() {

    }

}
