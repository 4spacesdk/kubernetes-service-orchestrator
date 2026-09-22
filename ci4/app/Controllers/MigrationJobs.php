<?php namespace App\Controllers;

use App\Entities\MigrationJob;
use App\Libraries\Audit\Audit;
use App\Models\MigrationJobModel;
use DebugTool\Data;

class MigrationJobs extends \App\Core\ResourceController {

    /**
     * What the job's pod sends its callback token in. See `MigrationJob::issueCallbackToken()`.
     */
    public const string TokenHeader = 'X-Migration-Job-Token';

    /**
     * The job a callback is about, if it exists and the request carries its token. An id
     * that is not there used to be saved as a new row.
     */
    private function jobForCallback(int $id): ?MigrationJob {
        $job = new MigrationJob();
        $job->find($id);
        if (!$job->exists()) {
            $this->fail('No migration job with that id', 404);
            return null;
        }
        if (!$job->acceptsCallbackToken($this->request->getHeaderLine(self::TokenHeader))) {
            $this->fail('Not allowed', 401);
            return null;
        }

        return $job;
    }

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
     * @route /migration-jobs/{id}/started
     * @method put
     * @custom true
     * @param int $id
     * @responseSchema MigrationJob
     * @return void
     * @audit none the migration job's own callback; the job's row is the record
     */
    public function setStarted(int $id): void {
        $job = $this->jobForCallback($id);
        if (!$job) {
            return;
        }

        $job->started = date('Y-m-d H:i:s');
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
     * @audit none the migration job's own callback; the job's row is the record
     */
    public function setEnded(int $id): void {
        $job = $this->jobForCallback($id);
        if (!$job) {
            return;
        }

        $job->ended = date('Y-m-d H:i:s');
        $job->log = trim((string) $this->request->getBody());
        $job->save();

        $job->validateLog();

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
        return false;
    }
}
