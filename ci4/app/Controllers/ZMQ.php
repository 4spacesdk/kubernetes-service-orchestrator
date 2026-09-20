<?php namespace App\Controllers;

use App\Entities\AutoUpdate;
use App\Entities\Deployment;
use App\Entities\ZMQEvent;
use App\Libraries\WebHooks\WebhookHelper;
use App\Libraries\ZMQ\ChangeEvent;
use CodeIgniter\Config\Services;
use CodeIgniter\Controller;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use DebugTool\Data;
use Psr\Log\LoggerInterface;

/**
 * The controller the zmq client calls into when something happened elsewhere in kso.
 *
 * **One container raises an event and the same container runs it.** `ZMQProxy` publishes to
 * `tcp://localhost:9101` and the subscriber reads `localhost:9100` - both this pod's own
 * WAMP router, all three processes started by the same entrypoint - so an event never
 * leaves the pod it was raised on.
 *
 * There used to be a deduplication here: store a row, then check whether it was the lowest
 * numbered one for that `identifier`, and stand down if not. It could not fire. Nothing
 * hands the same event to two containers, and even if something did the two would not
 * agree on a key - the identifier is minted by whichever router received the publish
 * (`PHPClient::onResource()` sets `uniqid()`), not by the publisher. What it could do was
 * the freak case: two routers landing on the same `uniqid()` in the same microsecond, and
 * one of two unrelated events silently dropped.
 *
 * The row in `zmq_events` is kept as a log of what arrived - `CleanupZmqEvents` is what
 * stops it growing for ever, which nothing used to do. Making push reach browsers on other
 * pods is a separate piece of work, and a real queue with one delivery per message is the
 * shape that would need, rather than a broadcast and a lock.
 */
class ZMQ extends Controller {

    private ZMQEvent $event;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger) {
        parent::initController($request, $response, $logger);

        if (!is_cli()) {
            // The routes for this controller are `$routes->cli()`, so this is belt and
            // braces - but an exception rather than a `die`, so the framework still shuts
            // down and says what happened.
            throw PageNotFoundException::forPageNotFound('Only CLI is allowed to enter this controller');
        }

        $request = Services::clirequest();
        $identifier = $request->getOption('identifier');
        $event = $request->getOption('event');
        $data = base64_decode($request->getOption('data'));
        $zmqEvent = new ZMQEvent();
        $zmqEvent->identifier = $identifier;
        $zmqEvent->event = $event;
        $zmqEvent->data = json_encode(json_decode($data), JSON_PRETTY_PRINT);
        $zmqEvent->save();
        $this->event = $zmqEvent;
    }

    public function migrationJobChangedStatus(): void {
        $changeEvent = ChangeEvent::Parse(json_decode($this->event->data, true));

        switch ($changeEvent->next['status']) {
            case \MigrationJobStatusTypes::Completed:
            case \MigrationJobStatusTypes::Failed_LogVerification:
            case \MigrationJobStatusTypes::Failed_PostCommands:
                sleep(5); // TODO Wait for job to be finished.
                $deployment = new Deployment();
                $deployment->find($changeEvent->next['deployment_id']);
                $deployment->checkStatus(true);
                break;
        }

        Data::debug('OK');
    }

    public function workspaceCreated(): void {
        $changeEvent = ChangeEvent::Parse(json_decode($this->event->data, true));
        WebhookHelper::Deliver(
            \WebHookTypes::Workspace_Created,
            json_encode($changeEvent->next)
        );
        Data::debug('OK');
    }

    public function workspaceUpdated(): void {
        $changeEvent = ChangeEvent::Parse(json_decode($this->event->data, true));
        WebhookHelper::Deliver(
            \WebHookTypes::Workspace_Updated,
            json_encode($changeEvent->next)
        );
        Data::debug('OK');
    }

    public function workspaceDeleted(): void {
        $changeEvent = ChangeEvent::Parse(json_decode($this->event->data, true));
        WebhookHelper::Deliver(
            \WebHookTypes::Workspace_Deleted,
            json_encode($changeEvent->next)
        );
        Data::debug('OK');
    }

    public function workspaceDeployed(): void {
        $changeEvent = ChangeEvent::Parse(json_decode($this->event->data, true));
        WebhookHelper::Deliver(
            \WebHookTypes::Workspace_Deployed,
            json_encode($changeEvent->next)
        );
        Data::debug('OK');
    }

    public function workspaceTerminated(): void {
        $changeEvent = ChangeEvent::Parse(json_decode($this->event->data, true));
        WebhookHelper::Deliver(
            \WebHookTypes::Workspace_Terminated,
            json_encode($changeEvent->next)
        );
        Data::debug('OK');
    }

    public function deploymentDeployed(): void {
        $changeEvent = ChangeEvent::Parse(json_decode($this->event->data, true));
        WebhookHelper::Deliver(
            \WebHookTypes::Deployment_Deployed,
            json_encode($changeEvent->next)
        );
        Data::debug('OK');
    }

    public function deploymentTerminated(): void {
        $changeEvent = ChangeEvent::Parse(json_decode($this->event->data, true));
        WebhookHelper::Deliver(
            \WebHookTypes::Deployment_Terminated,
            json_encode($changeEvent->next)
        );
        Data::debug('OK');
    }

    public function autoUpdateApproved(): void {
        $changeEvent = ChangeEvent::Parse(json_decode($this->event->data, true));

        // The event is handled out of band, so by the time it arrives the update may have
        // been rolled out by hand, or deleted with its deployment. Asked before it is used:
        // an id that is not there used to reach `rollout()` on an empty entity, where
        // `updateVersion(null)` is a `TypeError` - the whole run lost, rather than a line
        // saying there was nothing to do.
        $autoUpdate = new AutoUpdate();
        $autoUpdate->find($changeEvent->next['id'] ?? 0);
        if (!$autoUpdate->exists()) {
            Data::debug('No auto update with id', $changeEvent->next['id'] ?? 'none', '- nothing to roll out');
            return;
        }

        $autoUpdate->rollout();

        Data::debug('OK');
    }

}
