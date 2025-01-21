<?php namespace App\Libraries\DeploymentSteps\Helpers;

use App\Entities\Deployment;
use App\Entities\Workspace;
use App\Libraries\DeploymentSteps\BaseDeploymentStep;
use App\Libraries\DeploymentSteps\ClusterRoleBindingStep;
use App\Libraries\DeploymentSteps\ClusterRoleStep;
use App\Libraries\DeploymentSteps\ContourHttpProxyStep;
use App\Libraries\DeploymentSteps\CronjobStep;
use App\Libraries\DeploymentSteps\CustomResourceStep;
use App\Libraries\DeploymentSteps\DatabaseStep;
use App\Libraries\DeploymentSteps\DeploymentStep;
use App\Libraries\DeploymentSteps\IngressStep;
use App\Libraries\DeploymentSteps\IstioVirtualServiceStep;
use App\Libraries\DeploymentSteps\KServiceStep;
use App\Libraries\DeploymentSteps\MigrationJobStep;
use App\Libraries\DeploymentSteps\NamespaceStep;
use App\Libraries\DeploymentSteps\PersistentVolumeClaimStep;
use App\Libraries\DeploymentSteps\PersistentVolumeStep;
use App\Libraries\DeploymentSteps\RoleBindingStep;
use App\Libraries\DeploymentSteps\RoleStep;
use App\Libraries\DeploymentSteps\ServiceAccountStep;
use App\Libraries\DeploymentSteps\ServiceStep;
use App\Models\DeploymentModel;

class DeploymentStepHelper {

    const string
        DatabaseStatus_NotPerformed = 'not-performed',
        DatabaseStatus_Failed = 'failed',
        DatabaseStatus_Success = 'success';

    const string
        Namespace_Error = 'error',
        Namespace_NotFound = 'not-found',
        Namespace_Found = 'found';

    const string
        ClusterRole_NotFound = 'not-found',
        ClusterRole_Found = 'found',
        ClusterRole_NotFoundNotExpected = 'not-found-not-expected',
        ClusterRole_FoundNotExpected = 'found-not-expected';

    const string
        Role_NotFound = 'not-found',
        Role_Found = 'found',
        Role_NotFoundNotExpected = 'not-found-not-expected',
        Role_FoundNotExpected = 'found-not-expected';

    const string
        ServiceAccount_NotFound = 'not-found',
        ServiceAccount_Found = 'found';

    const string
        ClusterRoleBinding_NotFound = 'not-found',
        ClusterRoleBinding_Found = 'found',
        ClusterRoleBinding_NotFoundNotExpected = 'not-found-not-expected',
        ClusterRoleBinding_FoundNotExpected = 'found-not-expected';

    const string
        RoleBinding_NotFound = 'not-found',
        RoleBinding_Found = 'found',
        RoleBinding_NotFoundNotExpected = 'not-found-not-expected',
        RoleBinding_FoundNotExpected = 'found-not-expected';

    const string
        Deployment_NotFound = 'not-found',
        Deployment_Found = 'found';

    const string
        KService_NotFound = 'not-found',
        KService_Found = 'found';

    const string
        CustomResource_NotFound = 'not-found',
        CustomResource_Found = 'found';

    const string
        Service_NotFound = 'not-found',
        Service_Found = 'found';

    const string
        Ingress_NotFound = 'not-found',
        Ingress_Found = 'found';

    const string
        IstioVirtualService_NotFound = 'not-found',
        IstioVirtualService_Found = 'found';

    const string
        ContourHttpProxy_Error = 'error',
        ContourHttpProxy_NotFound = 'not-found',
        ContourHttpProxy_Found = 'found';

    const string
        Cronjob_NotFound = 'not-found',
        Cronjob_Found = 'found';

    const string
        MigrationJob_NotFound = 'not-found',
        MigrationJob_Completed = 'completed',
        MigrationJob_Running = 'running';

    const string
        PersistentVolume_NotFound = 'not-found',
        PersistentVolume_Found = 'found';

    const string
        PersistentVolumeClaim_NotFound = 'not-found',
        PersistentVolumeClaim_Found = 'found';



    public static function GetStep(string $identifier): ?BaseDeploymentStep {
        return match ($identifier) {
            DeploymentSteps::Namespace => new NamespaceStep(),
            DeploymentSteps::ContourHttpProxy => new ContourHttpProxyStep(),

            DeploymentSteps::Database => new DatabaseStep(),
            DeploymentSteps::ClusterRole => new ClusterRoleStep(),
            DeploymentSteps::Role => new RoleStep(),
            DeploymentSteps::ServiceAccount => new ServiceAccountStep(),
            DeploymentSteps::ClusterRoleBinding => new ClusterRoleBindingStep(),
            DeploymentSteps::RoleBinding => new RoleBindingStep(),
            DeploymentSteps::PersistentVolume => new PersistentVolumeStep(),
            DeploymentSteps::PersistentVolumeClaim => new PersistentVolumeClaimStep(),
            DeploymentSteps::Cronjob => new CronjobStep(),
            DeploymentSteps::CustomResource => new CustomResourceStep(),
            DeploymentSteps::Deployment => new DeploymentStep(),
            DeploymentSteps::KService => new KServiceStep(),
            DeploymentSteps::Service => new ServiceStep(),
            DeploymentSteps::Ingress => new IngressStep(),
            DeploymentSteps::IstioVirtualService => new IstioVirtualServiceStep(),
            DeploymentSteps::Migration => new MigrationJobStep(),
            default => null,
        };
    }

    public static function EmitTrigger(string $trigger, Deployment $deployment, ?string $reason = null): ?string {
        $deployment->checkStatus();
        if ($deployment->status == \DeploymentStatusTypes::Draft) {
            return "Deployment still in draft mode";
        }

        $steps = $deployment->findDeploymentSpecification()->getDeploymentSteps($deployment);
        foreach ($steps as $step) {
            if (in_array($trigger, $step->getTriggers())) {
                $error = $step->tryExecuteDeployCommand($deployment, $reason);;
                if ($error) {
                    return $error;
                }
            }
        }
        $deployment->checkStatus();
        return null;
    }

    public static function ExecuteWorkspaceDeployCommand(Workspace $workspace, array $triggers): ?string {
        /** @var Deployment $deployments */
        $deployments = (new DeploymentModel())
            ->where('workspace_id', $workspace->id)
            ->find();
        foreach ($deployments as $deployment) {
            foreach ($triggers as $trigger) {
                $error = DeploymentStepHelper::EmitTrigger($trigger, $deployment);
                if ($error) {
                    return $error;
                }
            }
        }
        return null;
    }

}
