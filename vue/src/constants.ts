import type {DeploymentCreateDialog_Input} from "@/components/Dialogs/Setup/Deployments/DeploymentCreateDialog.vue";
import type {ConfirmationDialog_Input} from "@/components/Dialogs/Common/ConfirmationDialog.vue";
import type {InfoDialog_Input} from "@/components/Dialogs/Common/InfoDialog.vue";
import type {
    DatabaseServiceEditDialog_Input
} from "@/components/Dialogs/Setup/DatabaseServices/DatabaseServiceEditDialog.vue";
import type {DomainCreateDialog_Input} from "@/components/Dialogs/Setup/Domains/DomainCreateDialog.vue";
import type {EmailServiceEditDialog_Input} from "@/components/Dialogs/Setup/EmailServices/EmailServiceEditDialog.vue";
import type {UserEditDialog_Input} from "@/components/Dialogs/Users/UserEditDialog.vue";
import {
    ContainerImage,
    DatabaseService,
    Deployment, DeploymentPackage, DeploymentSpecification,
    Domain,
    EmailService,
    User,
    Workspace
} from "@/core/services/Deploy/models";
import type {ToastDialog_Input} from "@/components/Dialogs/Common/Toast.vue";
import type {
    DeploymentUpdateVersionDialog_Input
} from "@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateVersionDialog.vue";
import type {
    DeploymentUpdateEnvironmentDialog_Input
} from "@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateEnvironmentDialog.vue";
import type {
    DeploymentUpdateDatabaseServiceDialog_Input
} from "@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateDatabaseServiceDialog.vue";
import type {
    DeploymentUpdateIngressDialog_Input
} from "@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateIngressDialog.vue";
import type {
    DeploymentUpdateResourceManagementDialog_Input
} from "@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateResourceManagementDialog.vue";
import type {
    DeploymentUpdateUpdateManagementDialog_Input
} from "@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateUpdateManagementDialog.vue";
import type {DeploymentResourceListDialog_Input} from "@/components/Dialogs/Setup/Deployments/DeploymentResourceListDialog.vue";
import type {DeploymentStep} from "@/core/services/Deploy/Api";
import type {JsonDialog_Input} from "@/components/Dialogs/Common/JsonDialog.vue";
import type {
    DeploymentResourcePreviewDialog_Input
} from "@/components/Dialogs/Setup/Deployments/DeploymentResourcePreviewDialog.vue";
import type {
    DeploymentUpdateEnvirontmentVariablesDialog_Input
} from "@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateEnvironmentVariablesDialog.vue";
import type {
    DeploymentUpdateEnvirontmentVariableDialog_Input
} from "@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateEnvironmentVariableDialog.vue";
import type {MigrationJobListDialog_Input} from "@/components/Dialogs/MigrationJobs/MigrationJobsListDialog.vue";
import type {WorkspaceCreateDialog_Input} from "@/components/Dialogs/Workspaces/WorkspaceCreateDialog.vue";
import type {
    WorkspaceUpdateDatabaseServiceDialog_Input
} from "@/components/Dialogs/Workspaces/UpdateDialogs/WorkspaceUpdateDatabaseServiceDialog.vue";
import type {
    WorkspaceUpdateEmailServiceDialog_Input
} from "@/components/Dialogs/Workspaces/UpdateDialogs/WorkspaceUpdateEmailServiceDialog.vue";
import type {
    WorkspaceUpdateIngressDialog_Input
} from "@/components/Dialogs/Workspaces/UpdateDialogs/WorkspaceUpdateIngressDialog.vue";
import type {
    WorkspaceUpdateNameDialog_Input
} from "@/components/Dialogs/Workspaces/UpdateDialogs/WorkspaceUpdateNameDialog.vue";
import type {WorkerDialog_Input} from "@/components/Dialogs/Common/WorkerDialog.vue";
import type {WorkspaceDeploymentListDialog_Input} from "@/components/Dialogs/Workspaces/WorkspaceDeploymentList.vue";
import type {DeploymentLogsDialog_Input} from "@/components/Dialogs/Setup/Deployments/DeploymentLogsDialog.vue";
import type {MigrationJobLogsDialog_Input} from "@/components/Dialogs/MigrationJobs/MigrationJobLogsDialog.vue";
import type {WorkspaceLogsDialog_Input} from "@/components/Dialogs/Workspaces/WorkspaceLogsDialog.vue";
import type {PodTerminalDialog_Input} from "@/components/Dialogs/Setup/Deployments/Pods/PodTerminalDialog.vue";
import type {
    ContainerImageEditDialog_Input
} from "@/components/Dialogs/Setup/ContainerImages/ContainerImageEditDialog.vue";
import type {
    DeploymentSpecificationEditDialog_Input
} from "@/components/Dialogs/Setup/DeploymentSpecifications/DeploymentSpecificationEditDialog.vue";
import type {
    DeploymentSpecificationUpdateEnvirontmentVariablesDialog_Input
} from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateEnvironmentVariablesDialog.vue";
import type {
    DeploymentSpecificationUpdateEnvirontmentVariableDialog_Input
} from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateEnvironmentVariableDialog.vue";
import type {
    DeploymentSpecificationUpdatePostCommandDialog_Input
} from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdatePostCommandDialog.vue";
import type {
    DeploymentSpecificationUpdatePostCommandsDialog_Input
} from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdatePostCommandsDialog.vue";
import type {
    DeploymentSpecificationUpdateServicePortsDialog_Input
} from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateServicePortsDialog.vue";
import type {
    DeploymentSpecificationUpdateServicePortDialog_Input
} from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateServicePortDialog.vue";
import type {
    DeploymentSpecificationUpdateIngressRulePathsDialog_Input
} from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateIngressRulePathsDialog.vue";
import type {
    DeploymentSpecificationUpdateIngressRulePathDialog_Input
} from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateIngressRulePathDialog.vue";
import type {
    DeploymentSpecificationUpdateClusterRoleRulesDialog_Input
} from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateClusterRoleRulesDialog.vue";
import type {
    DeploymentSpecificationUpdateClusterRoleRuleDialog_Input
} from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateClusterRoleRuleDialog.vue";
import type {
    DeploymentPackageCreateDialog_Input
} from "@/components/Dialogs/Setup/DeploymentPackages/DeploymentPackageEditDialog.vue";
import type {
    DeploymentPackageUpdateDeploymentSpecificationDialog_Input
} from "@/components/Dialogs/Setup/DeploymentPackages/UpdateDialogs/DeploymentPackageUpdateDeploymentSpecificationDialog.vue";
import type {
    DeploymentPackageUpdateDeploymentSpecificationsDialog_Input
} from "@/components/Dialogs/Setup/DeploymentPackages/UpdateDialogs/DeploymentPackageUpdateDeploymentSpecificationsDialog.vue";

