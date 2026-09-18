<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Entities\System;

/**
 * How kso finds the row it reads its own configuration from.
 *
 * There is one System, and it lives at id 1. `Get()` used to create a row whenever that
 * id was missing, which sounds harmless and is not: a lost row turned into an installation
 * that ran unconfigured, answered every request from a throwaway System, and grew the
 * table by a row per request. The test database sat in that state for months.
 *
 * So the rule is now: create it once, on an empty table, and otherwise refuse.
 */
class SystemGetTest extends DatabaseTestCase {

    public function testTheConfiguredRowIsReturned(): void {
        $this->db->table('systems')->where('id', 1)->update(['hosting_provider' => \HostingProviders::Gke]);

        $system = System::Get();

        $this->assertSame(1, (int) $system->id);
        $this->assertSame(\HostingProviders::Gke, $system->hosting_provider);
    }

    public function testCallingItTwiceDoesNotAddARow(): void {
        $before = $this->db->table('systems')->countAllResults();

        System::Get();
        System::Get();
        System::Get();

        $this->assertSame($before, $this->db->table('systems')->countAllResults());
    }

    /**
     * A fresh installation. Somebody has to write the first row, and this is the only case
     * where `Get()` does it.
     */
    public function testAnEmptyTableIsSeededOnce(): void {
        $this->emptyTheTable();

        $system = System::Get();

        $this->assertSame(1, (int) $system->id);
        $this->assertSame(1, $this->db->table('systems')->countAllResults());
    }

    /**
     * The case that used to be silent. An id 1 that has gone missing while other rows
     * remain is damage, not a fresh start, and guessing which row to use would be worse
     * than stopping.
     */
    public function testRowsWithoutIdOneAreRefused(): void {
        $this->emptyTheTable();
        $this->db->table('systems')->insert(['id' => 7, 'created' => date('Y-m-d H:i:s')]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('none with id 1');

        System::Get();
    }

    /**
     * DELETE, never TRUNCATE.
     *
     * MySQL commits implicitly around TRUNCATE, so it escapes the transaction these tests
     * run inside and the table is left however the last test happened to leave it. An
     * earlier version of this file used it, and whether the suite could run again came
     * down to which test PHPUnit executed last.
     */
    private function emptyTheTable(): void {
        $this->db->query('delete from systems');
    }

    /**
     * The id is written explicitly when seeding. A table that once held rows has an auto
     * increment well past 1, and a row landing at 530 would never be found again by the
     * lookup - which is precisely how the old behaviour compounded.
     */
    public function testSeedingUsesIdOneEvenAfterTheAutoIncrementHasMovedOn(): void {
        $this->emptyTheTable();
        $this->db->table('systems')->insert(['id' => 530, 'created' => date('Y-m-d H:i:s')]);
        $this->db->table('systems')->where('id', 530)->delete();

        $system = System::Get();

        $this->assertSame(1, (int) $system->id);
    }

}
