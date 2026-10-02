<?php namespace App\Database\Migrations;

use App\Controllers\Users;
use CodeIgniter\Database\Migration;
use OrmExtension\Migration\ColumnTypes;
use OrmExtension\Migration\Table;
use RestExtension\Entities\ApiRoute;

/**
 * The menu's Updates badge counts the updates that were approved on their own since a user last
 * opened Updates, as well as those waiting for approval. Which were approved on their own, and
 * when each user last looked.
 *
 * Every existing user has looked now: the updates approved before the upgrade are not news.
 */
class AddAutoUpdatesSeen extends Migration {

    public function up() {
        Table::init('auto_updates')
            ->column('is_auto_approved', ColumnTypes::BOOL_0);

        Table::init('users')
            ->column('auto_updates_seen_at', ColumnTypes::DATETIME);
        $this->db->table('users')->set('auto_updates_seen_at', date('Y-m-d H:i:s'))->update();

        ApiRoute::quick('/users/me/auto-updates-badge', Users::class, 'autoUpdatesBadge', 'get');
        ApiRoute::quick('/users/me/auto-updates-seen', Users::class, 'autoUpdatesSeen', 'put');
    }

    public function down() {

    }

}