export type Events = {
    confirm: ConfirmationDialog_Input;
    info: InfoDialog_Input;
    json: JsonDialog_Input;
    toast: ToastDialog_Input;
    worker: WorkerDialog_Input;

    databaseServiceEdit: DatabaseServiceEditDialog_Input;
    databaseServiceSaved: DatabaseService | undefined;
    databaseServiceEditDialog_closed: DatabaseService | undefined;

    deploymentCreate: DeploymentCreateDialog_Input;
    deploymentSaved: Deployment | undefined;
    deploymentUpdateVersion: DeploymentUpdateVersionDialog_Input;
    deploymentUpdateEnvironment: DeploymentUpdateEnvironmentDialog_Input;
    deploymentUpdateDatabaseService: DeploymentUpdateDatabaseServiceDialog_Input;
    deploymentUpdateIngress: DeploymentUpdateIngressDialog_Input;
    deploymentUpdateResourceManagement: DeploymentUpdateResourceManagementDialog_Input;
    deploymentUpdateUpdateManagement: DeploymentUpdateUpdateManagementDialog_Input;
    deploymentUpdateEnvirontmentVariables: DeploymentUpdateEnvirontmentVariablesDialog_Input;
    deploymentUpdateEnvirontmentVariable: DeploymentUpdateEnvirontmentVariableDialog_Input;
    deploymentResourceList: DeploymentResourceListDialog_Input;
    deploymentResourcePreview: DeploymentResourcePreviewDialog_Input;
    deployment_DeploymentStep_Status_Change: {
        deployment: Deployment,
        step: DeploymentStep
    };
    deploymentLogs: DeploymentLogsDialog_Input;

    domainCreate: DomainCreateDialog_Input;
    domainSaved: Domain | undefined;
    domainEditDialog_closed: Domain | undefined;

    emailServiceEdit: EmailServiceEditDialog_Input;
    emailServiceSaved: EmailService | undefined;
    emailServiceEditDialog_closed: EmailService | undefined;

    userEdit: UserEditDialog_Input;
    userSaved: User | undefined;
    userEditDialog_closed: User | undefined;

    migrationJobList: MigrationJobListDialog_Input;
    migrationJobLogs: MigrationJobLogsDialog_Input;

    workspaceSaved: Workspace | undefined;
    workspaceCreate: WorkspaceCreateDialog_Input;
    workspaceUpdateName: WorkspaceUpdateNameDialog_Input;
    workspaceUpdateEmailService: WorkspaceUpdateEmailServiceDialog_Input;
    workspaceUpdateDatabaseService: WorkspaceUpdateDatabaseServiceDialog_Input;
    workspaceUpdateIngress: WorkspaceUpdateIngressDialog_Input;
    workspaceDeploymentList: WorkspaceDeploymentListDialog_Input;
    workspaceLogs: WorkspaceLogsDialog_Input;

    podTerminal: PodTerminalDialog_Input;

    containerImageSaved: ContainerImage | undefined;
    containerImageEdit: ContainerImageEditDialog_Input;
    containerImageEditDialog_closed: ContainerImage | undefined;

    deploymentSpecificationSaved: DeploymentSpecification | undefined;
    deploymentSpecificationEdit: DeploymentSpecificationEditDialog_Input;
    deploymentSpecificationEditDialog_closed: DeploymentSpecification | undefined;
    deploymentSpecificationUpdateEnvironmentVariables: DeploymentSpecificationUpdateEnvirontmentVariablesDialog_Input;
    deploymentSpecificationUpdateEnvironmentVariable: DeploymentSpecificationUpdateEnvirontmentVariableDialog_Input;
    deploymentSpecificationUpdatePostCommands: DeploymentSpecificationUpdatePostCommandsDialog_Input;
    deploymentSpecificationUpdatePostCommand: DeploymentSpecificationUpdatePostCommandDialog_Input;
    deploymentSpecificationUpdateServicePorts: DeploymentSpecificationUpdateServicePortsDialog_Input;
    deploymentSpecificationUpdateServicePort: DeploymentSpecificationUpdateServicePortDialog_Input;
    deploymentSpecificationUpdateIngressRulePaths: DeploymentSpecificationUpdateIngressRulePathsDialog_Input;
    deploymentSpecificationUpdateIngressRulePath: DeploymentSpecificationUpdateIngressRulePathDialog_Input;
    deploymentSpecificationUpdateClusterRoleRules: DeploymentSpecificationUpdateClusterRoleRulesDialog_Input;
    deploymentSpecificationUpdateClusterRoleRule: DeploymentSpecificationUpdateClusterRoleRuleDialog_Input;

    deploymentPackageSaved: DeploymentPackage | undefined;
    deploymentPackageEdit: DeploymentPackageCreateDialog_Input;
    deploymentPackageEditDialog_closed: DeploymentPackage | undefined;
    deploymentPackageUpdateDeploymentSpecification: DeploymentPackageUpdateDeploymentSpecificationDialog_Input;
    deploymentPackageUpdateDeploymentSpecifications: DeploymentPackageUpdateDeploymentSpecificationsDialog_Input;

}

export const DeploymentStatusTypes = {
    Draft: 'draft',
    Deploying: 'deploying',
    Active: 'active',
    Error: 'error',
};

export const MigrationJobStatusTypes = {
    Deploying: 'deploying',
    Started: 'started',
    Completed: 'completed',
    FailedLogVerification: 'failed-log-verification',
    Failed_PostCommands: 'failed-post-commands',
};

export const ConfigurationTypes = {
    Checkbox: 'checkbox',
    Dropdown_Single: 'dropdown-single',
    TextFieldList_SingleLine: 'text-field-list',
};

export const KeelHookStatusTypes = {
    New: 'new',
    Running: 'running',
    Finished: 'finished',
    Error: 'error',
};
