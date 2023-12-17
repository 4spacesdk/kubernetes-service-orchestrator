<script setup lang="ts">
import {getCurrentInstance, onMounted, onUnmounted, ref} from 'vue'
import type {Component} from 'vue'
import bus from "@/plugins/bus";
import renderComponent from "@/plugins/renderComponent";

interface StackEntry {
    reference: string;
    unmount?: () => void;
}

const appContext = getCurrentInstance()?.appContext;
const container = ref();
const dynamicReferenceCounter = ref(0);
const stack = ref<StackEntry[]>([]);

bus.on('confirm', async input => {
    addComponent((await import('@/components/Dialogs/Common/ConfirmationDialog.vue')).default, input);
});

bus.on('info', async input => {
    addComponent((await import('@/components/Dialogs/Common/InfoDialog.vue')).default, input);
});

bus.on('json', async input => {
    addComponent((await import('@/components/Dialogs/Common/JsonDialog.vue')).default, input);
});

bus.on('toast', async input => {
    addComponent((await import('@/components/Dialogs/Common/Toast.vue')).default, input);
});

bus.on('worker', async input => {
    addComponent((await import('@/components/Dialogs/Common/WorkerDialog.vue')).default, input);
});


bus.on('domainCreate', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Domains/DomainCreateDialog.vue')).default, input);
});

bus.on('databaseServiceEdit', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DatabaseServices/DatabaseServiceEditDialog.vue')).default, input);
});

bus.on('emailServiceEdit', async input => {
    addComponent((await import('@/components/Dialogs/Setup/EmailServices/EmailServiceEditDialog.vue')).default, input);
});



bus.on('deploymentCreate', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Deployments/DeploymentCreateDialog.vue')).default, input);
});

bus.on('deploymentUpdateVersion', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateVersionDialog.vue')).default, input);
});

bus.on('deploymentUpdateEnvironment', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateEnvironmentDialog.vue')).default, input);
});
bus.on('deploymentUpdateDatabaseService', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateDatabaseServiceDialog.vue')).default, input);
});
bus.on('deploymentUpdateIngress', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateIngressDialog.vue')).default, input);
});
bus.on('deploymentUpdateResourceManagement', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateResourceManagementDialog.vue')).default, input);
});
bus.on('deploymentUpdateUpdateManagement', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateUpdateManagementDialog.vue')).default, input);
});
bus.on('deploymentUpdateEnvironmentVariables', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateEnvironmentVariablesDialog.vue')).default, input);
});
bus.on('deploymentUpdateEnvironmentVariable', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateEnvironmentVariableDialog.vue')).default, input);
});
bus.on('deploymentUpdateVolumes', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateVolumesDialog.vue')).default, input);
});
bus.on('deploymentUpdateVolume', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateVolumeDialog.vue')).default, input);
});
bus.on('deploymentResourceList', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Deployments/DeploymentResourceListDialog.vue')).default, input);
});
bus.on('deploymentResourcePreview', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Deployments/DeploymentResourcePreviewDialog.vue')).default, input);
});
bus.on('deploymentLogs', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Deployments/DeploymentLogsDialog.vue')).default, input);
});


bus.on('migrationJobList', async input => {
    addComponent((await import('@/components/Dialogs/MigrationJobs/MigrationJobsListDialog.vue')).default, input);
});

bus.on('migrationJobLogs', async input => {
    addComponent((await import('@/components/Dialogs/MigrationJobs/MigrationJobLogsDialog.vue')).default, input);
});


bus.on('userEdit', async input => {
    addComponent((await import('@/components/Dialogs/Users/UserEditDialog.vue')).default, input);
});


bus.on('workspaceCreate', async input => {
    addComponent((await import('@/components/Dialogs/Workspaces/WorkspaceCreateDialog.vue')).default, input);
});
bus.on('workspaceUpdateName', async input => {
    addComponent((await import('@/components/Dialogs/Workspaces/UpdateDialogs/WorkspaceUpdateNameDialog.vue')).default, input);
});
bus.on('workspaceUpdateEmailService', async input => {
    addComponent((await import('@/components/Dialogs/Workspaces/UpdateDialogs/WorkspaceUpdateEmailServiceDialog.vue')).default, input);
});
bus.on('workspaceUpdateDatabaseService', async input => {
    addComponent((await import('@/components/Dialogs/Workspaces/UpdateDialogs/WorkspaceUpdateDatabaseServiceDialog.vue')).default, input);
});
bus.on('workspaceUpdateIngress', async input => {
    addComponent((await import('@/components/Dialogs/Workspaces/UpdateDialogs/WorkspaceUpdateIngressDialog.vue')).default, input);
});
bus.on('workspaceDeploymentList', async input => {
    addComponent((await import('@/components/Dialogs/Workspaces/WorkspaceDeploymentList.vue')).default, input);
});
bus.on('workspaceLogs', async input => {
    addComponent((await import('@/components/Dialogs/Workspaces/WorkspaceLogsDialog.vue')).default, input);
});
bus.on('workspaceUpdateLabels', async input => {
    addComponent((await import('@/components/Dialogs/Workspaces/UpdateDialogs/WorkspaceUpdateLabelsDialog.vue')).default, input);
});
bus.on('workspaceUpdateLabel', async input => {
    addComponent((await import('@/components/Dialogs/Workspaces/UpdateDialogs/WorkspaceUpdateLabelDialog.vue')).default, input);
});


