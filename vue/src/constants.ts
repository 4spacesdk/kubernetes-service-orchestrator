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
    EmailService, InitContainer, OAuthClient,
    User, Webhook,
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
    DeploymentSpecificationUpdateEnvironmentVariablesDialog_Input
} from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateEnvironmentVariablesDialog.vue";
import type {
    DeploymentSpecificationUpdateEnvironmentVariableDialog_Input
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
import type {OAuthClientEditDialog_Input} from "@/components/Dialogs/Integrations/OAuthClients/OAuthClientEditDialog.vue";
import type {WebhookEditDialog_Input} from "@/components/Dialogs/Integrations/Webhooks/WebhookEditDialog.vue";
import type {
    WebhookDeliveryListDialog_Input
} from "@/components/Dialogs/Integrations/Webhooks/Deliveries/WebhookDeliveryListDialog.vue";
import type {
    DeploymentUpdateVolumesDialog_Input
} from "@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateVolumesDialog.vue";
import type {
    DeploymentUpdateVolumeDialog_Input
} from "@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateVolumeDialog.vue";
import type {
    DeploymentSpecificationUpdateIngressesDialog_Input
} from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateIngressesDialog.vue";
import type {
    DeploymentSpecificationUpdateIngressDialog_Input
} from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateIngressDialog.vue";
import type {
    DeploymentSpecificationUpdateServiceAnnotationsDialog_Input
} from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateServiceAnnotationsDialog.vue";
import type {
    DeploymentSpecificationUpdateServiceAnnotationDialog_Input
} from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateServiceAnnotationDialog.vue";
import type {
    WorkspaceUpdateLabelDialog_Input
} from "@/components/Dialogs/Workspaces/UpdateDialogs/WorkspaceUpdateLabelDialog.vue";
import type {
    WorkspaceUpdateLabelsDialog_Input
} from "@/components/Dialogs/Workspaces/UpdateDialogs/WorkspaceUpdateLabelsDialog.vue";
import type {
    DeploymentPackageUpdateEnvironmentVariablesDialog_Input
} from "@/components/Dialogs/Setup/DeploymentPackages/UpdateDialogs/DeploymentPackageUpdateEnvironmentVariablesDialog.vue";
import type {
    DeploymentPackageUpdateEnvironmentVariableDialog_Input
} from "@/components/Dialogs/Setup/DeploymentPackages/UpdateDialogs/DeploymentPackageUpdateEnvironmentVariableDialog.vue";
import type {
    DeploymentSpecificationUpdateQuickCommandsDialog_Input
} from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateQuickCommandsDialog.vue";
import type {
    DeploymentSpecificationUpdateQuickCommandDialog_Input
} from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateQuickCommandDialog.vue";
import type {InitContainerEditDialog_Input} from "@/components/Dialogs/Setup/InitContainers/InitContainerEditDialog.vue";
import type {
    DeploymentSpecificationUpdateInitContainersDialog_Input
} from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateInitContainersDialog.vue";
import type {
    InitContainerUpdateEnvironmentVariableDialog_Input
} from "@/components/Dialogs/Setup/InitContainers/UpdateDialogs/InitContainerUpdateEnvironmentVariableDialog.vue";
import type {
    InitContainerUpdateEnvironmentVariablesDialog_Input
} from "@/components/Dialogs/Setup/InitContainers/UpdateDialogs/InitContainerUpdateEnvironmentVariablesDialog.vue";
import type {
    DeploymentPackageUpdateLabelDialog_Input
} from "@/components/Dialogs/Setup/DeploymentPackages/UpdateDialogs/DeploymentPackageUpdateLabelDialog.vue";
import type {
    DeploymentPackageUpdateLabelsDialog_Input
} from "@/components/Dialogs/Setup/DeploymentPackages/UpdateDialogs/DeploymentPackageUpdateLabelsDialog.vue";

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
    deploymentUpdateEnvironmentVariables: DeploymentUpdateEnvirontmentVariablesDialog_Input;
    deploymentUpdateEnvironmentVariable: DeploymentUpdateEnvirontmentVariableDialog_Input;
    deploymentUpdateVolumes: DeploymentUpdateVolumesDialog_Input;
    deploymentUpdateVolume: DeploymentUpdateVolumeDialog_Input;
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
    workspaceUpdateLabels: WorkspaceUpdateLabelsDialog_Input;
    workspaceUpdateLabel: WorkspaceUpdateLabelDialog_Input;

    podTerminal: PodTerminalDialog_Input;

    containerImageSaved: ContainerImage | undefined;
    containerImageEdit: ContainerImageEditDialog_Input;
    containerImageEditDialog_closed: ContainerImage | undefined;

    deploymentSpecificationSaved: DeploymentSpecification | undefined;
    deploymentSpecificationEdit: DeploymentSpecificationEditDialog_Input;
    deploymentSpecificationEditDialog_closed: DeploymentSpecification | undefined;
    deploymentSpecificationUpdateEnvironmentVariables: DeploymentSpecificationUpdateEnvironmentVariablesDialog_Input;
    deploymentSpecificationUpdateEnvironmentVariable: DeploymentSpecificationUpdateEnvironmentVariableDialog_Input;
    deploymentSpecificationUpdatePostCommands: DeploymentSpecificationUpdatePostCommandsDialog_Input;
    deploymentSpecificationUpdatePostCommand: DeploymentSpecificationUpdatePostCommandDialog_Input;
    deploymentSpecificationUpdateQuickCommands: DeploymentSpecificationUpdateQuickCommandsDialog_Input;
    deploymentSpecificationUpdateQuickCommand: DeploymentSpecificationUpdateQuickCommandDialog_Input;
    deploymentSpecificationUpdateServicePorts: DeploymentSpecificationUpdateServicePortsDialog_Input;
    deploymentSpecificationUpdateServicePort: DeploymentSpecificationUpdateServicePortDialog_Input;
    deploymentSpecificationUpdateIngresses: DeploymentSpecificationUpdateIngressesDialog_Input;
    deploymentSpecificationUpdateIngress: DeploymentSpecificationUpdateIngressDialog_Input;
    deploymentSpecificationUpdateIngressRulePaths: DeploymentSpecificationUpdateIngressRulePathsDialog_Input;
    deploymentSpecificationUpdateIngressRulePath: DeploymentSpecificationUpdateIngressRulePathDialog_Input;
    deploymentSpecificationUpdateClusterRoleRules: DeploymentSpecificationUpdateClusterRoleRulesDialog_Input;
    deploymentSpecificationUpdateClusterRoleRule: DeploymentSpecificationUpdateClusterRoleRuleDialog_Input;
    deploymentSpecificationUpdateServiceAnnotations: DeploymentSpecificationUpdateServiceAnnotationsDialog_Input;
    deploymentSpecificationUpdateServiceAnnotation: DeploymentSpecificationUpdateServiceAnnotationDialog_Input;
    deploymentSpecificationUpdateInitContainers: DeploymentSpecificationUpdateInitContainersDialog_Input;

    deploymentPackageSaved: DeploymentPackage | undefined;
    deploymentPackageEdit: DeploymentPackageCreateDialog_Input;
    deploymentPackageEditDialog_closed: DeploymentPackage | undefined;
    deploymentPackageUpdateDeploymentSpecification: DeploymentPackageUpdateDeploymentSpecificationDialog_Input;
    deploymentPackageUpdateDeploymentSpecifications: DeploymentPackageUpdateDeploymentSpecificationsDialog_Input;
    deploymentPackageUpdateEnvironmentVariables: DeploymentPackageUpdateEnvironmentVariablesDialog_Input;
    deploymentPackageUpdateEnvironmentVariable: DeploymentPackageUpdateEnvironmentVariableDialog_Input;
    deploymentPackageUpdateLabels: DeploymentPackageUpdateLabelsDialog_Input;
    deploymentPackageUpdateLabel: DeploymentPackageUpdateLabelDialog_Input;

    oauthClientSaved: OAuthClient | undefined;
    oauthClientEdit: OAuthClientEditDialog_Input;
    oauthClientEditDialog_closed: OAuthClient | undefined;

    webhookSaved: Webhook | undefined;
    webhookEdit: WebhookEditDialog_Input;
    webhookEditDialog_closed: Webhook | undefined;
    webhookDeliveryList: WebhookDeliveryListDialog_Input;

    initContainerEdit: InitContainerEditDialog_Input;
    initContainerSaved: InitContainer | undefined;
    initContainerEditDialog_closed: InitContainer | undefined;
    initContainerUpdateEnvironmentVariables: InitContainerUpdateEnvironmentVariablesDialog_Input;
    initContainerUpdateEnvironmentVariable: InitContainerUpdateEnvironmentVariableDialog_Input;
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

export const KeelHookStatusTypes = {
    New: 'new',
    Running: 'running',
    Finished: 'finished',
    Error: 'error',
};

export const ContainerImageTagPolicies = {
    MatchDeployment: 'match-deployment',
    Static: 'static',
};

export const ImagePullPolicies = {
    IfNotPresent: 'IfNotPresent',
    Always: 'Always',
    Never: 'Never',
};

export const MigrationVerificationTypes = {
    EndsWith: 'ends-with',
    Regex: 'regex'
};

export const RbacPermissions = {
    Developer: 'developer',
    Workspaces: {
        Get: 'workspaces.get',
        List: 'workspaces.list',
        Create: 'workspaces.create',
        Update: 'workspaces.update',
        Delete: 'workspaces.delete',
    },
    Users: {
        Get: 'users.get',
        List: 'users.list',
        Create: 'users.create',
        Update: 'users.update',
        Delete: 'users.delete',
    },
}
