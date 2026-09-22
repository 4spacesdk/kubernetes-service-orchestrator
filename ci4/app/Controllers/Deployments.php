<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\Deployment;
use App\Entities\DeploymentCronJob;
use App\Entities\DeploymentSpecification;
use App\Entities\DeploymentVolume;
use App\Entities\EnvironmentVariable;
use App\Entities\KNativeMinScaleSchedule;
use App\Entities\Label;
use App\Entities\Workspace;
use App\Exceptions\ValidationException;
use App\Interfaces\DeploymentVolumeList;
use App\Interfaces\EnvironmentVariableList;
use App\Interfaces\IntArrayInterface;
use App\Interfaces\LabelList;
use App\Libraries\Audit\Audit;
use App\Libraries\DeploymentSteps\BaseDeploymentStep;
use App\Libraries\Kubernetes\DeploymentLogs;
use App\Libraries\Kubernetes\KubeAuth;
use App\Libraries\Kubernetes\KubeHelper;
use App\Libraries\Kubernetes\LogQuery;
use App\Libraries\Push\ChangeEvent;
use App\Libraries\Push\Events;
use App\Libraries\Push\Publisher;
use App\Libraries\RequestField;
use App\Libraries\DeploymentSteps\CronjobStep;
use App\Models\KNativeMinScaleScheduleModel;
use App\Models\MigrationJobModel;
use DebugTool\Data;
use Google\ApiCore\ApiException;

class Deployments extends ResourceController {

    /**
     * @route /deployments/create
     * @method post
     * @custom true
     * @parameter int $deploymentSpecificationId parameterType=query
     * @parameter string $name parameterType=query
     * @parameter int $workspaceId parameterType=query
     * @parameter string $namespace parameterType=query
     * @parameter string $version parameterType=query
     * @return void
     * @audit entity
     */
    public function create(): void {
        $deploymentSpecification = new DeploymentSpecification();
        $deploymentSpecification->find($this->request->getGet('deploymentSpecificationId'));

        if (!$deploymentSpecification->exists()) {
            $this->fail('unknown deployment specification');
            return;
        }

        $workspace = new Workspace();
        $workspaceId = $this->request->getGet('workspaceId') ?? 0;
        if ($workspaceId) {
            $workspace->find($workspaceId);
        }

        try {
            if ($workspace->exists()) {
                $item = $workspace->addDeployment(
                    $deploymentSpecification,
                    $this->request->getGet('name') ?? null,
                    $this->request->getGet('version') ?? null
                );
            } else {
                $item = Deployment::Prepare(
                    $deploymentSpecification,
                    $this->request->getGet('namespace') ?? '',
                    $this->request->getGet('workspaceId') ?? 0,
                    $this->request->getGet('name') ?? '',
                    $this->request->getGet('version') ?? ''
                );
                $item->save();
            }
            $this->_setResource($item);
        } catch (ValidationException|ApiException|\Google\ApiCore\ValidationException $e) {
            $this->fail($e->getMessage());
            return;
        }

        $this->success();
    }

    /**
     * @route /deployments/{id}/version
     * @method put
     * @custom true
     * @param int $id
     * @parameter string $value parameterType=query
     * @return void
     * @audit entity
     */
    public function updateVersion(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown deployment');
            return;
        }

