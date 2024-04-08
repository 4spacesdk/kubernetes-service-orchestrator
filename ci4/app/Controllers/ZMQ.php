<?php namespace App\Controllers;

use App\Entities\AutoUpdate;
use App\Entities\Deployment;
use App\Entities\ZMQEvent;
use App\Libraries\WebHooks\WebhookHelper;
use App\Libraries\ZMQ\ChangeEvent;
use App\Models\ZMQEventModel;
use CodeIgniter\Config\Services;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use DebugTool\Data;
use Psr\Log\LoggerInterface;

class ZMQ extends Controller {

    private ZMQEvent $event;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger) {
        parent::initController($request, $response, $logger);

        if (!is_cli()) {
            die('Only CLI is allowed to enter this controller');
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
            // This event is handled by another container.
            Data::debug('This event is handled by another container. I am skipping it');
            die;
        }
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
                $deployment->checkStatus();
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

    public function autoUpdateApproved(): void {
        $changeEvent = ChangeEvent::Parse(json_decode($this->event->data, true));

        $autoUpdate = new AutoUpdate();
        $autoUpdate->find($changeEvent->next['id']);
        $autoUpdate->rollout();

        Data::debug('OK');
    }

}
