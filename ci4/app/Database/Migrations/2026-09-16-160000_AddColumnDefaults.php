<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

/**
 * Give every NOT NULL column a usable default.
 *
 * CodeIgniter 4.7 writes only the fields an entity actually carries. An entity that is
 * built in code holds nothing until something is assigned, so an insert covers the
 * assigned columns and no others. With MySQL in STRICT_TRANS_TABLES a NOT NULL column
 * that has no default and is not in the insert rejects the whole statement, and the row
 * is never written.
 *
 * `AutoUpdate::CheckForUpdates()` is the case that surfaced it: it sets four fields and
 * saves, and `approved_date` and `log` broke the insert. There were 275 such columns
 * across 61 tables, so rather than teaching every write site to fill in fields it does
 * not care about, the schema states what an unset column means.
 *
 * Driven from information_schema rather than a list, so it covers the tables the
 * extensions own as well, and stays correct on an installation whose schema has drifted.
 */
class AddColumnDefaults extends Migration {

    /**
     * CodeIgniter owns this one and always writes every column of it.
     */
    private const SkipTables = ['migrations'];

    public function up() {
        $db = Database::connect();
        $schema = $db->getDatabase();

        $columns = $db->query(
            // Aliased in lower case on purpose: MySQL 8 returns information_schema column
            // names upper case where 5.7 returned them lower case.
            "SELECT table_name AS table_name,
                    column_name AS column_name,
                    data_type AS data_type,
                    column_type AS column_type,
                    collation_name AS collation_name
             FROM information_schema.columns
             WHERE table_schema = ?
               AND is_nullable = 'NO'
               AND column_default IS NULL
               AND extra NOT LIKE '%auto_increment%'
             ORDER BY table_name, ordinal_position",
            [$schema]
        )->getResultArray();

        foreach ($columns as $column) {
            $table = $column['table_name'];
            if (in_array($table, self::SkipTables, true)) {
                continue;
            }

            $name = $column['column_name'];
            $sql = $this->alterFor($table, $name, $column);
            if ($sql !== null) {
                $db->query($sql);
            }
        }
    }

    /**
     * MySQL 5.7 does not allow a default on TEXT or BLOB, so those are made nullable
     * instead: an unset column has to be able to mean something either way.
     *
     * Everything else keeps its type and only gains a default, which MySQL applies to the
     * table metadata without rewriting the rows.
     */
    private function alterFor(string $table, string $name, array $column): ?string {
        $type = strtolower($column['data_type']);

        if (in_array($type, ['text', 'tinytext', 'mediumtext', 'longtext', 'blob', 'tinyblob', 'mediumblob', 'longblob', 'json'], true)) {
            $definition = $column['column_type'];
            if (! empty($column['collation_name'])) {
                $definition .= " COLLATE {$column['collation_name']}";
            }

            return "ALTER TABLE `{$table}` MODIFY COLUMN `{$name}` {$definition} NULL DEFAULT NULL";
        }

        $default = $this->isNumeric($type) ? '0' : "''";

        return "ALTER TABLE `{$table}` ALTER COLUMN `{$name}` SET DEFAULT {$default}";
    }

    private function isNumeric(string $type): bool {
        return in_array(
            $type,
            ['int', 'integer', 'bigint', 'mediumint', 'smallint', 'tinyint', 'decimal', 'numeric', 'float', 'double'],
            true
        );
    }

    public function down() {
        // Not reversible. Dropping the defaults again would put every installation back in
        // the state where an ordinary insert can fail, and nothing depends on them being
        // absent.
    }

}