bus.on('podTerminal', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Deployments/Pods/PodTerminalDialog.vue')).default, input);
});


bus.on('containerImageEdit', async input => {
    addComponent((await import('@/components/Dialogs/Setup/ContainerImages/ContainerImageEditDialog.vue')).default, input);
});


bus.on('deploymentSpecificationEdit', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/DeploymentSpecificationEditDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdateEnvironmentVariables', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateEnvironmentVariablesDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdateEnvironmentVariable', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateEnvironmentVariableDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdatePostCommands', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdatePostCommandsDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdatePostCommand', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdatePostCommandDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdateServicePorts', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateServicePortsDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdateServicePort', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateServicePortDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdateIngressRulePaths', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateIngressRulePathsDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdateIngressRulePath', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateIngressRulePathDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdateIngresses', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateIngressesDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdateIngress', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateIngressDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdateClusterRoleRules', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateClusterRoleRulesDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdateClusterRoleRule', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateClusterRoleRuleDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdateServiceAnnotations', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateServiceAnnotationsDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdateServiceAnnotation', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateServiceAnnotationDialog.vue')).default, input);
});


bus.on('deploymentPackageEdit', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentPackages/DeploymentPackageEditDialog.vue')).default, input);
});
bus.on('deploymentPackageUpdateDeploymentSpecification', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentPackages/UpdateDialogs/DeploymentPackageUpdateDeploymentSpecificationDialog.vue')).default, input);
});
bus.on('deploymentPackageUpdateDeploymentSpecifications', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentPackages/UpdateDialogs/DeploymentPackageUpdateDeploymentSpecificationsDialog.vue')).default, input);
});

bus.on('oauthClientEdit', async input => {
    addComponent((await import('@/components/Dialogs/Integrations/OAuthClients/OAuthClientEditDialog.vue')).default, input);
});

bus.on('webhookEdit', async input => {
    addComponent((await import('@/components/Dialogs/Integrations/Webhooks/WebhookEditDialog.vue')).default, input);
});
bus.on('webhookDeliveryList', async input => {
    addComponent((await import('@/components/Dialogs/Integrations/Webhooks/Deliveries/WebhookDeliveryListDialog.vue')).default, input);
});




function addComponent(component: Component, input: any) {
    const stackEntry: StackEntry = {
        reference: `component-${dynamicReferenceCounter.value++}`,
    };
    pushStack(stackEntry);

    setTimeout(() => {
        if (!container.value) {
            return;
        }
        stackEntry.unmount = renderComponent(
            container.value[container.value.length - 1],
            component,
            {
                input: input,
                events: {
                    onClose: () => dismissDynamicComponent(stackEntry)
                }
            },
            appContext
        );
    });
}

function dismissDynamicComponent(dynamicComponent: StackEntry) {
    if (dynamicComponent.unmount) {
        dynamicComponent.unmount();
    }
    stack.value.splice(
        stack.value.indexOf(dynamicComponent),
        1
    );
}

onMounted(() => {
    document.addEventListener("keydown", onKeyDownEventListener);
});

onUnmounted(() => {
    document.removeEventListener("keydown", onKeyDownEventListener);
});

function onKeyDownEventListener(keyboardEvent: KeyboardEvent) {
    if (keyboardEvent?.key == "Escape") {
        if (stack.value.length > 0) {
            popStack();
        }
    }
}

function pushStack(entry: StackEntry) {
    stack.value.push(entry);
}

function popStack() {
    dismissDynamicComponent(stack.value[stack.value.length - 1]);
}

</script>

<template>

    <div>
        <div
            v-for="ref in stack" :key="ref.reference"
            ref="container"/>
    </div>

</template>

<style scoped lang="scss">

</style>
