<?php namespace App\Controllers;

use App\Entities\MigrationJob;
use App\Libraries\Audit\Audit;
use App\Models\MigrationJobModel;
use DebugTool\Data;

class MigrationJobs extends \App\Core\ResourceController {

    /**
     * @route /migration-jobs/{id}/rerun
     * @method put
     * @custom true
     * @param int $id
     * @responseSchema MigrationJob
     * @return void
     * @audit migration_job.rerun
     */
    public function rerun(int $id): void {
        /** @var MigrationJob $job */
        $job = (new MigrationJobModel())
            ->where('id', $id)
            ->find();

        if ($job->exists()) {
            $job->rerun();
        }

        if ($job->exists()) {
            Audit::Record('migration_job.rerun', $job);
        }
        Data::set('resource', $job);
        $this->success();
    }

    /**
     * @return void
     * @codeCoverageIgnore
     * @ignore true
     */
    public function post() {
    }

    /**
     * @param $id
     * @return void
     * @codeCoverageIgnore
     * @ignore true
     */
    public function put($id = 0) {
    }

    /**
     * @param $id
     * @return void
     * @codeCoverageIgnore
     * @ignore true
     */
    public function patch($id = 0) {
    }

    /**
     * @param $id
     * @return void
     * @codeCoverageIgnore
     * @ignore true
     */
    public function delete($id) {
    }

    public function requireAuth(string $method): bool {
        return true;
    }
}
