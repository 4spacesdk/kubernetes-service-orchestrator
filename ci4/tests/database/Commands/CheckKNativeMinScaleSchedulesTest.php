<?php namespace App\Tests\Database\Commands;

use App\Commands\CheckKNativeMinScaleSchedules;
use App\DatabaseTestCase;
use App\Entities\Deployment;
use App\Entities\KNativeMinScaleSchedule;
use App\Fixtures;

/**
 * The cron job that scales a Knative workspace up before the working day starts.
 *
 * A schedule is a cron expression plus a timezone plus a minimum number of pods. Every
 * minute this job asks each schedule whether it is due *in its own timezone* and applies
 * the one that is. The timezone is the part that cannot be eyeballed: a customer in Tokyo
 * and one in Copenhagen share the expression `0 7 * * 1-5` and must be scaled up thirteen
 * hours apart.
 *
 * The job writes what it decided into its own log, which is what these read.
 */
class CheckKNativeMinScaleSchedulesTest extends DatabaseTestCase {

    public function testADeploymentWithoutSchedulingIsNotLookedAt(): void {
        Fixtures::deployableDeployment(['name' => 'unscheduled']);

        $log = $this->runTheJob();

        $this->assertStringContainsString('found 0 deployments', $log);
        $this->assertStringNotContainsString('unscheduled', $log);
    }

    public function testADueScheduleIsApplied(): void {
        $deployment = $this->deploymentWithSchedules([
            ['cron_expression' => '* * * * *', 'timezone' => 'UTC', 'min_scale' => 4],
        ]);

        $log = $this->runTheJob();

        $this->assertStringContainsString("found 1 schedules for deployment {$deployment->name}", $log);
        $this->assertStringContainsString("applying min scale 4 to deployment {$deployment->name}", $log);
    }

    public function testAScheduleThatIsNotDueIsLeftAlone(): void {
        $deployment = $this->deploymentWithSchedules([
            // One minute a year, and this is not it.
            ['cron_expression' => '0 0 1 1 *', 'timezone' => 'UTC', 'min_scale' => 4],
        ]);

        $log = $this->runTheJob();

        $this->assertStringContainsString("found 1 schedules for deployment {$deployment->name}", $log);
        $this->assertStringNotContainsString('applying min scale', $log);
    }

    /**
     * The same expression, two timezones, one answer. `new DateTime('now', $timezone)` is
     * the whole mechanism, and dropping it would scale every customer in the world at the
     * server's hour.
     */
    public function testTheTimezoneDecidesWhetherAScheduleIsDue(): void {
        $hourHereAndNowhereElse = '* ' . gmdate('G') . ' * * *';

        $inUtc = $this->deploymentWithSchedules([
            ['cron_expression' => $hourHereAndNowhereElse, 'timezone' => 'UTC', 'min_scale' => 2],
        ], 'in-utc');
        $inTokyo = $this->deploymentWithSchedules([
            ['cron_expression' => $hourHereAndNowhereElse, 'timezone' => 'Asia/Tokyo', 'min_scale' => 9],
        ], 'in-tokyo');

        $log = $this->runTheJob();

        $this->assertStringContainsString("applying min scale 2 to deployment {$inUtc->name}", $log);
        $this->assertStringNotContainsString("to deployment {$inTokyo->name}", $log);
    }

    /**
     * **Today's behaviour, and it is FEAT-22.** `checkForDue()` finds the schedule that is
     * due and then returns the whole **collection** rather than the one it found, so
     * `applyMinScale()` reads the first schedule's value - the one with the lowest priority
     * number - whichever one was actually due.
     *
     * Here the due schedule asks for 9 and the log says 1.
     */
    public function testTheLoggedValueComesFromTheFirstScheduleNotTheDueOne(): void {
        $deployment = $this->deploymentWithSchedules([
            ['cron_expression' => '0 0 1 1 *', 'timezone' => 'UTC', 'min_scale' => 1, 'priority' => 0],
            ['cron_expression' => '* * * * *', 'timezone' => 'UTC', 'min_scale' => 9, 'priority' => 1],
        ]);

        $log = $this->runTheJob();

        $this->assertStringContainsString("applying min scale 1 to deployment {$deployment->name}", $log);
        $this->assertStringNotContainsString('applying min scale 9', $log);
    }

    public function testTheJobRecordsWhenItLastRan(): void {
        $before = $this->cronJob()['last_run'];

        $this->runTheJob();

        $this->assertNotSame($before, $this->cronJob()['last_run']);
    }

    // <editor-fold desc="Fixtures">

    /**
     * Runs the command and hands back what it wrote into its own log. The debugger's store
     * is a process-wide static, so it is cleared first - otherwise each run carries every
     * earlier run's lines and an assertion reads the wrong answer.
     */
    private function runTheJob(): string {
        $store = (new \ReflectionClass(\DebugTool\Data::class))->getProperty('store');
        $store->setValue(null, ['status' => null]);

        (new CheckKNativeMinScaleSchedules(service('logger'), service('commands')))->run([]);

        return (string) $this->cronJob()['last_log'];
    }

    /**
     * @return array<string, mixed>
     */
    private function cronJob(): array {
        return $this->db->table('cron_jobs')
            ->where('id', \CronJobIds::CheckKNativeMinScaleSchedules)
            ->get()->getRowArray();
    }

    /**
     * @param array<array<string, mixed>> $schedules
     */
    private function deploymentWithSchedules(array $schedules, string $name = 'scheduled'): Deployment {
        $deployment = Fixtures::deployableDeployment(['name' => $name]);

        $values = new KNativeMinScaleSchedule();
        $values->all = array_map(fn (array $overrides) => Fixtures::minScaleSchedule($overrides), $schedules);
        $deployment->updateKNativeMinScaleSchedules($values);

        return $deployment;
    }

    // </editor-fold>

}
