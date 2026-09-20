<?php namespace App\Libraries\DeploymentSteps;

use App\Entities\Deployment;
use App\Entities\Domain;
use App\Entities\EnvironmentVariable;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepHelper;
use App\Libraries\DeploymentSteps\Helpers\DeploymentStepLevels;
use App\Libraries\DeploymentSteps\Helpers\DeploymentSteps;
use App\Libraries\Kubernetes\ImagePullSecrets;
use App\Libraries\Kubernetes\KubeAuth;
use App\Models\ContainerImageModel;
use App\Models\DeploymentCronJobModel;
use App\Models\DeploymentSpecificationCronJobModel;
use App\Models\EnvironmentVariableModel;
use App\Models\K8sCronJobModel;
use Cron\CronExpression;
use DebugTool\Data;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Instances\Container;
use RenokiCo\PhpK8s\Kinds\K8sCronJob;
use RenokiCo\PhpK8s\Kinds\K8sEvent;
use RenokiCo\PhpK8s\Kinds\K8sJob;
use RenokiCo\PhpK8s\Kinds\K8sPod;
use App\Entities\K8sCronJob as CronJob;

class CronjobStep extends BaseDeploymentStep {

    public function getIdentifier(): string {
        return DeploymentSteps::Cronjob;
    }

    public function getLevel(): string {
        return DeploymentStepLevels::Deployment;
    }

    public function getName(): string {
        return 'Cron Job';
    }

    public function getTriggers(): array {
        return [

        ];
    }

    public function hasPreviewCommand(): bool {
        return true;
    }

    public function hasStatusCommand(): bool {
        return true;
    }

    public function hasDeployCommand(): bool {
        return true;
    }

    public function hasTerminateCommand(): bool {
        return true;
    }

    public function hasKubernetesEvents(): bool {
        return true;
    }

    public function hasKubernetesStatus(): bool {
        return true;
    }

    public function getSuccessStatus(Deployment $deployment): string {
        return DeploymentStepHelper::Cronjob_Found;
    }

    /**
     * @throws \Exception
     */
    public function getPreview(Deployment $deployment): string {
        $resources = $this->getResources($deployment, true);

        $locals = [];
        $remotes = [];

        foreach ($resources as $resource) {
            $locals[] = $resource->toJson();

            if ($resource->exists()) {
                $exiting = $resource->get();
                $remote = json_decode($exiting->toJson(), true);
                unset($remote['metadata']['uid']);
                unset($remote['metadata']['resourceVersion']);
                unset($remote['metadata']['generation']);
                unset($remote['metadata']['creationTimestamp']);
                unset($remote['metadata']['annotations']);
                unset($remote['metadata']['managedFields']);
                unset($remote['spec']['suspend']);
                unset($remote['spec']['jobTemplate']['metadata']);
                unset($remote['spec']['jobTemplate']['spec']['template']['metadata']);
                unset($remote['spec']['jobTemplate']['spec']['template']['spec']['containers'][0]['resources']);
                unset($remote['spec']['jobTemplate']['spec']['template']['spec']['containers'][0]['terminationMessagePath']);
                unset($remote['spec']['jobTemplate']['spec']['template']['spec']['containers'][0]['terminationMessagePolicy']);
                unset($remote['spec']['jobTemplate']['spec']['template']['spec']['terminationGracePeriodSeconds']);
                unset($remote['spec']['jobTemplate']['spec']['template']['spec']['dnsPolicy']);
                unset($remote['spec']['jobTemplate']['spec']['template']['spec']['schedulerName']);
                unset($remote['status']);
                $remotes[] = json_encode($remote);
            }
        }

        return json_encode([
            'local' => $locals,
            'remote' => $remotes,
        ]);
    }

    /**
     * @throws KubernetesAPIException
     * @throws \Exception
     */
    public function getStatus(Deployment $deployment): string | array {
        $resources = $this->getResources($deployment, true);

        $result = [];
        foreach ($resources as $resource) {
            $result[] = $resource->exists() ? DeploymentStepHelper::Cronjob_Found : DeploymentStepHelper::Cronjob_NotFound;;
        }

        return $result;
    }

    public function validateDeployCommand(Deployment $deployment): ?string {
        if (strlen($deployment->name) == 0) {
            return 'Missing name';
        }
        if (strlen($deployment->namespace) == 0) {
            return 'Missing namespace';
        }

        $namespaceStep = new NamespaceStep();
        if ($namespaceStep->getStatus($deployment) != DeploymentStepHelper::Namespace_Found) {
            return 'Missing Namespace';
        }

        return null;
    }

    public function startDeployCommand(Deployment $deployment, ?string $reason = null): void {
        $resources = $this->getResources($deployment, true);
        foreach ($resources as $resource) {
            $this->apply($resource);
        }
    }

