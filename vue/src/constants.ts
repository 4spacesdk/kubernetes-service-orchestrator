import type { DeploymentCreateDialog_Input } from "@/components/Dialogs/Setup/Deployments/DeploymentCreateDialog.vue";
import type { ConfirmationDialog_Input } from "@/components/Dialogs/Common/ConfirmationDialog.vue";
import type { InfoDialog_Input } from "@/components/Dialogs/Common/InfoDialog.vue";
import type { DatabaseServiceEditDialog_Input } from "@/components/Dialogs/Setup/DatabaseServices/DatabaseServiceEditDialog.vue";
import type { DomainCreateDialog_Input } from "@/components/Dialogs/Setup/Domains/DomainCreateDialog.vue";
import type { EmailServiceEditDialog_Input } from "@/components/Dialogs/Setup/EmailServices/EmailServiceEditDialog.vue";
import type { ProjectEditDialog_Input } from "@/components/Dialogs/Setup/Projects/ProjectEditDialog.vue";
import type { ContainerRegistryEditDialog_Input } from "@/components/Dialogs/Integrations/ContainerRegistries/ContainerRegistryEditDialog.vue";
import type { ContainerRegistryImportDialog_Input } from "@/components/Dialogs/Integrations/ContainerRegistries/ContainerRegistryImportDialog.vue";
import type { ContainerImageDeploymentsDialog_Input } from "@/components/Dialogs/Setup/ContainerImages/ContainerImageDeploymentsDialog.vue";
import type { ContainerImageTagsDialog_Input } from "@/components/Dialogs/Setup/ContainerImages/ContainerImageTagsDialog.vue";
import type { ContainerImageScansDialog_Input } from "@/components/Dialogs/Setup/ContainerImages/ContainerImageScansDialog.vue";
import type { GithubIntegrationEditDialog_Input } from "@/components/Dialogs/Integrations/GithubIntegrations/GithubIntegrationEditDialog.vue";
import type { IntegrationDeleteDialog_Input } from "@/components/Dialogs/Integrations/IntegrationDeleteDialog.vue";
import type { DeploymentBulkUpdateVersionDialog_Input } from "@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentBulkUpdateVersionDialog.vue";
import type { UserEditDialog_Input } from "@/components/Dialogs/Users/UserEditDialog.vue";
import {
    ContainerImage,
    DatabaseService,
    Deployment,
    WorkspaceTemplate,
    DeploymentSpecification,
    Domain,
    Gateway,
    EmailService,
    Project,
    ContainerRegistry,
    GithubIntegration,
    InitContainer,
    K8sCronJob,
    KNativeMinScaleSchedule,
    OAuthClient,
    PodioIntegration,
    PostUpdateAction,
    User,
    Webhook,
    Workspace,
} from "@/core/services/Deploy/models";
import type { ToastDialog_Input } from "@/components/Dialogs/Common/Toast.vue";
import type { DeploymentResourceListDialog_Input } from "@/components/Dialogs/Setup/Deployments/DeploymentResourceListDialog.vue";
import type { DeploymentStep } from "@/core/services/Deploy/Api";
import type { JsonDialog_Input } from "@/components/Dialogs/Common/JsonDialog.vue";
import type { DeploymentResourcePreviewDialog_Input } from "@/components/Dialogs/Setup/Deployments/DeploymentResourcePreviewDialog.vue";
import type { DeploymentUpdateEnvirontmentVariableDialog_Input } from "@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateEnvironmentVariableDialog.vue";
import type { MigrationJobListDialog_Input } from "@/components/Dialogs/MigrationJobs/MigrationJobsListDialog.vue";
import type { AuditEventListDialog_Input } from "@/components/Dialogs/AuditEvents/AuditEventsListDialog.vue";
import type { WorkspaceCreateDialog_Input } from "@/components/Dialogs/Workspaces/WorkspaceCreateDialog.vue";
import type { WorkerDialog_Input } from "@/components/Dialogs/Common/WorkerDialog.vue";
import type { DeploymentLogsDialog_Input } from "@/components/Dialogs/Setup/Deployments/DeploymentLogsDialog.vue";
import type { MigrationJobLogsDialog_Input } from "@/components/Dialogs/MigrationJobs/MigrationJobLogsDialog.vue";
import type { WorkspaceLogsDialog_Input } from "@/components/Dialogs/Workspaces/WorkspaceLogsDialog.vue";
import type { PodTerminalDialog_Input } from "@/components/Dialogs/Setup/Deployments/Pods/PodTerminalDialog.vue";
import type { ContainerImageEditDialog_Input } from "@/components/Dialogs/Setup/ContainerImages/ContainerImageEditDialog.vue";
import type { DeploymentSpecificationCreateDialog_Input } from "@/components/Dialogs/Setup/DeploymentSpecifications/DeploymentSpecificationCreateDialog.vue";
import type { DeploymentSpecificationUpdateEnvironmentVariableDialog_Input } from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateEnvironmentVariableDialog.vue";
import type { DeploymentSpecificationUpdatePostCommandDialog_Input } from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdatePostCommandDialog.vue";
import type { DeploymentSpecificationUpdateServicePortDialog_Input } from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateServicePortDialog.vue";
import type { DeploymentSpecificationUpdateIngressRulePathsDialog_Input } from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateIngressRulePathsDialog.vue";
import type { DeploymentSpecificationUpdateIngressRulePathDialog_Input } from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateIngressRulePathDialog.vue";
import type { DeploymentSpecificationUpdateClusterRoleRuleDialog_Input } from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateClusterRoleRuleDialog.vue";
import type { WorkspaceTemplateCreateDialog_Input } from "@/components/Dialogs/Setup/WorkspaceTemplates/WorkspaceTemplateEditDialog.vue";
import type { WorkspaceTemplateUpdateDeploymentSpecificationDialog_Input } from "@/components/Dialogs/Setup/WorkspaceTemplates/UpdateDialogs/WorkspaceTemplateUpdateDeploymentSpecificationDialog.vue";
import type { WorkspaceTemplateUpdateDeploymentSpecificationsDialog_Input } from "@/components/Dialogs/Setup/WorkspaceTemplates/UpdateDialogs/WorkspaceTemplateUpdateDeploymentSpecificationsDialog.vue";
import type { OAuthClientEditDialog_Input } from "@/components/Dialogs/Integrations/OAuthClients/OAuthClientEditDialog.vue";
import type { WebhookEditDialog_Input } from "@/components/Dialogs/Integrations/Webhooks/WebhookEditDialog.vue";
import type { WebhookDeliveryListDialog_Input } from "@/components/Dialogs/Integrations/Webhooks/Deliveries/WebhookDeliveryListDialog.vue";
import type { DeploymentUpdateVolumeDialog_Input } from "@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateVolumeDialog.vue";
import type { DeploymentSpecificationUpdateIngressDialog_Input } from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateIngressDialog.vue";
import type { DeploymentSpecificationUpdateServiceAnnotationDialog_Input } from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateServiceAnnotationDialog.vue";
import type { WorkspaceUpdateLabelDialog_Input } from "@/components/Dialogs/Workspaces/UpdateDialogs/WorkspaceUpdateLabelDialog.vue";
import type { WorkspaceTemplateUpdateEnvironmentVariablesDialog_Input } from "@/components/Dialogs/Setup/WorkspaceTemplates/UpdateDialogs/WorkspaceTemplateUpdateEnvironmentVariablesDialog.vue";
import type { WorkspaceTemplateUpdateEnvironmentVariableDialog_Input } from "@/components/Dialogs/Setup/WorkspaceTemplates/UpdateDialogs/WorkspaceTemplateUpdateEnvironmentVariableDialog.vue";
import type { DeploymentSpecificationUpdateQuickCommandDialog_Input } from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateQuickCommandDialog.vue";
import type { InitContainerEditDialog_Input } from "@/components/Dialogs/Setup/InitContainers/InitContainerEditDialog.vue";
import type { InitContainerUpdateEnvironmentVariableDialog_Input } from "@/components/Dialogs/Setup/InitContainers/UpdateDialogs/InitContainerUpdateEnvironmentVariableDialog.vue";
import type { InitContainerUpdateEnvironmentVariablesDialog_Input } from "@/components/Dialogs/Setup/InitContainers/UpdateDialogs/InitContainerUpdateEnvironmentVariablesDialog.vue";
import type { WorkspaceTemplateUpdateLabelDialog_Input } from "@/components/Dialogs/Setup/WorkspaceTemplates/UpdateDialogs/WorkspaceTemplateUpdateLabelDialog.vue";
import type { WorkspaceTemplateUpdateLabelsDialog_Input } from "@/components/Dialogs/Setup/WorkspaceTemplates/UpdateDialogs/WorkspaceTemplateUpdateLabelsDialog.vue";
import type { PodioIntegrationEditDialog_Input } from "@/components/Dialogs/Integrations/PodioIntegrations/PodioIntegrationEditDialog.vue";
import type { PostUpdateActionUpdateConditionDialog_Input } from "@/components/Dialogs/Setup/PostUpdateActions/UpdateDialogs/PostUpdateActionUpdateConditionDialog.vue";
import type { PostUpdateActionUpdateConditionsDialog_Input } from "@/components/Dialogs/Setup/PostUpdateActions/UpdateDialogs/PostUpdateActionUpdateConditionsDialog.vue";
import type { PostUpdateActionEditDialog_Input } from "@/components/Dialogs/Setup/PostUpdateActions/PostUpdateActionEditDialog.vue";
import type { DeploymentSpecificationUpdateLabelDialog_Input } from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateLabelDialog.vue";
import type { DeploymentUpdateLabelDialog_Input } from "@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateLabelDialog.vue";
import type { DeploymentSpecificationUpdateRoleRuleDialog_Input } from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateRoleRuleDialog.vue";
import type { DeploymentSpecificationUpdateIngressAnnotationsDialog_Input } from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateIngressAnnotationsDialog.vue";
import type { DeploymentSpecificationUpdateIngressAnnotationDialog_Input } from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateIngressAnnotationDialog.vue";
import type { DeploymentSpecificationUpdateDeploymentAnnotationDialog_Input } from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateDeploymentAnnotationDialog.vue";
import type { CronJobEditDialog_Input } from "@/components/Dialogs/Setup/CronJobs/CronJobEditDialog.vue";
import type { DomainEditDialog_Input } from "@/components/Dialogs/Setup/Domains/DomainEditDialog.vue";
import type { GatewayEditDialog_Input } from "@/components/Dialogs/Setup/Gateways/GatewayEditDialog.vue";
import type { GatewayResourcePreviewDialog_Input } from "@/components/Dialogs/Setup/Gateways/GatewayResourcePreviewDialog.vue";
import type { DeploymentSpecificationUpdateHttpProxyRoutePathDialog_Input } from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateHttpProxyRouteDialog.vue";
import type { DeploymentUpdateWorkspaceDialog_Input } from "@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateWorkspaceDialog.vue";
import type { DeploymentSpecificationUpdateVolumeDialog_Input } from "@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateVolumeDialog.vue";
import type { WorkspaceTemplateUpdateDeploymentSpecificationKNativeMinScaleSchedulesDialog_Input } from "@/components/Dialogs/Setup/WorkspaceTemplates/UpdateDialogs/WorkspaceTemplateUpdateDeploymentSpecificationKNativeMinScaleSchedulesDialog.vue";
import type { KNativeMinScaleScheduleEditDialog_Input } from "@/components/Dialogs/Setup/KNativeMinScaleSchedules/KNativeMinScaleScheduleEditDialog.vue";

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
    deploymentUpdateWorkspace: DeploymentUpdateWorkspaceDialog_Input;
    deploymentUpdateEnvironmentVariable: DeploymentUpdateEnvirontmentVariableDialog_Input;
    deploymentUpdateVolume: DeploymentUpdateVolumeDialog_Input;
    deploymentResourceList: DeploymentResourceListDialog_Input;
    deploymentResourcePreview: DeploymentResourcePreviewDialog_Input;
    deployment_DeploymentStep_Status_Change: {
        deployment: Deployment;
        step: DeploymentStep;
    };
    deploymentLogs: DeploymentLogsDialog_Input;
    deploymentUpdateLabel: DeploymentUpdateLabelDialog_Input;

    domainCreate: DomainCreateDialog_Input;
    domainEdit: DomainEditDialog_Input;
    domainSaved: Domain | undefined;
    domainEditDialog_closed: Domain | undefined;

    gatewayCreate: GatewayEditDialog_Input;
    gatewayEdit: GatewayEditDialog_Input;
    gatewaySaved: Gateway | undefined;
    gatewayEditDialog_closed: Gateway | undefined;
    gatewayResourcePreview: GatewayResourcePreviewDialog_Input;

    emailServiceEdit: EmailServiceEditDialog_Input;
    emailServiceSaved: EmailService | undefined;
    emailServiceEditDialog_closed: EmailService | undefined;

    projectEdit: ProjectEditDialog_Input;
    projectSaved: Project | undefined;
    projectEditDialog_closed: Project | undefined;

    containerRegistryEdit: ContainerRegistryEditDialog_Input;
    containerRegistryImport: ContainerRegistryImportDialog_Input;
    containerImageDeployments: ContainerImageDeploymentsDialog_Input;
    containerImageTags: ContainerImageTagsDialog_Input;
    containerImageScans: ContainerImageScansDialog_Input;
    containerRegistrySaved: ContainerRegistry | undefined;
    containerRegistryEditDialog_closed: ContainerRegistry | undefined;
    githubIntegrationEdit: GithubIntegrationEditDialog_Input;
    integrationDelete: IntegrationDeleteDialog_Input;
    deploymentBulkUpdateVersion: DeploymentBulkUpdateVersionDialog_Input;
    githubIntegrationSaved: GithubIntegration | undefined;
    githubIntegrationEditDialog_closed: GithubIntegration | undefined;

    userEdit: UserEditDialog_Input;
    userSaved: User | undefined;
    userEditDialog_closed: User | undefined;

    auditEventList: AuditEventListDialog_Input;

    migrationJobList: MigrationJobListDialog_Input;
    migrationJobLogs: MigrationJobLogsDialog_Input;

    workspaceSaved: Workspace | undefined;
    workspaceCreate: WorkspaceCreateDialog_Input;
    workspaceLogs: WorkspaceLogsDialog_Input;
    workspaceUpdateLabel: WorkspaceUpdateLabelDialog_Input;

    podTerminal: PodTerminalDialog_Input;

    containerImageSaved: ContainerImage | undefined;
    containerImageEdit: ContainerImageEditDialog_Input;
    containerImageEditDialog_closed: ContainerImage | undefined;

    deploymentSpecificationSaved: DeploymentSpecification | undefined;
    deploymentSpecificationCreate: DeploymentSpecificationCreateDialog_Input;
    deploymentSpecificationUpdateEnvironmentVariable: DeploymentSpecificationUpdateEnvironmentVariableDialog_Input;
    deploymentSpecificationUpdatePostCommand: DeploymentSpecificationUpdatePostCommandDialog_Input;
    deploymentSpecificationUpdateQuickCommand: DeploymentSpecificationUpdateQuickCommandDialog_Input;
    deploymentSpecificationUpdateServicePort: DeploymentSpecificationUpdateServicePortDialog_Input;
    deploymentSpecificationUpdateIngress: DeploymentSpecificationUpdateIngressDialog_Input;
    deploymentSpecificationUpdateIngressRulePaths: DeploymentSpecificationUpdateIngressRulePathsDialog_Input;
    deploymentSpecificationUpdateIngressRulePath: DeploymentSpecificationUpdateIngressRulePathDialog_Input;
    deploymentSpecificationUpdateClusterRoleRule: DeploymentSpecificationUpdateClusterRoleRuleDialog_Input;
    deploymentSpecificationUpdateRoleRule: DeploymentSpecificationUpdateRoleRuleDialog_Input;
    deploymentSpecificationUpdateServiceAnnotation: DeploymentSpecificationUpdateServiceAnnotationDialog_Input;
    deploymentSpecificationUpdateDeploymentAnnotation: DeploymentSpecificationUpdateDeploymentAnnotationDialog_Input;
    deploymentSpecificationUpdateIngressAnnotations: DeploymentSpecificationUpdateIngressAnnotationsDialog_Input;
    deploymentSpecificationUpdateIngressAnnotation: DeploymentSpecificationUpdateIngressAnnotationDialog_Input;
    deploymentSpecificationUpdateLabel: DeploymentSpecificationUpdateLabelDialog_Input;
    deploymentSpecificationUpdateHttpProxyRoute: DeploymentSpecificationUpdateHttpProxyRoutePathDialog_Input;
    deploymentSpecificationUpdateVolume: DeploymentSpecificationUpdateVolumeDialog_Input;

    workspaceTemplateSaved: WorkspaceTemplate | undefined;
    workspaceTemplateEdit: WorkspaceTemplateCreateDialog_Input;
    workspaceTemplateEditDialog_closed: WorkspaceTemplate | undefined;
    workspaceTemplateUpdateDeploymentSpecification: WorkspaceTemplateUpdateDeploymentSpecificationDialog_Input;
    workspaceTemplateUpdateDeploymentSpecifications: WorkspaceTemplateUpdateDeploymentSpecificationsDialog_Input;
    workspaceTemplateUpdateDeploymentSpecificationKNativeMinScaleSchedules: WorkspaceTemplateUpdateDeploymentSpecificationKNativeMinScaleSchedulesDialog_Input;
    workspaceTemplateUpdateEnvironmentVariables: WorkspaceTemplateUpdateEnvironmentVariablesDialog_Input;
    workspaceTemplateUpdateEnvironmentVariable: WorkspaceTemplateUpdateEnvironmentVariableDialog_Input;
    workspaceTemplateUpdateLabels: WorkspaceTemplateUpdateLabelsDialog_Input;
    workspaceTemplateUpdateLabel: WorkspaceTemplateUpdateLabelDialog_Input;

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

    podioIntegrationSaved: PodioIntegration | undefined;
    podioIntegrationEdit: PodioIntegrationEditDialog_Input;
    podioIntegrationEditDialog_closed: PodioIntegration | undefined;

    postUpdateActionEdit: PostUpdateActionEditDialog_Input;
    postUpdateActionSaved: PostUpdateAction | undefined;
    postUpdateActionEditDialog_closed: PostUpdateAction | undefined;
    postUpdateActionUpdateConditions: PostUpdateActionUpdateConditionsDialog_Input;
    postUpdateActionUpdateCondition: PostUpdateActionUpdateConditionDialog_Input;

    cronJobEdit: CronJobEditDialog_Input;
    cronJobSaved: K8sCronJob | undefined;
    cronJobEditDialog_closed: K8sCronJob | undefined;

    knativeMinScaleScheduleEdit: KNativeMinScaleScheduleEditDialog_Input;
    knativeMinScaleScheduleSaved: KNativeMinScaleSchedule | undefined;
    knativeMinScaleScheduleEditDialog_closed:
        | KNativeMinScaleSchedule
        | undefined;
};

