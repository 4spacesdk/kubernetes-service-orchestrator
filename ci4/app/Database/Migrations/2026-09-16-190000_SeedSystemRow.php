<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Make the System row part of the schema instead of a side effect of the first request.
 *
 * kso reads its own configuration from `systems` id 1. Until now that row was created
 * lazily by `System::Get()`, which meant a database could exist without it - and did: the
 * test database ran for months with no id 1, handing every caller a fresh, unconfigured
 * System and leaving a row behind on each call.
 *
 * Seeding it here makes the contract explicit, gives a new installation a configurable
 * System before anyone visits it, and lets `System::Get()` refuse to invent one.
 *
 * Idempotent. An installation that already has the row is left alone, which is all of
 * them.
 */
class SeedSystemRow extends Migration {

    public function up() {
        if ($this->db->table('systems')->where('id', 1)->countAllResults() > 0) {
            return;
        }

        $this->db->table('systems')->insert([
            'id' => 1,
            'created' => date('Y-m-d H:i:s'),
        ]);
    }

    public function down() {
        // Deliberately nothing. Removing the row would leave the application unable to
        // read its own configuration, and `System::Get()` now refuses to recreate it.
    }

}
