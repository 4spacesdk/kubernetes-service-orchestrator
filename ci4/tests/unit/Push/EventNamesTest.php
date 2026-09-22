<?php namespace App\Tests\Unit\Push;

use App\Libraries\Push\Events;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The topic strings the push events are published under.
 *
 * Every name goes through one private `Generate()`, which lowercases and turns hyphens into
 * underscores, and the result is the Centrifugo channel the browser subscribes to. The two halves have to
 * agree on the exact string and nothing checks that they do - a renamed topic is a
 * notification that silently stops arriving, with no error anywhere.
 *
 * The scoped names carry an id, which is what makes a subscription per workspace possible;
 * the unscoped ones are broadcast. Both shapes are pinned, and so is the substitution -
 * `auto-update-created` and `migration-job.created` are the only two places it does
 * anything, and they are the reason it exists.
 */
class EventNamesTest extends CIUnitTestCase {

    public function testEveryTopicIsUnderTheOnePrefix(): void {
        foreach ($this->everyName() as $label => $name) {
            $this->assertStringStartsWith('events.', $name, $label . ' is published outside the prefix');
        }
    }

    /**
     * Nothing enforces it, and a collision would mean two unrelated changes arriving on one
     * subscription - the client has no way to tell them apart afterwards.
     */
    public function testNoTwoTopicsAreTheSame(): void {
        $names = $this->everyName();

        $this->assertSame(count($names), count(array_unique($names)));
    }

    /**
     * Hyphens become underscores and everything is lowercased, so the name in the code and
     * the name on the wire are not the same string. The dots are left alone: they are the
     * topic separator the client matches on.
     */
    public function testHyphensBecomeUnderscoresAndTheCaseIsDropped(): void {
        $this->assertSame('events.migration_job.created', Events::MigrationJob_Created());
        $this->assertSame('events.auto_update_created', Events::AutoUpdate_Created());
        $this->assertSame('events.auto_update_rolled_out', Events::AutoUpdate_RolledOut());
    }

    public function testTheScopedTopicsCarryTheIdOfTheRowThatChanged(): void {
        $this->assertSame('events.deployment.42.changed.status', Events::Deployment_Changed_Status(42));
        $this->assertSame('events.workspace.7.changed.status', Events::Workspace_Changed_Status(7));
        $this->assertSame('events.migration_job.3.changed.status', Events::MigrationJob_Changed_Status(3));
        $this->assertSame('events.container_image.5.scans.changed', Events::ContainerImage_Scans_Changed(5));
    }

    /**
     * The only topic built from names rather than ids, and the only one a value from the
     * cluster reaches: a pod and a container as Kubernetes calls them.
     *
     * Every pod name has hyphens in it - Kubernetes appends a replica set hash and a pod
     * hash - so the substitution rewrites the name on the way into the topic. That is not
     * a mistake as long as **both** ends rewrite it, and the browser does:
     * `vue/src/services/Wamp/Events.ts` has the same lowercase-and-replace in its own
     * `Generate()`. The two are copies of each other with nothing holding them together,
     * which is why the exact string is worth writing down on this side.
     */
    public function testTheLogWatchTopicRewritesThePodNameTheSameWayTheBrowserDoes(): void {
        $this->assertSame(
            'events.kubernetes.pod.api_7c9f_x2d4.containers.app.logs.watch',
            Events::KubernetesPod_Logs_Watch('api-7c9f-x2d4', 'app')
        );
    }

    /**
     * A pod name is lowercase by Kubernetes' own rules, but nothing here depends on that:
     * the topic is lowercased on the way out, so a caller that passes anything else still
     * subscribes to the same string.
     */
    public function testTheLogWatchTopicIsLowercasedLikeEveryOtherOne(): void {
        $this->assertSame(
            'events.kubernetes.pod.api.containers.app.logs.watch',
            Events::KubernetesPod_Logs_Watch('API', 'App')
        );
    }

    public function testTheBroadcastTopics(): void {
        $this->assertSame('events.workspace.created', Events::Workspace_Created());
        $this->assertSame('events.workspace.updated', Events::Workspace_Updated());
        $this->assertSame('events.workspace.deleted', Events::Workspace_Deleted());
        $this->assertSame('events.workspace.deployed', Events::Workspace_Deployed());
        $this->assertSame('events.workspace.terminated', Events::Workspace_Terminated());
        $this->assertSame('events.deployment.deployed', Events::Deployment_Deployed());
        $this->assertSame('events.deployment.terminated', Events::Deployment_Terminated());
        $this->assertSame('events.auto_update_deleted', Events::AutoUpdate_Deleted());
        $this->assertSame('events.auto_update_approved', Events::AutoUpdate_Approved());
    }

    /**
     * @return array<string, string>
     */
    private function everyName(): array {
        return [
            'Deployment_Changed_Status' => Events::Deployment_Changed_Status(1),
            'Workspace_Changed_Status' => Events::Workspace_Changed_Status(1),
            'MigrationJob_Created' => Events::MigrationJob_Created(),
            'MigrationJob_Changed_Status' => Events::MigrationJob_Changed_Status(1),
            'KubernetesPod_Logs_Watch' => Events::KubernetesPod_Logs_Watch('pod', 'container'),
            'Workspace_Created' => Events::Workspace_Created(),
            'Workspace_Updated' => Events::Workspace_Updated(),
            'Workspace_Deleted' => Events::Workspace_Deleted(),
            'Workspace_Deployed' => Events::Workspace_Deployed(),
            'Workspace_Terminated' => Events::Workspace_Terminated(),
            'Deployment_Deployed' => Events::Deployment_Deployed(),
            'Deployment_Terminated' => Events::Deployment_Terminated(),
            'AutoUpdate_Created' => Events::AutoUpdate_Created(),
            'AutoUpdate_Deleted' => Events::AutoUpdate_Deleted(),
            'AutoUpdate_Approved' => Events::AutoUpdate_Approved(),
            'AutoUpdate_RolledOut' => Events::AutoUpdate_RolledOut(),
        ];
    }

}