    public function startTerminateCommand(Deployment $deployment): void {
        $resources = $this->getResources($deployment, true);
        foreach ($resources as $resource) {
            $resource->synced();
            $resource->delete();
        }
    }

    public function getKubernetesEvents(Deployment $deployment): array {
        $resources = $this->getResources($deployment, true);
        $events = [];

        foreach ($resources as $resource) {
            /** @var K8sEvent $event */
            foreach ($resource->getEvents() as $event) {
                $events[] = [
                    'count' => $event->getAttribute('count'),
                    'type' => $event->getAttribute('type'),
                    'reason' => $event->getAttribute('reason'),
                    'date' => date('Y-m-d H:i:s', strtotime_($event->getAttribute('lastTimestamp'))),
                    'from' => $event->getAttribute('source')['component'] ?? '',
                    'message' => $event->getAttribute('message'),
                ];
            }
        }
        return $events;
    }

    /**
     * @throws KubernetesAPIException
     * @throws \Exception
     */
    public function getKubernetesStatus(Deployment $deployment): array {
        $resources = $this->getResources($deployment, true);
        $status = [];
        foreach ($resources as $resource) {
            $status[] = $resource->get()->getAttribute('status');
        }
        return $status;
    }

    /**
     * The name of every cron job this step deploys for the deployment, from the
     * specification's and the deployment's own alike.
     *
     * @return string[]
     * @throws \Exception
     */
    public function getCronJobNames(Deployment $deployment): array {
        return array_map(fn(K8sCronJob $resource) => $resource->getName(), $this->getResources($deployment));
    }

    /**
     * Start a run of one of the deployment's cron jobs now (#50). What
     * `kubectl create job --from=cronjob/<name>` does: the job is made from the template of
     * the cron job in the cluster, so it runs what is deployed rather than what kso would
     * deploy next. It is owned by the cron job, as kubectl's is, so it goes when the cron
     * job does.
     *
     * @return string The name of the job.
     * @throws \Exception When the name is not one of the deployment's, or the cron job is
     *     not deployed.
     */
    public function runNow(Deployment $deployment, string $name): string {
        // Before the cluster is asked: the name comes from the request.
        if (!in_array($name, $this->getCronJobNames($deployment), true)) {
            throw new \Exception("'{$name}' is not one of the deployment's cron jobs");
        }
        $resource = current(array_filter($this->getResources($deployment, true), fn(K8sCronJob $r) => $r->getName() === $name));
        if (!$resource->exists()) {
            throw new \Exception("'{$name}' is not deployed");
        }
        /** @var K8sCronJob $cronJob */
        $cronJob = $resource->get();
        $template = $cronJob->getAttribute('spec.jobTemplate', []);

        // A cron job's name is at most 52 characters, so this stays within the 63 a job's
        // pods can carry it in a label.
        $jobName = substr($name, 0, 52) . '-manual-' . substr(uniqid(), -5);

        $job = new K8sJob((new KubeAuth())->authenticate(), [
            'metadata' => [
                'name' => $jobName,
                'namespace' => $deployment->namespace,
                'labels' => $template['metadata']['labels'] ?? [],
                'annotations' => array_merge(
                    $template['metadata']['annotations'] ?? [],
                    ['cronjob.kubernetes.io/instantiate' => 'manual'],
                ),
                'ownerReferences' => [[
                    'apiVersion' => 'batch/v1',
                    'kind' => 'CronJob',
                    'name' => $name,
                    'uid' => $cronJob->getAttribute('metadata.uid'),
                    'controller' => true,
                    'blockOwnerDeletion' => true,
                ]],
            ],
            'spec' => $template['spec'] ?? [],
        ]);
        $job->create();

        return $jobName;
    }