        // The rollout's error used to be dropped, so a deploy that failed answered OK.
        $error = $item->updateVersion((string) $this->request->getGet('value'));
        $this->_setResource($item);
        if ($error) {
            $this->fail("The version is saved, but the deploy failed: {$error}");
            return;
        }
        $this->success();
    }

    /**
     * @route /deployments/{id}/image-pull-policy
     * @method put
     * @custom true
     * @param int $id
     * @parameter string $value parameterType=query
     * @return void
     * @audit entity
     */
    public function updateImagePullPolicy(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown deployment');
            return;
        }

        $item->updateImagePullPolicy($this->request->getGet('value'));
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployments/{id}/environment
     * @method put
     * @custom true
     * @param int $id
     * @parameter string $value parameterType=query
     * @return void
     * @audit entity
     */
    public function updateEnvironment(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown deployment');
            return;
        }

        $item->updateEnvironment($this->request->getGet('value'));
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployments/{id}/workspace
     * @method put
     * @custom true
     * @param int $id
     * @parameter int $value parameterType=query
     * @return void
     * @audit entity
     */
    public function updateWorkspace(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown deployment');
            return;
        }

        $item->updateWorkspaceId($this->request->getGet('value'));
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployments/{id}/databaseServiceId
     * @method put
     * @custom true
     * @param int $id
     * @parameter int $value parameterType=query
     * @return void
     * @audit entity
     */
    public function updateDatabaseServiceId(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown deployment');
            return;
        }

        $item->updateDatabaseServiceId($this->request->getGet('value'));
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployments/{id}/resourceManagement
     * @method put
     * @custom true
     * @param int $id
     * @parameter int $cpuLimit parameterType=query
     * @parameter int $cpuRequest parameterType=query
     * @parameter int $memoryLimit parameterType=query
     * @parameter int $memoryRequest parameterType=query
     * @parameter int $replicas parameterType=query
     * @parameter int $knativeConcurrencyLimitSoft parameterType=query
     * @parameter int $knativeConcurrencyLimitHard parameterType=query
     * @return void
     * @audit entity
     */
    public function updateResourceManagement(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown deployment');
            return;
        }

        $item->updateResourceManagement(
            $this->request->getGet('cpuLimit'),
            $this->request->getGet('cpuRequest'),
            $this->request->getGet('memoryLimit'),
            $this->request->getGet('memoryRequest'),
            $this->request->getGet('replicas'),
            $this->request->getGet('knativeConcurrencyLimitSoft'),
            $this->request->getGet('knativeConcurrencyLimitHard'),
        );
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployments/{id}/updateManagement
     * @method put
     * @custom true
     * @param int $id
     * @parameter bool $enabled parameterType=query
     * @parameter string $tagRegex parameterType=query
     * @parameter bool $requireApproval parameterType=query
     * @return void
     * @audit entity
     */
    public function updateUpdateManagement(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown deployment');
            return;
        }

        $enabled = in_array($this->request->getGet('enabled'), ['1', 'true']);
        $tagRegex = (string) $this->request->getGet('tagRegex');

        // Both used to be silent: a pattern that cannot compile went to the debug log while
        // the caller was told it went well, and a missing one was a TypeError that escaped
        // the controller. The pattern is used as `/<pattern>$/`, so it is checked that way.
        if ($enabled && $tagRegex === '') {
            $this->fail('A tag pattern is needed to turn auto update on');
            return;
        }
        if ($tagRegex !== '' && @preg_match("/{$tagRegex}$/", '') === false) {
            $this->fail("The tag pattern '{$tagRegex}' is not a valid regular expression");
            return;
        }

        try {
            $item->updateUpdateManagement(
                $enabled,
                $tagRegex,
                in_array($this->request->getGet('requireApproval'), ['1', 'true'])
            );
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());
            return;
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployments/{id}/environment-variables
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema EnvironmentVariableList
     * @return void
     * @audit entity
     */
    public function updateEnvironmentVariables(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown deployment');
            return;
        }

        /** @var EnvironmentVariableList $body */
        $body = $this->request->getJSON();
        $values = new EnvironmentVariable();
        $values->all = array_map(
            fn(array $variable) => EnvironmentVariable::Create(...$variable),
            EnvironmentVariable::Replacements($body->values, $item->environment_variables->find())
        );
        $item->updateEnvironmentVariables($values);
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployments/{id}/volumes
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema DeploymentVolumeList
     * @return void
     * @audit entity
     */
    public function updateDeploymentVolumes(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown deployment');
            return;
        }

        /** @var DeploymentVolumeList $body */
        $body = $this->request->getJSON();
        // Before anything is written: `Create()` saves as it goes.
        if ($problem = $item->volumeCountProblem(count($body->values ?? []))) {
            $this->fail($problem);
            return;
        }
        if ($problem = $item->volumeChangeProblem($body->values ?? [])) {
            $this->fail($problem);
            return;
        }
        $values = new DeploymentVolume();
        $values->all = array_map(
            fn($data) => DeploymentVolume::Create(
                RequestField::read($data, 'type'),
                RequestField::read($data, 'mount_path'),
                RequestField::read($data, 'sub_path'),
                RequestField::read($data, 'capacity'),
                RequestField::read($data, 'volume_mode'),
                RequestField::read($data, 'reclaim_policy'),
                RequestField::read($data, 'nfs_server'),
                RequestField::read($data, 'nfs_path'),
                RequestField::read($data, 'storage_class'),
                RequestField::read($data, 'csi_driver'),
                RequestField::read($data, 'csi_volume_handle')
            ),
            $body->values
        );
        $item->updateDeploymentVolumes($values);
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployments/{id}/labels
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema LabelList
     * @return void
     * @audit entity
     */
    public function updateLabels(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown deployment');
            return;
        }

        /** @var LabelList $body */
        $body = $this->request->getJSON();
        $values = new Label();
        $values->all = array_map(
            fn($data) => Label::Create($data->name, $data->value),
            $body->values
        );
        $item->updateLabels($values);
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployment/{id}/cron-jobs
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema IntArrayInterface
     * @return void
     * @audit entity
     */
    public function updateCronJobs(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown deployment');
            return;
        }

        /** @var IntArrayInterface $body */
        $body = $this->request->getJSON();

        $values = new DeploymentCronJob();
        $pos = 0;
        $values->all = array_map(
            fn($cronJobId, $i) => DeploymentCronJob::Create($cronJobId, $pos + $i),
            $body->values,
            array_keys($body->values)
        );

        $item->updateCronJobs($values);
        $this->_setResource($item);
        $this->success();
    }

    /**
     * The name of each cron job the deployment has in the cluster, for picking one to run.
     *
     * @route /deployments/{id}/cron-jobs/names
     * @method get
     * @custom true
     * @param int $id
     * @responseSchema DeploymentCronJobNamesGetResponse
     * @return void
     */
    public function getCronJobNames(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown deployment');
            return;
        }

        try {
            Data::set('resource', ['names' => (new CronjobStep())->getCronJobNames($item)]);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());
            return;
        }
        $this->success();
    }

    /**
     * Start one of the deployment's cron jobs now, from what is deployed (#50).
     *
     * @route /deployments/{id}/cron-jobs/run
     * @method post
     * @custom true
     * @param int $id
     * @parameter string $name parameterType=query
     * @responseSchema DeploymentCronJobRunResponse
     * @return void
     * @audit deployment.run_cron_job
     */
    public function runCronJob(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown deployment');
            return;
        }

        try {
            Data::set('resource', ['job' => (new CronjobStep())->runNow($item, (string) $this->request->getGet('name'))]);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());
            return;
        }
        Audit::Record('deployment.run_cron_job', $item, ['cron_job' => (string) $this->request->getGet('name')]);
        $this->success();
    }

    /**
     * @route /deployments/{id}/knative-min-scale-schedules
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema IntArrayInterface
     * @return void
     * @audit entity
     */
    public function updateKNativeMinScaleSchedules(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown deployment');
            return;
        }

        /** @var IntArrayInterface $body */
        $body = $this->request->getJSON();

        if (isset($body->values) && is_array($body->values) && count($body->values) > 0) {
            $values = (new KNativeMinScaleScheduleModel())
                ->whereIn('id', $body->values)
                ->find();
        } else {
            $values = new KNativeMinScaleSchedule();
        }

        $item->updateKNativeMinScaleSchedules($values);
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployments/{id}/status
     * @method get
     * @custom true
     * @param int $id
     * @return void
     */
    public function getStatus(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown deployment');
            return;
        }

        $item->checkStatus(true);
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /deployments/{id}/migration-jobs
     * @method get
     * @custom true
     * @param int $id
     * @responseSchema MigrationJob
     * @return void
     */
    public function getMigrationJobs(int $id): void {
        $this->queryParser->parseFilter("deployment_id:$id");
        $items = (new MigrationJobModel())->restGet(0, $this->queryParser);
        $this->_setResources($items);
        $this->success();
    }

    /**
     * @route /deployments/{id}/deployment-specification
     * @method get
     * @custom true
     * @param int $id
     * @responseSchema DeploymentSpecification
     * @return void
     */
    public function getDeploymentSpecification(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown deployment');
            return;
        }

        $spec = $item->findDeploymentSpecification();

        $result = $spec->toArray();
        $result['deploymentSteps'] = array_map(fn(BaseDeploymentStep $step) => $step->toArray(), $spec->getDeploymentSteps($item));

        Data::set('resource', $result);
        $this->success();
    }

    /**
     * The last lines from every pod of the deployment, as one log.
     *
     * @route /deployments/{id}/logs
     * @method get
     * @custom true
     * @param int $id
     * @parameter bool $previous parameterType=query
     * @parameter int $sinceSeconds parameterType=query
     * @return void
     * @responseSchema DeploymentLogEntry
     */
    public function getLogs(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown deployment');
            return;
        }

        $previous = (bool) $this->request->getGet('previous');
        $sinceSeconds = LogQuery::WindowFrom($this->request->getGet('sinceSeconds'));

        try {
            $logs = (new DeploymentLogs((new KubeAuth())->streaming()))->recent($item, $previous, $sinceSeconds);
        } catch (\Throwable $e) {
            $this->fail(KubeHelper::PrintException($e));
            return;
        }

        Data::set('resources', $logs);
        $this->success();
    }

    /**
     * Follow every pod of the deployment in this one request, pushing the lines as they come.
     *
     * One process for the whole deployment rather than one per pod, and it ends itself - see
     * `DeploymentLogs`. The answer comes when it does.
     *
     * @route /deployments/{id}/logs/watch
     * @method put
     * @custom true
     * @param int $id
     * @return void
     * @audit none reads logs - PUT only for its body
     */
    public function watchLogs(int $id): void {
        $item = new Deployment();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown deployment');
            return;
        }

        try {
            (new DeploymentLogs((new KubeAuth())->streaming()))->follow($item, function (array $lines) use ($item) {
                Publisher::getInstance()->send(
                    Events::Deployment_Logs_Watch($item->id),
                    (new ChangeEvent(null, $lines))->toArray()
                );
            });
        } catch (\Throwable $e) {
            $this->fail(KubeHelper::PrintException($e));
            return;
        }

        $this->success();
    }

    /**
     * Empty on purpose - and routed anyway.
     *
     * `@ignore true` keeps the verb out of the route generator and swagger, but the init
     * migration wrote `post deployments` and `put deployments` into `api_routes` back in 2023 and
     * nothing removed them. Both still answer 200 with an entirely empty body: `success()`
     * is never called, so there is no envelope at all - no status, no error. A generated
     * client calling them is told the write succeeded. Closing them takes a
     * migration that deletes the rows.
     *
     * @return void
     * @ignore true
     */
    public function post() {
    }

    /**
     * Empty on purpose - and routed anyway. See `post()` above.
     *
     * @param $id
     * @return void
     * @ignore true
     */
    public function put($id = 0) {
    }

}
