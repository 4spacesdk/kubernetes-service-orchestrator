<?php namespace App\Libraries\Push;

use App\Entities\AutoUpdate;
use App\Entities\Deployment;
use App\Libraries\Health\HealthCheck;
use App\Libraries\WebHooks\WebhookHelper;
use DebugTool\Data;

/**
 * The events kso acts on itself, and what it does.
 *
 * `Publisher` puts each of these on the queue when it is raised, and `Jobs\HandleEvent` hands
 * it back here when a worker picks it up - in whichever container that worker runs, and after
 * a restart if need be. Every other event is only pushed to the browsers.
 *
 * This used to be a WAMP subscriber that shelled out to a CLI controller per event, and what
 * was raised while the subscriber was not connected was gone. `EventHandlersTest` goes
 * through `For()` the way the publisher does.
 */
class EventHandlers {

    public const Queue = 'events';
    public const Job = 'handle-event';

    /**
     * @return array<string, array{method: string, delay: int}>
     */
    private static function Map(): array {
        return [
            // Seconds, because the job's pod is still shutting down when it reports that it
            // is done, and the deployment's status comes from the cluster.
            Events::MigrationJob_Changed_Status(0) => ['method' => 'migrationJobChangedStatus', 'delay' => 5],
            Events::Workspace_Created() => ['method' => 'workspaceCreated', 'delay' => 0],
            Events::Workspace_Updated() => ['method' => 'workspaceUpdated', 'delay' => 0],
            Events::Workspace_Deleted() => ['method' => 'workspaceDeleted', 'delay' => 0],
            Events::Workspace_Deployed() => ['method' => 'workspaceDeployed', 'delay' => 0],
            Events::Workspace_Terminated() => ['method' => 'workspaceTerminated', 'delay' => 0],
            Events::Deployment_Deployed() => ['method' => 'deploymentDeployed', 'delay' => 0],
            Events::Deployment_Terminated() => ['method' => 'deploymentTerminated', 'delay' => 0],
            Events::AutoUpdate_Approved() => ['method' => 'autoUpdateApproved', 'delay' => 0],
            Events::Deployment_Health_Settled() => ['method' => 'deploymentHealthSettled', 'delay' => 0],
            // The pace a rollout is followed at. Each look queues the next, so nothing waits in
            // the worker in between.
            Events::Deployment_Health_Follow() => ['method' => 'deploymentHealthFollow', 'delay' => HealthCheck::FollowEvery],
        ];
    }

    /**
     * @return array{method: string, delay: int}|null
     */
    public static function For(string $event): ?array {
        return self::Map()[$event] ?? null;
    }

    public static function Handle(string $event, ChangeEvent $changeEvent): void {
        $handler = self::For($event);
        if (!$handler) {
            Data::debug('No handler for', $event);
            return;
        }
        $method = $handler['method'];
        self::$method($changeEvent);
    }

    private static function migrationJobChangedStatus(ChangeEvent $changeEvent): void {
        switch ($changeEvent->next['status'] ?? null) {
            case \MigrationJobStatusTypes::Completed:
            case \MigrationJobStatusTypes::Failed_LogVerification:
            case \MigrationJobStatusTypes::Failed_PostCommands:
                $deployment = new Deployment();
                $deployment->find($changeEvent->next['deployment_id'] ?? 0);
                if (!$deployment->exists()) {
                    Data::debug('No deployment with id', $changeEvent->next['deployment_id'] ?? 'none');
                    return;
                }
                $deployment->checkStatus(true);
                break;
        }
    }

    private static function workspaceCreated(ChangeEvent $changeEvent): void {
        WebhookHelper::Deliver(\WebHookTypes::Workspace_Created, json_encode($changeEvent->next));
    }

    private static function workspaceUpdated(ChangeEvent $changeEvent): void {
        WebhookHelper::Deliver(\WebHookTypes::Workspace_Updated, json_encode($changeEvent->next));
    }

    private static function workspaceDeleted(ChangeEvent $changeEvent): void {
        WebhookHelper::Deliver(\WebHookTypes::Workspace_Deleted, json_encode($changeEvent->next));
    }

    private static function workspaceDeployed(ChangeEvent $changeEvent): void {
        WebhookHelper::Deliver(\WebHookTypes::Workspace_Deployed, json_encode($changeEvent->next));
    }

    private static function workspaceTerminated(ChangeEvent $changeEvent): void {
        WebhookHelper::Deliver(\WebHookTypes::Workspace_Terminated, json_encode($changeEvent->next));
    }

    private static function deploymentDeployed(ChangeEvent $changeEvent): void {
        WebhookHelper::Deliver(\WebHookTypes::Deployment_Deployed, json_encode($changeEvent->next));

        if (isset($changeEvent->next['id'])) {
            HealthCheck::StartFollowing((int) $changeEvent->next['id']);
        }
    }

    private static function deploymentHealthSettled(ChangeEvent $changeEvent): void {
        WebhookHelper::Deliver(\WebHookTypes::Deployment_Health_Changed, json_encode($changeEvent->next));
    }

    private static function deploymentHealthFollow(ChangeEvent $changeEvent): void {
        HealthCheck::Follow((int) ($changeEvent->next['deployment_id'] ?? 0), (int) ($changeEvent->next['until'] ?? 0));
    }

    private static function deploymentTerminated(ChangeEvent $changeEvent): void {
        WebhookHelper::Deliver(\WebHookTypes::Deployment_Terminated, json_encode($changeEvent->next));
    }

    private static function autoUpdateApproved(ChangeEvent $changeEvent): void {
        // Handled out of band, so by the time the job runs the update may have been rolled
        // out by hand, or deleted with its deployment. An id that is not there used to reach
        // `rollout()` on an empty entity, where `updateVersion(null)` is a `TypeError`.
        $autoUpdate = new AutoUpdate();
        $autoUpdate->find($changeEvent->next['id'] ?? 0);
        if (!$autoUpdate->exists()) {
            Data::debug('No auto update with id', $changeEvent->next['id'] ?? 'none', '- nothing to roll out');
            return;
        }
        $autoUpdate->rollout();
    }

}