    /**
     * @return K8sCronJob[]
     * @throws \Exception
     */
    protected function getResources(Deployment $deployment, bool $auth = false): array {
        $spec = $deployment->findDeploymentSpecification();

        /** @var CronJob $cronJobs */
        $cronJobs = (new K8sCronJobModel())
            ->includeRelated(ContainerImageModel::class)
            ->whereRelated(DeploymentSpecificationCronJobModel::class, 'deployment_specification_id', $spec->id)
            ->orderByRelated(DeploymentSpecificationCronJobModel::class, 'position', 'asc')
            ->find();

        /** @var CronJob $deploymentCronJobs */
        $deploymentCronJobs = (new K8sCronJobModel())
            ->includeRelated(ContainerImageModel::class)
            ->whereRelated(DeploymentCronJobModel::class, 'deployment_id', $deployment->id)
            ->orderByRelated(DeploymentCronJobModel::class, 'position', 'asc')
            ->find();
        foreach ($deploymentCronJobs as $deploymentCronJob) {
            $cronJobs->add($deploymentCronJob);
        }

        $resources = [];

        foreach ($cronJobs as $index => $cronJob) {

            $container = new Container();
            $container
                ->setAttribute('name', $cronJob->name)
                ->setImage(
                    $cronJob->container_image->url,
                    match ($cronJob->container_image_tag_policy) {
                        \ContainerImageTagPolicies::MatchDeployment => $deployment->version,
                        \ContainerImageTagPolicies::Static => $cronJob->container_image_tag_value,
                        \ContainerImageTagPolicies::Default => $cronJob->container_image->default_tag,
                    }
                )
                ->setAttribute('imagePullPolicy', $cronJob->container_image_pull_policy);
            if (strlen($cronJob->command)) {
                $container->setAttribute('command', [
                    EnvironmentVariable::ApplyVariablesToString($cronJob->command, $deployment),
                ]);
            }
            $args = json_decode($cronJob->args);
            if ($args != null && count($args) > 0) {
                $container->setAttribute(
                    'args',
                    array_map(fn($arg) => EnvironmentVariable::ApplyVariablesToString($arg, $deployment), $args)
                );
            }

            // Security Context
            if (strlen($cronJob->container_image->security_context_run_as_user) > 0) {
                $container->setAttribute('securityContext.runAsUser', (int)$cronJob->container_image->security_context_run_as_user);
            }
            if (strlen($cronJob->container_image->security_context_run_as_group) > 0) {
                $container->setAttribute('securityContext.runAsGroup', (int)$cronJob->container_image->security_context_run_as_group);
            }
            $container->setAttribute('securityContext.allowPrivilegeEscalation', (bool)$cronJob->container_image->security_context_allow_privilege_escalation);
            $container->setAttribute('securityContext.readOnlyRootFilesystem', (bool)$cronJob->container_image->security_context_read_only_root_filesystem);

            $envVars = [];

            if ($cronJob->include_deployment_environment_variables) {
                $specEnvVars = $spec->getEnvironmentVariables($deployment);
                foreach ($specEnvVars as $key => $value) {
                    $envVars[$key] = $value;
                }
                /** @var EnvironmentVariable $deploymentEnvironmentVariables */
                $deploymentEnvironmentVariables = (new EnvironmentVariableModel())
                    ->where('deployment_id', $deployment->id)
                    ->find();
                foreach ($deploymentEnvironmentVariables as $deploymentEnvironmentVariable) {
                    $envVars[$deploymentEnvironmentVariable->name] = $deploymentEnvironmentVariable->value;
                }
            }

            $container->addEnvs($envVars);

            if ($cronJob->cpu_request) {
                $container->minCpu($cronJob->cpu_request.'m');
            }
            if ($cronJob->cpu_limit) {
                $container->maxCpu($cronJob->cpu_limit.'m');
            }
            if ($cronJob->memory_request) {
                $container->minMemory($cronJob->memory_request, 'Mi');
            }
            if ($cronJob->memory_limit) {
                $container->maxMemory($cronJob->memory_limit, 'Mi');
            }

            $template = (new K8sPod())
                ->setContainers([$container])
                ->setSpec('restartPolicy', $cronJob->restart_policy);
            $imagePullSecrets = ImagePullSecrets::of($cronJob->container_image);
            if (count($imagePullSecrets) > 0) {
                $template->setSpec('imagePullSecrets', $imagePullSecrets);
            }

            if (strlen($cronJob->container_image->security_context_fs_group) > 0) {
                $template->setSpec('securityContext.fsGroup', (int)$cronJob->container_image->security_context_fs_group);
            }

            $resource = new K8sCronJob();
            $resource
                ->setName($cronJob->generateName($deployment))
                ->setNamespace($deployment->namespace)
                ->setSchedule(new CronExpression($cronJob->schedule))
                ->setSpec('concurrencyPolicy', $cronJob->concurrency_policy)
                ->setJobTemplate((new K8sJob())
                    ->setTemplate($template)
                );

            if (is_numeric($cronJob->successful_jobs_history_limit)) {
                $resource->setSpec('successfulJobsHistoryLimit', (int)$cronJob->successful_jobs_history_limit);
            }
            if (is_numeric($cronJob->failed_jobs_history_limit)) {
                $resource->setSpec('failedJobsHistoryLimit', (int)$cronJob->failed_jobs_history_limit);
            }

            $resources[] = $resource;
        }

        if ($auth) {
            $auth = new KubeAuth();
            $cluster = $auth->authenticate();
            foreach ($resources as $resource) {
                $resource->onCluster($cluster);
            }
        }

        return $resources;
    }

}
