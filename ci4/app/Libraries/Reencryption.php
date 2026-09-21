<?php namespace App\Libraries;

use CodeIgniter\Encryption\Exceptions\EncryptionException;
use Config\Database;

/**
 * Every stored credential written again with the current key, so a rotated one can be taken
 * out of `ENCRYPTION_PREVIOUS_KEYS`. Without it a rotation is never finished: the old key is
 * needed until every row it wrote has been saved again, and that was every registry, service,
 * deployment and environment variable, by hand.
 *
 * The columns come from the entities - the fields each names in `EncryptedFields` - so one
 * encrypted later is included without anyone remembering to. Written through the query
 * builder, as the migrations that encrypted them were: an entity would decrypt on read and
 * could not tell which key a value was written with.
 *
 * A plain value, one written before its column was encrypted, is encrypted. One no key can
 * read is left as it is and reported.
 */
class Reencryption {

    /**
     * @return array<string, list<string>> table => its encrypted columns
     */
    public static function Columns(): array {
        $columns = [];
        foreach (glob(APPPATH . 'Entities/*.php') as $file) {
            $name = basename($file, '.php');
            $entity = "App\\Entities\\{$name}";
            $model = "App\\Models\\{$name}Model";
            if (!defined("{$entity}::EncryptedFields") || !class_exists($model)) {
                continue;
            }
            $columns[(new $model())->getTableName()] = array_values(constant("{$entity}::EncryptedFields"));
        }
        ksort($columns);

        return $columns;
    }

    /**
     * @return array{rewritten: int, unreadable: list<string>} how many values were written, and
     *         `table.column#id` of each that could not be read
     */
    public function run(): array {
        $db = Database::connect();
        $result = ['rewritten' => 0, 'unreadable' => []];

        foreach (self::Columns() as $table => $columns) {
            foreach ($db->table($table)->select(['id', ...$columns])->get()->getResultArray() as $row) {
                $update = [];
                foreach ($columns as $column) {
                    $stored = (string) ($row[$column] ?? '');
                    if ($stored === '') {
                        continue;
                    }
                    try {
                        $update[$column] = Crypt::Encrypt(Crypt::Decrypt($stored));
                    } catch (EncryptionException) {
                        $result['unreadable'][] = "{$table}.{$column}#{$row['id']}";
                    }
                }
                if ($update !== []) {
                    $db->table($table)->where('id', $row['id'])->update($update);
                    $result['rewritten'] += count($update);
                }
            }
        }

        return $result;
    }

}
