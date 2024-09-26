<?php namespace App\Entities;

use App\Libraries\Kubernetes\KubeHelper;
use App\Libraries\PodioLib;
use App\Libraries\ZMQ\ChangeEvent;
use App\Libraries\ZMQ\Events;
use App\Libraries\ZMQ\ZMQProxy;
use App\Models\DeploymentModel;
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

    public static function CheckForUpdates(string $image, string $tag): void {
        /** @var Deployment $deployments */
        $deployments = (new DeploymentModel())
            ->where('auto_update_enabled', true)
            ->where('image', $image)
            ->find();

        foreach ($deployments as $deployment) {
            if (preg_match("/{$deployment->auto_update_tag_regex}$/", $tag)) {
                Data::debug('update', $deployment->name, $deployment->namespace, 'with', $image, $tag);

                $autoUpdate = new AutoUpdate();
                $autoUpdate->deployment_id = $deployment->id;
                $autoUpdate->image = $image;
                $autoUpdate->previous_tag = $deployment->version;
                $autoUpdate->next_tag = $tag;
                $autoUpdate->save();

                if (!$deployment->auto_update_require_approval) {
                    $autoUpdate->approve();
                }
            }
        }
    }

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

    public function delete($related = null) {
        parent::delete($related);

        ZMQProxy::getInstance()->send(
            Events::AutoUpdate_Deleted(),
            (new ChangeEvent(null, $this->toArray()))->toArray()
        );
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|AutoUpdate[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
