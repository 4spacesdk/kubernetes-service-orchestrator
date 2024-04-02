<?php namespace App\Libraries\DeploymentSteps\Helpers;

use App\Entities\Deployment;
use App\Entities\Workspace;
use App\Libraries\DeploymentSteps\BaseDeploymentStep;
use App\Libraries\DeploymentSteps\ClusterRoleBindingStep;
use App\Libraries\DeploymentSteps\ClusterRoleStep;
use App\Libraries\DeploymentSteps\CronjobStep;
use App\Libraries\DeploymentSteps\DatabaseStep;
use App\Libraries\DeploymentSteps\DeploymentStep;
use App\Libraries\DeploymentSteps\IngressStep;
use App\Libraries\DeploymentSteps\MigrationJobStep;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\DeploymentSteps\PersistentVolumeClaimStep;
use App\Libraries\DeploymentSteps\PersistentVolumeStep;
use App\Libraries\DeploymentSteps\RedirectsStep;
use App\Libraries\DeploymentSteps\ServiceAccountStep;
use App\Libraries\DeploymentSteps\ServiceStep;
use App\Models\DeploymentModel;

class DeploymentStepHelper {

    const
        DatabaseStatus_NotPerformed = 'not-performed',
        DatabaseStatus_Failed = 'failed',
        DatabaseStatus_Success = 'success';

    const
        Namespace_NotFound = 'not-found',
        Namespace_Found = 'found';

    const
        ClusterRole_NotFound = 'not-found',
        ClusterRole_Found = 'found';

    const
        ServiceAccount_NotFound = 'not-found',
        ServiceAccount_Found = 'found';

    const
        ClusterRoleBinding_NotFound = 'not-found',
        ClusterRoleBinding_Found = 'found';

    const
        Deployment_NotFound = 'not-found',
        Deployment_Found = 'found';

    const
        Service_NotFound = 'not-found',
        Service_Found = 'found';

    const
        Ingress_NotFound = 'not-found',
        Ingress_Found = 'found';

    const
        Redirects_NotFound = 'not-found',
        Redirects_NotFoundNotExpected = 'not-found-not-expected',
        Redirects_FoundNotExpected = 'found-not-expected',
        Redirects_Found = 'found';

    const
        Cronjob_NotFound = 'not-found',
        Cronjob_Found = 'found';

    const
        MigrationJob_NotFound = 'not-found',
        MigrationJob_Completed = 'completed',
        MigrationJob_Running = 'running';

    const
        PersistentVolume_NotFound = 'not-found',
        PersistentVolume_Found = 'found';

    const
        PersistentVolumeClaim_NotFound = 'not-found',
        PersistentVolumeClaim_Found = 'found';



    public static function GetStep(string $identifier): ?BaseDeploymentStep {
        return match ($identifier) {
            DeploymentSteps::Database => new DatabaseStep(),
            DeploymentSteps::Namespace => new NamespaceStep(),
            DeploymentSteps::ClusterRole => new ClusterRoleStep(),
            DeploymentSteps::ServiceAccount => new ServiceAccountStep(),
            DeploymentSteps::ClusterRoleBinding => new ClusterRoleBindingStep(),
            DeploymentSteps::PersistentVolume => new PersistentVolumeStep(),
            DeploymentSteps::PersistentVolumeClaim => new PersistentVolumeClaimStep(),
            DeploymentSteps::Cronjob => new CronjobStep(),
            DeploymentSteps::Deployment => new DeploymentStep(),
            DeploymentSteps::Service => new ServiceStep(),
            DeploymentSteps::Ingress => new IngressStep(),
            DeploymentSteps::Redirects => new RedirectsStep(),
            DeploymentSteps::Migration => new MigrationJobStep(),
            default => null,
        };
    }

    /**
     * @param Deployment $deployment
     * @param string[] $stepIdentifiers
     * @return ?string
     */
    public static function ExecuteDeployCommand(Deployment $deployment, array $stepIdentifiers): ?string {
        $deployment->checkStatus();
        if ($deployment->status == \DeploymentStatusTypes::Draft) {
            return "Deployment still in draft mode";
        }

        foreach ($stepIdentifiers as $identifier) {
            $error = self::GetStep($identifier)->tryExecuteDeployCommand($deployment);
            if ($error) {
                return $error;
            }
        }
        $deployment->checkStatus();
        return null;
    }

    /**
     * @param Workspace $workspace
     * @param string[] $stepIdentifiers
     * @return ?string
     */
    public static function ExecuteWorkspaceDeployCommand(Workspace $workspace, array $stepIdentifiers): ?string {
        /** @var Deployment $deployments */
        $deployments = (new DeploymentModel())
            ->where('workspace_id', $workspace->id)
            ->find();
        foreach ($deployments as $deployment) {
            $error = self::ExecuteDeployCommand($deployment, $stepIdentifiers);
            if ($error) {
                return $error;
            }
        }
        return null;
    }

}
