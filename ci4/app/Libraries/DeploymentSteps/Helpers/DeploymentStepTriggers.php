<?php namespace App\Libraries\DeploymentSteps\Helpers;

class DeploymentStepTriggers {

    const string
        Deployment_CustomResource_Updated = 'deployment-custom-resource-updated',
        Deployment_Volume_Updated = 'deployment-volume-updated',
        Deployment_Environment_Updated = 'deployment-environment-updated',
        Deployment_EnvironmentVariable_Updated = 'deployment-environment-variable-updated',
        Deployment_ResourceManagement_Updated = 'deployment-resource-management-updated',
        Deployment_UpdateManagement_Updated = 'deployment-update-management-updated',
        Deployment_Version_Updated = 'deployment-version-updated',
        Workspace_EmailService_Updated = 'workspace-email-service-updated'
    ;

}
