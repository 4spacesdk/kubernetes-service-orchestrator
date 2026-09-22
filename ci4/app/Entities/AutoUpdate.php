<?php namespace App\Entities;

use App\Libraries\PostUpdateActions\PostUpdateActionHelper;
use App\Libraries\Push\ChangeEvent;
use App\Libraries\Push\Events;
use App\Libraries\Push\Publisher;
use App\Models\AutoUpdateModel;
use App\Models\DeploymentModel;
use App\Models\WorkspaceModel;
use DebugTool\Data;
use App\Core\Entity;
use DeploymentStatusTypes;

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

    /** Appended to as the update runs. */
    public const array AuditIgnoredFields = ['log'];

    public static function CheckForUpdates(string $image, string $tag): void {
        /** @var Deployment $deployments */
        $deployments = (new DeploymentModel())
            ->includeRelated(WorkspaceModel::class)
            ->where('auto_update_enabled', true)
            ->where('image', $image)
            ->whereIn('status', [
                DeploymentStatusTypes::OutOfSync,
                DeploymentStatusTypes::Synced,
            ])
            ->find();

        foreach ($deployments as $deployment) {
            if ($deployment->isInAPausedOrInactiveWorkspace()) {
                continue;
            }
            if (preg_match("/{$deployment->auto_update_tag_regex}$/", $tag)) {
                Data::debug('update', $deployment->name, $deployment->namespace, 'with', $image, $tag);

                /** @var AutoUpdate $autoUpdate */
                $autoUpdate = (new AutoUpdateModel())
                    ->where('deployment_id', $deployment->id)
                    ->where('is_approved', false)
                    ->orderBy('id', 'desc')
                    ->find();
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

        Publisher::getInstance()->send(
            Events::AutoUpdate_Approved(),
            (new ChangeEvent(null, $this->toArray()))->toArray()
        );
    }

    public function rollout(): void {
        Data::debug('rollout', $this->image, $this->next_tag);

        $deployment = new Deployment();
        $deployment->find($this->deployment_id);

        // Both halves, and neither is idle. Without `exists()` an update whose deployment
        // has been removed went on to `updateVersion()`, which sets a version and saves -
        // and saving an entity that was never loaded **inserts a new row**, so a rollout
        // created a nameless deployment in no workspace and then asked Kubernetes to deploy
        // it. And without the id check, `find(null)` loads the whole table and answers
        // `exists()` from the first row, so an update with no deployment on it would have
        // rolled its tag out onto somebody else's deployment.
        if (!$this->deployment_id || !$deployment->exists()) {
            Data::debug('Skip rollout because the deployment is gone');
            return;
        }

        if ($deployment->isInAPausedOrInactiveWorkspace()) {
            Data::debug('Skip rollout because the workspace is paused or inactive');
            return;
        }

        $error = $deployment->updateVersion($this->next_tag);
        if ($error) {
            Data::debug("Deploy failed: {$error}");
        }

        $postUpdateActionHelper = new PostUpdateActionHelper($deployment);
        $postUpdateActionHelper->performAll();

        $log = Data::getDebugger();
        if (is_array($log)) {
            try {
                $lines = implode("\n", $log);
                $this->appendLog($lines);
            } catch (\Exception $e) {
                $this->appendLog(json_encode($log, JSON_PRETTY_PRINT));
            }
        }

        Publisher::getInstance()->send(
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

        Publisher::getInstance()->send(
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
