<?php namespace App\Entities;

use App\Libraries\Kubernetes\KubeHelper;
use App\Libraries\PodioLib;
use App\Libraries\ZMQ\ChangeEvent;
use App\Libraries\ZMQ\Events;
use App\Libraries\ZMQ\ZMQProxy;
use DebugTool\Data;
use RestExtension\Core\Entity;

/**
 * Class AutoUpdate
 * @package App\Entities
 * @property int $deployment_id
 * @property Deployment $deployment
 * @property string $image
 * @property string $previous_tag
 * @property string $next_tag
 * @property bool $is_approved
 * @property string $approved_date
 * @property string $log
 */
class AutoUpdate extends Entity {

    public function approve(): void {
        $this->is_approved = true;
        $this->approved_date = date('Y-m-d H:i:s');
        $this->save();

        ZMQProxy::getInstance()->send(
            Events::AutoUpdate_Approved(),
            (new ChangeEvent(null, $this->toArray()))->toArray()
        );
    }

    public function rollout(): void {
        Data::debug('rollout', $this->image, $this->next_tag);

        $deployment = new Deployment();
        $deployment->find($this->deployment_id);
        $deployment->updateVersion($this->next_tag);

        if ($deployment->enable_podio_notification && $deployment->workspace_id) {
            try {
                PodioLib::Notify($deployment, fn($log) => $this->appendLog($log));
            } catch (\Exception $e) {
                $this->appendLog('Failed to notify podio');
                $this->appendLog(KubeHelper::PrintException($e));
            }
        }

        ZMQProxy::getInstance()->send(
            Events::AutoUpdate_RolledOut(),
            (new ChangeEvent(null, $this->toArray()))->toArray()
        );
    }

    public function appendLog(string $log): void {
        $this->log .= $log . "\n";
        $this->save();
        Data::debug(get_class($this), $log);
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|AutoUpdate[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
