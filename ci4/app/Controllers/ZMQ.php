<?php namespace App\Controllers;

use App\Entities\AutoUpdate;
use App\Entities\Deployment;
use App\Entities\ZMQEvent;
use App\Libraries\WebHooks\WebhookHelper;
use App\Libraries\ZMQ\ChangeEvent;
use App\Models\ZMQEventModel;
use CodeIgniter\Config\Services;
use CodeIgniter\Controller;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use DebugTool\Data;
use Psr\Log\LoggerInterface;

class ZMQ extends Controller {

    private ZMQEvent $event;

    /**
     * Whether another container stored this event first and is the one running it.
     *
     * Every container is handed every event, so all but one of them have nothing to do.
     * That used to be a `die`, which ends the process without CodeIgniter's shutdown - so
     * `post_system` never ran and RestExtension never wrote an access log entry for it.
     * With more than one container that is the majority of events, invisible in the log.
     * Same reason as in `BaseController::fail()`.
     */
    private bool $handledElsewhere = false;

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

        // Check if I'm the first container to store this event
        /** @var ZMQEvent $firstEventStored */
        $firstEventStored = (new ZMQEventModel())
            ->where('identifier', $identifier)
            ->orderBy('id', 'asc')
            ->limit(1)
            ->find();
        if ($zmqEvent->id != $firstEventStored->id) {
            $zmqEvent->delete();
            Data::debug('This event is handled by another container. I am skipping it');
            $this->handledElsewhere = true;
        }
    }

    /**
     * Every action goes through here, which is where a container that lost the race stops.
     *
     * One guard rather than the same three lines at the top of nine handlers, and a plain
     * return rather than leaving the process, so the run ends the way every other request
     * does.
     *
     * @param string ...$params
     */
    public function _remap(string $method, ...$params): string {
        if ($this->handledElsewhere) {
            return '';
        }

        $this->{$method}(...$params);

        return '';
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

        $autoUpdate = new AutoUpdate();
        $autoUpdate->find($changeEvent->next['id']);
        $autoUpdate->rollout();

        Data::debug('OK');
    }

}
