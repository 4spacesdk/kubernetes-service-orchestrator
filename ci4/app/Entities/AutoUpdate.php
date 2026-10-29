<?php namespace App\Entities;

use App\Libraries\Kubernetes\KubeHelper;
use App\Libraries\PostUpdateActions\PostUpdateActionHelper;
use App\Libraries\Push\ChangeEvent;
use App\Libraries\Push\Events;
use App\Libraries\Push\Publisher;
use App\Models\AutoUpdateModel;
use App\Models\DeploymentModel;
use App\Models\WorkspaceModel;
use Config\Database;
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

    /** Seconds a rollout waits for the one ahead of it on the same deployment. The test suite shortens it. */
    public static int $lockWait = 600;

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
        $locked = false;
        try {
            $this->takeLock();
            $locked = true;
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
            // Only once the log is saved: the next rollout of the deployment reads it to see
            // whether this one got there first - see `isOvertaken()`.
            if ($locked) {
                $this->releaseLock();
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

        if ($this->isOvertaken()) {
            Data::debug('Skip rollout because a later update of the deployment has rolled out');
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

    /**
     * Several workers take jobs off the queue at once, and two updates of one deployment would
     * otherwise roll out side by side: two deploys of the same objects, and the migration job
     * of one deleted under the other. Each waits for the one ahead of it instead; updates of
     * other deployments go on meanwhile.
     *
     * Followed by the database's name: a lock is the MySQL server's, not the database's.
     */
    public static function LockName(int $deploymentId): string {
        return 'kso-rollout-' . Database::connect()->getDatabase() . '-' . $deploymentId;
    }

    /**
     * @throws \RuntimeException when the rollout ahead has not finished in `$lockWait` seconds.
     */
    private function takeLock(): void {
        $row = Database::connect()
            ->query('SELECT GET_LOCK(?, ?) AS taken', [self::LockName((int) $this->deployment_id), self::$lockWait])
            ->getRow();
        if ((int) ($row->taken ?? 0) !== 1) {
            throw new \RuntimeException("Another rollout of deployment {$this->deployment_id} did not finish within " . self::$lockWait . ' seconds');
        }
    }

    private function releaseLock(): void {
        Database::connect()->query('SELECT RELEASE_LOCK(?)', [self::LockName((int) $this->deployment_id)]);
    }

    /**
     * Two workers can take two updates of one deployment at the same moment, and the later
     * one may get the lock first. The earlier one rolled out after it would put the older tag
     * back. A rollout writes its log as it ends, under the lock, so a later update with a log
     * has been.
     *
     * Later by when it was approved, not by id: Re-approve rolls an old update out again, and
     * that is meant to win over the ones before it.
     */
    private function isOvertaken(): bool {
        $db = Database::connect();
        $approved = $db->escape((string) $this->approved_date);
        return $db->table('auto_updates')
            ->where('deployment_id', $this->deployment_id)
            ->where('is_approved', true)
            ->where("(approved_date > {$approved} OR (approved_date = {$approved} AND id > " . (int) $this->id . '))', null, false)
            ->where("log != ''", null, false)
            ->countAllResults() > 0;
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
