<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use OrmExtension\Migration\Table;

/**
 * A migration job's pod proves itself with a token of its own when it reports that it started
 * and ended - see `MigrationJob::issueCallbackToken()`. Only the token's SHA-256 is stored.
 *
 * A job started before this has no token, and its callbacks are refused: rerun it.
 */
class AddMigrationJobCallbackTokens extends Migration {

    public function up() {
        Table::init('migration_jobs')
            ->column('callback_token_hash', 'VARCHAR(64) NULL');
    }

    public function down() {

    }

}