/**
 * Whether kso's resources are in the cluster - a sync status, as Argo CD has it. Whether the
 * workload is doing well is `HealthStatusTypes`, beside it.
 */
export const DeploymentStatusTypes = {
    Draft: "draft",
    OutOfSync: "out_of_sync",
    Synced: "synced",
    Inactive: "inactive",
};

/**
 * Runtime health, beside the status - Argo CD's six. See `Libraries/Health` in the backend.
 */
export const HealthStatusTypes = {
    Healthy: "healthy",
    Progressing: "progressing",
    Degraded: "degraded",
    Suspended: "suspended",
    Missing: "missing",
    Unknown: "unknown",
};

/**
 * How sure a diagnosis is - see `Libraries/Health/Diagnosis` in the backend.
 */
export const DiagnosisVerdicts = {
    Certain: "certain",
    Possible: "possible",
    CannotTell: "cannot_tell",
};

export const WorkspaceStatusTypes = {
    Draft: "draft",
    OutOfSync: "out_of_sync",
    Synced: "synced",
    Inactive: "inactive",
    Paused: "paused",
};

export const MigrationJobStatusTypes = {
    Deploying: "deploying",
    Started: "started",
    Completed: "completed",
    FailedLogVerification: "failed-log-verification",
    Failed_PostCommands: "failed-post-commands",
};

