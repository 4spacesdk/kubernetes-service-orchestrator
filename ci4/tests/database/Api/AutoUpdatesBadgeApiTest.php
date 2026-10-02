<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Fixtures;

/**
 * The number on the menu's Updates, for the signed-in user: the updates waiting for approval,
 * which stay until somebody approves them, and those approved on their own since the user last
 * opened Updates, which go when they do. An update somebody approved by hand is neither.
 */
class AutoUpdatesBadgeApiTest extends ControllerTestCase {

    public function testWhatWaitsAndWhatWasApprovedOnItsOwnSinceTheLastLookAreCounted(): void {
        $this->theSignedInUserLookedAt($this->ago(3600));
        $this->anUpdate(isApproved: false);
        $this->anUpdate(isApproved: true, isAutoApproved: true, approvedSecondsAgo: 600);
        $this->anUpdate(isApproved: true, isAutoApproved: true, approvedSecondsAgo: 7200);
        $this->anUpdate(isApproved: true, isAutoApproved: false, approvedSecondsAgo: 600);

        $this->assertSame(['waiting' => 1, 'approved_on_their_own' => 1], $this->badge());
    }

    /**
     * Opening Updates clears what was approved on its own - and only that.
     */
    public function testOpeningUpdatesLeavesWhatWaitsForApproval(): void {
        $this->theSignedInUserLookedAt($this->ago(3600));
        $this->anUpdate(isApproved: false);
        $this->anUpdate(isApproved: true, isAutoApproved: true, approvedSecondsAgo: 600);

        $seen = $this->decode($this->signedIn()->put('users/me/auto-updates-seen'));

        $this->assertSame(['waiting' => 1, 'approved_on_their_own' => 0], $seen['resource']);
        $this->assertSame(['waiting' => 1, 'approved_on_their_own' => 0], $this->badge());
    }

    /**
     * Each user has their own last look: somebody else opening Updates is not news seen by me.
     */
    public function testAnotherUsersLookIsNotMine(): void {
        $this->theSignedInUserLookedAt($this->ago(3600));
        Fixtures::user(['username' => 'colleague', 'auto_updates_seen_at' => $this->ago(0)]);
        $this->anUpdate(isApproved: true, isAutoApproved: true, approvedSecondsAgo: 600);

        $this->assertSame(1, $this->badge()['approved_on_their_own']);
    }

    // <editor-fold desc="Helpers">

    /**
     * @return array{waiting: int, approved_on_their_own: int}
     */
    private function badge(): array {
        return $this->decode($this->signedIn()->get('users/me/auto-updates-badge'))['resource'];
    }

    private function theSignedInUserLookedAt(string $time): void {
        db_connect()->table('users')->where('id', $this->signedInUserId())->update(['auto_updates_seen_at' => $time]);
    }

    private function anUpdate(bool $isApproved, bool $isAutoApproved = false, int $approvedSecondsAgo = 0): void {
        db_connect()->table('auto_updates')->insert([
            'deployment_id' => 0,
            'image' => 'registry.example.org/app',
            'previous_tag' => '1.0',
            'next_tag' => '1.1',
            'is_approved' => $isApproved,
            'is_auto_approved' => $isAutoApproved,
            'log' => '',
            ...($isApproved ? ['approved_date' => $this->ago($approvedSecondsAgo)] : []),
        ]);
    }

    private function ago(int $seconds): string {
        return date('Y-m-d H:i:s', time() - $seconds);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

    // </editor-fold>

}
