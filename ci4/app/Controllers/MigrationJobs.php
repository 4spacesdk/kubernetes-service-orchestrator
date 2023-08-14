<?php namespace App\Controllers;

use App\Entities\MigrationJob;
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
     */
    public function rerun(int $id): void {
        /** @var MigrationJob $job */
        $job = (new MigrationJobModel())
            ->where('id', $id)
            ->find();

        if ($job->exists()) {
            $job->rerun();
        }

        Data::set('resource', $job);
        $this->success();
    }

    /**
     * @route /migration-jobs/{id}/started
     * @method put
     * @custom true
     * @param int $id
     * @responseSchema MigrationJob
     * @return void
     */
    public function setStarted(int $id): void {
        /** @var MigrationJob $job */
        $job = (new MigrationJobModel())
            ->where('id', $id)
            ->find();

        $job->started = date('Y-m-d H:i-s');
        $job->save();

        $job->updateStatus(\MigrationJobStatusTypes::Started);

        Data::set('resource', $job);
        $this->success();
    }

    /**
     * @route /migration-jobs/{id}/ended
     * @method put
     * @custom true
     * @param int $id
     * @responseSchema MigrationJob
     * @return void
     */
    public function setEnded(int $id): void {
        $job = new MigrationJob();
        $job->find($id);
        $job->ended = date('Y-m-d H:i-s');
        $job->log = trim(file_get_contents('php://input'));
        $job->save();

        $job->validateLog();

        Data::set('resource', $job);
        $this->success();
    }

    /**
     * @ignore true
     * @return void
     */
    public function post() {
    }

    /**
     * @ignore true
     * @param $id
     * @return void
     */
    public function put($id = 0) {
    }

    /**
     * @ignore true
     * @param $id
     * @return void
     */
    public function patch($id = 0) {
    }

    /**
     * @ignore true
     * @param $id
     * @return void
     */
    public function delete($id) {
    }

    public function requireAuth(string $method): bool {
        return false;
    }
}
