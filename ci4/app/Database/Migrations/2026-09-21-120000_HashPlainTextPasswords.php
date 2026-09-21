<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

/**
 * A password under six characters set through the API was written as sent and never
 * hashed. Any such row still holds it in plain text.
 *
 * Hashed in place, so the user can still sign in with it, and marked for renewal, because
 * it is too short for today's rules and the sign-in form then asks for a new one. A
 * bcrypt hash starts with `$2`; nothing else in the column is one.
 */
class HashPlainTextPasswords extends Migration {

    public function up() {
        $db = Database::connect();

        $rows = $db->table('users')
            ->select('id, password')
            ->where('password IS NOT NULL')
            ->where('password !=', '')
            ->notLike('password', '$2', 'after')
            ->get()->getResultArray();

        foreach ($rows as $row) {
            $db->table('users')->where('id', $row['id'])->update([
                'password' => password_hash($row['password'], PASSWORD_BCRYPT),
                'renew_password' => 1,
            ]);
        }
    }

    public function down() {

    }

}
