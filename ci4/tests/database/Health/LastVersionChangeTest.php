<?php namespace App\Tests\Database\Health;

use App\DatabaseTestCase;
use App\Fixtures;
use App\Libraries\Health\Diagnosis\EvidenceGatherer;

/**
 * The version before this one, read off the audit trail - what the diagnosis offers to roll back to.
 */
class LastVersionChangeTest extends DatabaseTestCase {

    public function testTheLastChangeOfTheVersionIsFound(): void {
        $deployment = Fixtures::deployment(['version' => '1.0']);
        $deployment->updateVersion('1.1', false);
        $deployment->updateVersion('1.2', false);
        $deployment->environment = 'production';
        $deployment->save();

        $change = EvidenceGatherer::LastVersionChange($deployment);

        $this->assertSame('1.1', $change['from']);
        $this->assertSame('1.2', $change['to']);
        $this->assertEqualsWithDelta(time(), $change['at'], 5);
    }

    /**
     * Its creation carries the first version too, but going from nothing is not a change to roll
     * back from.
     */
    public function testADeploymentWhoseVersionNeverChangedHasNone(): void {
        $deployment = Fixtures::deployment(['version' => '1.0']);

        $this->assertNull(EvidenceGatherer::LastVersionChange($deployment));
    }

    public function testAnotherDeploymentsChangeIsNotThisOnes(): void {
        $deployment = Fixtures::deployment(['version' => '1.0']);
        $other = Fixtures::deployment(['name' => 'other', 'version' => '1.0']);
        $other->updateVersion('2.0', false);

        $this->assertNull(EvidenceGatherer::LastVersionChange($deployment));
    }

}
