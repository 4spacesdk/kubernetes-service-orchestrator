<?php namespace App\Libraries\DeploymentSteps\Helpers;

class DeploymentStepTriggers {

    const string
        Deployment_CustomResource_Updated = 'workspace',
        Deployment_Volume_Updated = 'deployment',
        Deployment_Environment_Updated = 'deployment',
        Deployment_EnvironmentVariable_Updated = 'deployment',
        Deployment_ResourceManagement_Updated = 'deployment',
        Deployment_UpdateManagement_Updated = 'deployment',
        Deployment_Version_Updated = 'deployment',
        Workspace_EmailService_Updated = 'deployment'
    ;

}
