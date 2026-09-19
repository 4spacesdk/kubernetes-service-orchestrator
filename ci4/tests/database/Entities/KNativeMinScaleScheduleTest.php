<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Entities\Deployment;
use App\Entities\KNativeMinScaleSchedule;
use App\Fixtures;

/**
 * How many pods a KNative workload keeps warm, by time of day.
 *
 * Every schedule carries a cron expression, a timezone and a minimum scale. The one whose
 * last firing is most recent wins, and its `min_scale` becomes an annotation on the
 * revision. It is read on every manifest build, so the answer decides whether a customer
 * pays for idle pods overnight or waits for a cold start in the morning - and both
 * failures look like a working deployment.
 *
 * **What is not pinned here, and why.** The comparison is against `new DateTime('now')`
 * with no seam to inject a clock, so which of two schedules wins depends on when the suite
 * runs. Only orderings that hold at every hour of the year are asserted below: a schedule
 * that fires every minute always beats one that fires every January. Comparing two
 * timezones is exactly the case that cannot be made stable that way - it flips on New
 * Year's Day - so it is left out rather than written as a test that fails once a year. A
 * clock seam would fix that.
 */
class KNativeMinScaleScheduleTest extends DatabaseTestCase {

    /** Fires every minute, so its last run is always within the last minute. */
    private const CONSTANTLY = '* * * * *';

    /** Fires once a year, so its last run is months ago on almost every day. */
    private const RARELY = '0 0 1 1 *';

    public function testADeploymentWithSchedulingDisabledKeepsNothingWarm(): void {
        $deployment = $this->deploymentWithSchedules(
            [['min_scale' => 5, 'cron_expression' => self::CONSTANTLY]],
            ['knative_scheduled_minscale_is_enabled' => false]
        );

        $this->assertSame('0', $this->currentValue($deployment));
    }

    /**
     * Scheduling on but nothing scheduled is the same answer as scheduling off: scale to
     * zero. The alternative - leaving the previous value in place - would keep pods warm
     * for a deployment whose schedules were all deleted.
     */
    public function testNoSchedulesMeansNothingWarm(): void {
        $deployment = $this->deploymentWithSchedules([]);

        $this->assertSame('0', $this->currentValue($deployment));
    }

    public function testASingleScheduleDecidesOnItsOwn(): void {
        $deployment = $this->deploymentWithSchedules([
            ['min_scale' => 3, 'cron_expression' => self::CONSTANTLY],
        ]);

        $this->assertSame('3', $this->currentValue($deployment));
    }

    /**
     * The most recent firing wins, not the highest scale and not the first row. A nightly
     * schedule does not hold the workload up all day just because it asks for more pods.
     */
    public function testTheMostRecentlyFiredScheduleWins(): void {
        $deployment = $this->deploymentWithSchedules([
            ['min_scale' => 9, 'cron_expression' => self::RARELY, 'priority' => 0],
            ['min_scale' => 1, 'cron_expression' => self::CONSTANTLY, 'priority' => 1],
        ]);

        $this->assertSame('1', $this->currentValue($deployment));
    }

    public function testOrderInTheTableDoesNotDecideIt(): void {
        $deployment = $this->deploymentWithSchedules([
            ['min_scale' => 1, 'cron_expression' => self::CONSTANTLY, 'priority' => 5],
            ['min_scale' => 9, 'cron_expression' => self::RARELY, 'priority' => 0],
        ]);

        $this->assertSame('1', $this->currentValue($deployment));
    }

    /**
     * Two schedules that last fired at the same moment are separated by priority, lowest
     * first. Both use the yearly expression so the two timestamps are identical whenever
     * the suite runs - with a per-minute expression they could land either side of a
     * minute boundary and the test would be a coin toss.
     */
    public function testATieIsBrokenByTheLowestPriority(): void {
        $deployment = $this->deploymentWithSchedules([
            ['min_scale' => 7, 'cron_expression' => self::RARELY, 'priority' => 2],
            ['min_scale' => 4, 'cron_expression' => self::RARELY, 'priority' => 1],
        ]);

        $this->assertSame('4', $this->currentValue($deployment));
    }

    public function testATieIsBrokenTheSameWayWhicheverOrderTheRowsAreWritten(): void {
        $deployment = $this->deploymentWithSchedules([
            ['min_scale' => 4, 'cron_expression' => self::RARELY, 'priority' => 1],
            ['min_scale' => 7, 'cron_expression' => self::RARELY, 'priority' => 2],
        ]);

        $this->assertSame('4', $this->currentValue($deployment));
    }

    /**
     * Another deployment's schedules are not this deployment's business. They are joined
     * through a table, so the filter is the only thing keeping them apart.
     */
    public function testOnlyThisDeploymentsSchedulesAreConsidered(): void {
        // Both yearly, so their last firings are identical and priority decides. The other
        // deployment's schedule is given the lower number, so it would win if the join
        // ever stopped filtering - which is what makes this an assertion rather than a
        // coincidence.
        $mine = $this->deploymentWithSchedules([
            ['min_scale' => 2, 'cron_expression' => self::RARELY, 'priority' => 5],
        ], ['name' => 'mine']);
        $this->deploymentWithSchedules([
            ['min_scale' => 8, 'cron_expression' => self::RARELY, 'priority' => 0],
        ], ['name' => 'someone-else']);

        $this->assertSame('2', $this->currentValue($mine));
    }

    /**
     * The value becomes an annotation, and a Kubernetes annotation has to be a string.
     * A number here is rejected by the API server - see KServiceStepTest.
     */
    public function testTheValueIsAlwaysAString(): void {
        $scheduled = $this->deploymentWithSchedules([
            ['min_scale' => 3, 'cron_expression' => self::CONSTANTLY],
        ]);
        $unscheduled = $this->deploymentWithSchedules([]);

        $this->assertIsString($this->currentValue($scheduled));
        $this->assertIsString($this->currentValue($unscheduled));
    }

    /**
     * Neither field is validated when it is stored, so a typo surfaces here - while a
     * manifest is being built, not while the schedule is being saved. Held as tests
     * because the failure lands a long way from the mistake.
     */
    public function testAMalformedCronExpressionThrowsWhileBuildingTheManifest(): void {
        $deployment = $this->deploymentWithSchedules([
            ['min_scale' => 1, 'cron_expression' => 'every other tuesday'],
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->currentValue($deployment);
    }

    public function testAnUnknownTimezoneThrowsWhileBuildingTheManifest(): void {
        $deployment = $this->deploymentWithSchedules([
            ['min_scale' => 1, 'cron_expression' => self::CONSTANTLY, 'timezone' => 'Mars/Olympus_Mons'],
        ]);

        $this->expectException(\Exception::class);

        $this->currentValue($deployment);
    }

    // <editor-fold desc="Fixtures">

    /**
     * @param array<array<string, mixed>> $schedules
     * @param array<string, mixed> $deployment
     */
    private function deploymentWithSchedules(array $schedules, array $deployment = []): Deployment {
        $item = Fixtures::deployment(array_merge([
            'knative_scheduled_minscale_is_enabled' => true,
        ], $deployment));

        foreach ($schedules as $index => $schedule) {
            $row = Fixtures::minScaleSchedule(array_merge(['priority' => $index], $schedule));
            $this->db->table('deployments_knative_min_scale_schedules')->insert([
                'deployment_id' => $item->id,
                'knative_min_scale_schedule_id' => $row->id,
            ]);
        }

        return $item;
    }

    private function currentValue(Deployment $deployment): string {
        return KNativeMinScaleSchedule::GetCurrentValueForDeployment($deployment);
    }

    // </editor-fold>

}
