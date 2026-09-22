<?php namespace App\Tests\Database\Entities;

use App\DatabaseTestCase;
use App\Entities\AutoUpdate;
use App\Entities\Deployment;
use App\Fixtures;
use App\Models\AutoUpdateModel;

/**
 * AutoUpdate::CheckForUpdates() decides whether a newly published image tag should update a
 * deployment. It is the code that reaches customers without anyone pressing anything, so
 * what it refuses to touch matters as much as what it picks up.
 *
 * It takes the image and the tag as arguments and never talks to a registry itself: the
 * puller command discovers tags and hands them over. These tests therefore need no fake
 * registry, only rows.
 */
class AutoUpdateCheckForUpdatesTest extends DatabaseTestCase {

    private const Image = 'eu.gcr.io/example/backend';

    public function testMatchingTagCreatesAnUpdate(): void {
        $deployment = Fixtures::autoUpdatableDeployment(['image' => self::Image, 'auto_update_tag_regex' => 'latest-minor']);

        AutoUpdate::CheckForUpdates(self::Image, 'latest-minor');

        $update = $this->updateFor($deployment);
        $this->assertNotNull($update, 'expected an update to be created');
        $this->assertSame('latest-minor', $update->next_tag);
        $this->assertSame('old', $update->previous_tag);
    }

    public function testUpdateIsApprovedWhenApprovalIsNotRequired(): void {
        $deployment = Fixtures::autoUpdatableDeployment(['image' => self::Image, 'auto_update_tag_regex' => 'latest-minor']);

        AutoUpdate::CheckForUpdates(self::Image, 'latest-minor');

        $this->assertTrue((bool)$this->updateFor($deployment)->is_approved);
    }

    public function testUpdateWaitsWhenApprovalIsRequired(): void {
        $deployment = Fixtures::autoUpdatableDeployment(['image' => self::Image, 'auto_update_tag_regex' => 'latest-minor', 'auto_update_require_approval' => true]);

        AutoUpdate::CheckForUpdates(self::Image, 'latest-minor');

        $this->assertFalse((bool)$this->updateFor($deployment)->is_approved);
    }

    public function testTagThatDoesNotMatchIsIgnored(): void {
        $deployment = Fixtures::autoUpdatableDeployment(['image' => self::Image, 'auto_update_tag_regex' => 'latest-minor']);

        AutoUpdate::CheckForUpdates(self::Image, 'develop');

        $this->assertNull($this->updateFor($deployment));
    }

    public function testAnotherImageIsIgnored(): void {
        $deployment = Fixtures::autoUpdatableDeployment(['image' => self::Image, 'auto_update_tag_regex' => 'latest-minor']);

        AutoUpdate::CheckForUpdates('eu.gcr.io/example/frontend', 'latest-minor');

        $this->assertNull($this->updateFor($deployment));
    }

    public function testDeploymentWithAutoUpdateOffIsIgnored(): void {
        $deployment = Fixtures::autoUpdatableDeployment(['image' => self::Image, 'auto_update_tag_regex' => 'latest-minor']);
        $deployment->auto_update_enabled = false;
        $deployment->save();

        AutoUpdate::CheckForUpdates(self::Image, 'latest-minor');

        $this->assertNull($this->updateFor($deployment));
    }

    /**
     * A terminated deployment is left alone. Together with the workspace check below this
     * is what keeps a paused customer from being updated underneath them.
     */
    public function testInactiveDeploymentIsIgnored(): void {
        $deployment = Fixtures::autoUpdatableDeployment([
            'image' => self::Image,
            'auto_update_tag_regex' => 'latest-minor',
            'status' => \DeploymentStatusTypes::Inactive,
        ]);

        AutoUpdate::CheckForUpdates(self::Image, 'latest-minor');

        $this->assertNull($this->updateFor($deployment));
    }

    /**
     * The second guard, and the one that carries the weight: a deployment can sit in a
     * status the query accepts while its workspace is paused.
     */
    public function testDeploymentInAnInactiveWorkspaceIsIgnored(): void {
        $deployment = Fixtures::autoUpdatableDeployment([
            'image' => self::Image,
            'auto_update_tag_regex' => 'latest-minor',
            'workspace_status' => \WorkspaceStatusTypes::Inactive,
        ]);

        AutoUpdate::CheckForUpdates(self::Image, 'latest-minor');

        $this->assertNull($this->updateFor($deployment));
    }

    /**
     * A deployment that is not all there - its last deploy did not finish - is still a
     * candidate: a new tag is often exactly what fixes it.
     */
    public function testAnOutOfSyncDeploymentIsStillUpdated(): void {
        $deployment = Fixtures::autoUpdatableDeployment([
            'image' => self::Image,
            'auto_update_tag_regex' => 'latest-minor',
            'status' => \DeploymentStatusTypes::OutOfSync,
        ]);

        AutoUpdate::CheckForUpdates(self::Image, 'latest-minor');

        $this->assertNotNull($this->updateFor($deployment));
    }

    /**
     * Documents how the pattern is applied today: it is anchored at the end only, so a
     * regex of "latest" also matches "not-latest". Worth knowing before writing a regex,
     * and worth revisiting - anchoring both ends would be the less surprising rule.
     */
    public function testPatternIsAnchoredAtTheEndOnly(): void {
        $deployment = Fixtures::autoUpdatableDeployment(['image' => self::Image, 'auto_update_tag_regex' => 'latest']);

        AutoUpdate::CheckForUpdates(self::Image, 'not-latest');

        $this->assertNotNull($this->updateFor($deployment));
    }

    private function updateFor(Deployment $deployment): ?AutoUpdate {
        /** @var AutoUpdate $update */
        $update = (new AutoUpdateModel())
            ->where('deployment_id', $deployment->id)
            ->find();
        return $update->exists() ? $update : null;
    }

}