export const ContainerImageTagPolicies = {
    MatchDeployment: "match-deployment",
    Static: "static",
    Default: "default",
};

export const ImagePullPolicies = {
    IfNotPresent: "IfNotPresent",
    Always: "Always",
    Never: "Never",
};

export const MigrationVerificationTypes = {
    EndsWith: "ends-with",
    Regex: "regex",
};

export const RbacPermissions = {
    Developer: "developer",
    Workspaces: {
        Get: "workspaces.get",
        List: "workspaces.list",
        Create: "workspaces.create",
        Update: "workspaces.update",
        Delete: "workspaces.delete",
    },
    Users: {
        Get: "users.get",
        List: "users.list",
        Create: "users.create",
        Update: "users.update",
        Delete: "users.delete",
    },
};

export const ContainerRegistries = {
    ArtifactContainerRegistry: "artifact-container-registry",
    AzureContainerRegistry: "azure-container-registry",
    Harbor: "harbor",
};

export const CommitIdentificationMethods = {
    EnvironmentVariable: "environment-variable",
};

export const VersionControlProviders = {
    GitHub: "github",
};

export const PostUpdateActionTypes = {
    Podio_AddComment: "podio-add-comment",
    Podio_FieldUpdate: "podio-field-update",
};

export const PostUpdateActionConditionTypes = {
    PodioFieldEquals: "podio-field-equals",
};

