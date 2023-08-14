<?php namespace App\Entities;

use App\Libraries\DeploymentSteps\DeploymentStep;
use App\Libraries\DeploymentSteps\MigrationJobStep;
use App\Libraries\Kubernetes;
use App\Libraries\PodioLib;
use App\Libraries\ZMQ\ChangeEvent;
use App\Libraries\ZMQ\Events;
use App\Libraries\ZMQ\ZMQProxy;
use App\Models\DeploymentModel;
use App\Models\WorkspaceModel;
use DebugTool\Data;
use RestExtension\Core\Entity;

/**
 * Class KeelHookQueueItem
 * @package App\Entities
 * @property string $data
 * @property string $log
 * @property string $status
 */
class KeelHookQueueItem extends Entity {

    public function run(): void {
        $this->updateStatus(\KeelHookStatusTypes::Running);

        $data = json_decode($this->data, true);
        Data::debug($data);

        $event = $data['type'];
        $namespace = $data['metadata']['namespace'];
        $name = $data['metadata']['name'];
        $messageParts = explode(':', $data['message']);
        $tag = substr($messageParts[1], 0, -1);

        /** @var Deployment $deployment */
        $deployment = (new DeploymentModel())
            ->includeRelated(WorkspaceModel::class)
            ->where('name', $name)
            ->where('namespace', $namespace)
            ->find();
        if (!$deployment->exists()) {
            $this->delete();
            return;
        }

        switch ($event) {
            case 'preparing deployment update':
                $deployment->updateVersion($tag, false);

                if ($deployment->findDeploymentSpecification()->hasDeploymentStep($deployment, MigrationJobStep::class)) {
                    $this->appendLog('Migration job started');
                    $migrationStep = new MigrationJobStep();
                    $errors = $migrationStep->tryExecuteDeployCommand($deployment);
                    if ($errors) {
                        $this->appendLog('error cause of startMigrationJob failed');
                        $this->appendLog($errors);
                        $this->updateStatus(\KeelHookStatusTypes::Error);
                    } else {
                        $this->appendLog('All good');
                        $this->updateStatus(\KeelHookStatusTypes::Finished);
                    }
                } else {
                    $this->appendLog('No migration step - All good');
                    $this->updateStatus(\KeelHookStatusTypes::Finished);
                }

                break;
            case 'deployment update':

                // Wait for new containers to finish
                try {
                    $this->appendLog('Wait for new containers to finish');
                    $deploymentStep = new DeploymentStep();
                    $timeout = 10;
                    while ($timeout-- > 0 && $deploymentStep->hasNonReadyContainer($deployment)) {
                        $this->appendLog('Found non-ready containers. Wait 5 seconds and try again.');
                        sleep(5);
                    }
                    if ($timeout == 0) {
                        $this->appendLog('Timed out waiting for containers to get ready');
                    }
                } catch (\Exception $exception) {
                    $error = Kubernetes\KubeHelper::PrintException($exception);
                    $this->appendLog('failed to check Deployment containers');
                    $this->appendLog($error);
                    $this->updateStatus(\KeelHookStatusTypes::Error);
                }

                $this->appendLog('All good. Checking for podio notification');

                if ($this->checkForPodioNotification($deployment)) {
                    $this->updateStatus(\KeelHookStatusTypes::Finished);
                }
                break;
            default:
                $this->appendLog('error cause of unknown event ' . $event);
                $this->updateStatus(\KeelHookStatusTypes::Error);
                break;
        }

    }

    public function appendLog(string $log): void {
        $this->log .= $log . "\n";
        $this->save();
        Data::debug(get_class($this), $log);
    }

    private function updateStatus(string $status): void {
        $this->status = $status;
        $this->save();

        ZMQProxy::getInstance()->send(
            Events::KeelHookQueueItem_Changed_Status($this->id),
            (new ChangeEvent(null, $this->toArray()))->toArray()
        );
    }

    private function checkForPodioNotification(Deployment $deployment): bool {
        // Requirements
        // 1) Must have related workspace
        // 2) Related workspace must be of type "minor" or "patch"
        if (!$deployment->workspace_id) {
            $this->appendLog('checkForPodioNotification: ignored cause of missing workspace id');
            return true;
        }
        $workspace = new Workspace();
        $workspace->find($deployment->workspace_id);
        if (!$workspace->exists()) {
            $this->appendLog('checkForPodioNotification: ignored cause of unknown workspace');
            return false;
        }

        if (!$deployment->enable_podio_notification) {
            $this->appendLog('checkForPodioNotification: ignored cause of deployment package');
            return true;
        }

        try {
            PodioLib::Notify($deployment, fn($log) => $this->appendLog($log));
            $this->appendLog('checkForPodioNotification: all good');
            return true;
        } catch (\Exception $e) {
            $this->appendLog('checkForPodioNotification: failed to notify podio');
            $this->appendLog(Kubernetes\KubeHelper::PrintException($e));
            $this->updateStatus(\KeelHookStatusTypes::Error);
            return false;
        }
    }

    public function delete($related = null) {
        parent::delete($related);
        ZMQProxy::getInstance()->send(
            Events::KeelHookQueueItem_Deleted(),
            (new ChangeEvent(null, $this->toArray()))->toArray()
        );
    }

    /**
     * @return \ArrayIterator|Entity[]|\Traversable|KeelHookQueueItem[]
     */
    public function getIterator() {
        return parent::getIterator();
    }

}
