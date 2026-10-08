<?php namespace App\Entities;

use App\Libraries\Kubernetes\KubeHelper;
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
 * @property bool $is_auto_approved Approved on its own, as the deployment does not require approval
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
                    $autoUpdate->approve(automatically: true);
                }
            }
        }
    }

    /**
     * @param bool $automatically approved on its own rather than by somebody - which is news for
     *                            the menu's badge until they open Updates
     */
    public function approve(bool $automatically = false): void {
        $this->is_approved = true;
        $this->is_auto_approved = $automatically;
        $this->approved_date = date('Y-m-d H:i:s');
        $this->save();

        Publisher::getInstance()->send(
            Events::AutoUpdate_Approved(),
            (new ChangeEvent(null, $this->toArray()))->toArray()
        );
    }

    public function rollout(): void {
        // The queue worker runs one rollout after another in the same process, and the debug log
        // is kept for all of it. This one's log starts here - and is saved however it ends: rolled
        // out, skipped or thrown.
        $logFrom = count(Data::getDebugger());
        try {
            $this->rolloutLogged();
        } catch (\Throwable $e) {
            Data::debug('Rollout failed:', KubeHelper::PrintException($e));
            throw $e;
        } finally {
            // Saving it must not hide why the rollout failed.
            try {
                $this->appendLog(implode("\n", array_map(
                    fn($line) => is_string($line) ? $line : json_encode($line),
                    array_slice(Data::getDebugger(), $logFrom)
                )));
            } catch (\Throwable $e) {
                log_message('error', 'The log of auto update {id} could not be saved: {message}', ['id' => $this->id, 'message' => $e->getMessage()]);
            }
        }

        Publisher::getInstance()->send(
            Events::AutoUpdate_RolledOut(),
            (new ChangeEvent(null, $this->toArray()))->toArray()
        );
    }

    private function rolloutLogged(): void {
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
            // The actions say the new version is out - a comment on the task, a phase moved on.
            Data::debug("Deploy failed: {$error}");
            Data::debug('Skip post update actions because the deploy failed');
            return;
        }

        $postUpdateActionHelper = new PostUpdateActionHelper($deployment);
        $postUpdateActionHelper->performAll();
    }

    public function appendLog(string $log): void {
        $this->log .= $log . "\n";
        $this->save();
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