export const CronJobConcurrencyPolicies = {
    Allow: "Allow",
    Forbid: "Forbid",
    Replace: "Replace",
};

export const CronJobRestartPolicies = {
    Always: "Always",
    OnFailure: "OnFailure",
    Never: "Never",
};

export const WorkloadTypes = {
    Deployment: "deployment",
    KNativeService: "knative-service",
    DaemonSet: "daemon-set",
    CustomResource: "custom-resource",
};

export const NetworkTypes = {
    NginxIngress: "nginx-ingress",
    Istio: "istio",
    Contour: "contour",
    GatewayApi: "gateway-api",
};

export const HostingProviders = {
    Gke: "gke",
    Eks: "eks",
    Aks: "aks",
    DigitalOcean: "digitalocean",
    Openshift: "openshift",
    SelfHosted: "self-hosted",
};

export const HostingProviderItems = [
    {title: "Google Kubernetes Engine (GKE)", value: HostingProviders.Gke},
    {title: "Amazon Elastic Kubernetes Service (EKS)", value: HostingProviders.Eks},
    {title: "Azure Kubernetes Service (AKS)", value: HostingProviders.Aks},
    {title: "DigitalOcean Kubernetes", value: HostingProviders.DigitalOcean},
    {title: "OpenShift", value: HostingProviders.Openshift},
    {title: "Self hosted", value: HostingProviders.SelfHosted},
];

export const HealthCheckTypes = {
    Http: "http",
    Tcp: "tcp",
};

export const DeploymentStepLevels = {
    Workspace: "workspace",
    Deployment: "deployment",
};

export const DeploymentAnnotationLevels = {
    Deployment: "deployment",
    Pod: "pod",
};
