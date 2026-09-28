<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Fixtures;

/**
 * `?filter=health:[…]` on the deployments, as the list asks it. The list leaves Suspended out
 * by naming every other health, and "no health" is a NULL that `IN (…)` never matches - so it
 * has a name of its own, `none`, or a deployment the health check has not reached yet would
 * disappear from the list without a word.
 */
class DeploymentHealthFilterApiTest extends ControllerTestCase {

    public function setUp(): void {
        parent::setUp();

        Fixtures::deployment(['name' => 'healthy', 'health' => \HealthStatusTypes::Healthy]);
        Fixtures::deployment(['name' => 'degraded', 'health' => \HealthStatusTypes::Degraded]);
        Fixtures::deployment(['name' => 'suspended', 'health' => \HealthStatusTypes::Suspended]);
        Fixtures::deployment(['name' => 'unchecked', 'health' => null]);
    }

    public function testTheListsDefaultLeavesOutSuspendedAndKeepsTheUnchecked(): void {
        $this->assertSame(
            ['degraded', 'healthy', 'unchecked'],
            $this->namesOf('deployments?filter=health:[degraded,missing,progressing,unknown,healthy,none]')
        );
    }

    public function testHealthsWithoutNoneLeaveOutTheUnchecked(): void {
        $this->assertSame(['degraded', 'healthy'], $this->namesOf('deployments?filter=health:[healthy,degraded]'));
    }

    public function testNoneAloneIsTheUnchecked(): void {
        $this->assertSame(['unchecked'], $this->namesOf('deployments?filter=health:[none]'));
    }

    public function testSuspendedCanBeAskedFor(): void {
        $this->assertSame(['suspended'], $this->namesOf('deployments?filter=health:[suspended]'));
    }

    public function testNothingChosenIsEveryHealth(): void {
        $this->assertSame(['degraded', 'healthy', 'suspended', 'unchecked'], $this->namesOf('deployments?filter=health:[]'));
    }

    /**
     * The OR for `none` sits in a group of its own, so it cannot widen the filters beside it.
     */
    public function testNoneDoesNotWidenAnotherFilter(): void {
        $this->assertSame(
            ['healthy'],
            $this->namesOf('deployments?filter=health:[healthy,none],name:healthy')
        );
    }

    public function testTheCountAgreesWithTheRows(): void {
        $body = $this->decode($this->signedIn()->get('deployments?filter=health:[healthy,none]'));

        $this->assertSame(2, (int) $body['count']);
        $this->assertCount(2, $body['resources']);
    }

    /**
     * @return string[]
     */
    private function namesOf(string $path): array {
        $body = $this->decode($this->signedIn()->get($path));
        $names = array_map(fn ($row) => $row['name'], $body['resources'] ?? []);
        sort($names);

        return $names;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

}
