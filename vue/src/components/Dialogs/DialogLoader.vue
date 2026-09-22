<script setup lang="ts">
import {getCurrentInstance, onMounted, onUnmounted, ref} from 'vue'
import type {Component} from 'vue'
import bus from "@/plugins/bus";
import renderComponent from "@/plugins/renderComponent";
import {useRouter} from "vue-router";

interface StackEntry {
    reference: string;
    unmount?: () => void;
    /** Something was typed or changed in the dialog. Esc asks before throwing it away. */
    dirty?: boolean;
    /** Stays when the page changes under it - a toast saying what just happened does. */
    keepOnNavigation?: boolean;
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
    addComponent((await import('@/components/Dialogs/Common/Toast.vue')).default, input, true);
});

bus.on('worker', async input => {
    addComponent((await import('@/components/Dialogs/Common/WorkerDialog.vue')).default, input);
});


bus.on('domainCreate', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Domains/DomainCreateDialog.vue')).default, input);
});

bus.on('domainEdit', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Domains/DomainEditDialog.vue')).default, input);
});

bus.on('gatewayCreate', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Gateways/GatewayEditDialog.vue')).default, input);
});

bus.on('gatewayEdit', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Gateways/GatewayEditDialog.vue')).default, input);
});

bus.on('gatewayResourcePreview', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Gateways/GatewayResourcePreviewDialog.vue')).default, input);
});

bus.on('databaseServiceEdit', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DatabaseServices/DatabaseServiceEditDialog.vue')).default, input);
});

bus.on('containerImageScans', async input => {
    addComponent((await import('@/components/Dialogs/Setup/ContainerImages/ContainerImageScansDialog.vue')).default, input);
});

bus.on('containerImageDeployments', async input => {
    addComponent((await import('@/components/Dialogs/Setup/ContainerImages/ContainerImageDeploymentsDialog.vue')).default, input);
});

bus.on('containerImageTags', async input => {
    addComponent((await import('@/components/Dialogs/Setup/ContainerImages/ContainerImageTagsDialog.vue')).default, input);
});

bus.on('containerRegistryImport', async input => {
    addComponent((await import('@/components/Dialogs/Integrations/ContainerRegistries/ContainerRegistryImportDialog.vue')).default, input);
});

bus.on('containerRegistryEdit', async input => {
    addComponent((await import('@/components/Dialogs/Integrations/ContainerRegistries/ContainerRegistryEditDialog.vue')).default, input);
});

bus.on('integrationDelete', async input => {
    addComponent((await import('@/components/Dialogs/Integrations/IntegrationDeleteDialog.vue')).default, input);
});

bus.on('githubIntegrationEdit', async input => {
    addComponent((await import('@/components/Dialogs/Integrations/GithubIntegrations/GithubIntegrationEditDialog.vue')).default, input);
});

bus.on('emailServiceEdit', async input => {
    addComponent((await import('@/components/Dialogs/Setup/EmailServices/EmailServiceEditDialog.vue')).default, input);
});



bus.on('deploymentCreate', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Deployments/DeploymentCreateDialog.vue')).default, input);
});


bus.on('deploymentUpdateWorkspace', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateWorkspaceDialog.vue')).default, input);
});
bus.on('deploymentUpdateEnvironmentVariable', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateEnvironmentVariableDialog.vue')).default, input);
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
bus.on('deploymentUpdateLabel', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentUpdateLabelDialog.vue')).default, input);
});


bus.on('auditEventList', async input => {
    addComponent((await import('@/components/Dialogs/AuditEvents/AuditEventsListDialog.vue')).default, input);
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


bus.on('deploymentBulkUpdateVersion', async input => {
    addComponent((await import('@/components/Dialogs/Setup/Deployments/UpdateDialogs/DeploymentBulkUpdateVersionDialog.vue')).default, input);
});

bus.on('containerImageEdit', async input => {
    addComponent((await import('@/components/Dialogs/Setup/ContainerImages/ContainerImageEditDialog.vue')).default, input);
});


bus.on('deploymentSpecificationCreate', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/DeploymentSpecificationCreateDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdateEnvironmentVariable', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateEnvironmentVariableDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdatePostCommand', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdatePostCommandDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdateQuickCommand', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateQuickCommandDialog.vue')).default, input);
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
bus.on('deploymentSpecificationUpdateIngress', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateIngressDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdateClusterRoleRule', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateClusterRoleRuleDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdateRoleRule', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateRoleRuleDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdateServiceAnnotation', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateServiceAnnotationDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdateDeploymentAnnotation', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateDeploymentAnnotationDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdateIngressAnnotations', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateIngressAnnotationsDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdateIngressAnnotation', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateIngressAnnotationDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdateLabel', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateLabelDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdateHttpProxyRoute', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateHttpProxyRouteDialog.vue')).default, input);
});
bus.on('deploymentSpecificationUpdateVolume', async input => {
    addComponent((await import('@/components/Dialogs/Setup/DeploymentSpecifications/UpdateDialogs/DeploymentSpecificationUpdateVolumeDialog.vue')).default, input);
});


bus.on('workspaceTemplateEdit', async input => {
    addComponent((await import('@/components/Dialogs/Setup/WorkspaceTemplates/WorkspaceTemplateEditDialog.vue')).default, input);
});
bus.on('workspaceTemplateUpdateDeploymentSpecification', async input => {
    addComponent((await import('@/components/Dialogs/Setup/WorkspaceTemplates/UpdateDialogs/WorkspaceTemplateUpdateDeploymentSpecificationDialog.vue')).default, input);
});
bus.on('workspaceTemplateUpdateDeploymentSpecifications', async input => {
    addComponent((await import('@/components/Dialogs/Setup/WorkspaceTemplates/UpdateDialogs/WorkspaceTemplateUpdateDeploymentSpecificationsDialog.vue')).default, input);
});
bus.on('workspaceTemplateUpdateDeploymentSpecificationKNativeMinScaleSchedules', async input => {
    addComponent((await import('@/components/Dialogs/Setup/WorkspaceTemplates/UpdateDialogs/WorkspaceTemplateUpdateDeploymentSpecificationKNativeMinScaleSchedulesDialog.vue')).default, input);
});
bus.on('workspaceTemplateUpdateEnvironmentVariables', async input => {
    addComponent((await import('@/components/Dialogs/Setup/WorkspaceTemplates/UpdateDialogs/WorkspaceTemplateUpdateEnvironmentVariablesDialog.vue')).default, input);
});
bus.on('workspaceTemplateUpdateEnvironmentVariable', async input => {
    addComponent((await import('@/components/Dialogs/Setup/WorkspaceTemplates/UpdateDialogs/WorkspaceTemplateUpdateEnvironmentVariableDialog.vue')).default, input);
});
bus.on('workspaceTemplateUpdateLabels', async input => {
    addComponent((await import('@/components/Dialogs/Setup/WorkspaceTemplates/UpdateDialogs/WorkspaceTemplateUpdateLabelsDialog.vue')).default, input);
});
bus.on('workspaceTemplateUpdateLabel', async input => {
    addComponent((await import('@/components/Dialogs/Setup/WorkspaceTemplates/UpdateDialogs/WorkspaceTemplateUpdateLabelDialog.vue')).default, input);
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

bus.on('initContainerEdit', async input => {
    addComponent((await import('@/components/Dialogs/Setup/InitContainers/InitContainerEditDialog.vue')).default, input);
});
bus.on('initContainerUpdateEnvironmentVariables', async input => {
    addComponent((await import('@/components/Dialogs/Setup/InitContainers/UpdateDialogs/InitContainerUpdateEnvironmentVariablesDialog.vue')).default, input);
});
bus.on('initContainerUpdateEnvironmentVariable', async input => {
    addComponent((await import('@/components/Dialogs/Setup/InitContainers/UpdateDialogs/InitContainerUpdateEnvironmentVariableDialog.vue')).default, input);
});

bus.on('podioIntegrationEdit', async input => {
    addComponent((await import('@/components/Dialogs/Integrations/PodioIntegrations/PodioIntegrationEditDialog.vue')).default, input);
});

bus.on('postUpdateActionEdit', async input => {
    addComponent((await import('@/components/Dialogs/Setup/PostUpdateActions/PostUpdateActionEditDialog.vue')).default, input);
});
bus.on('postUpdateActionUpdateConditions', async input => {
    addComponent((await import('@/components/Dialogs/Setup/PostUpdateActions/UpdateDialogs/PostUpdateActionUpdateConditionsDialog.vue')).default, input);
});
bus.on('postUpdateActionUpdateCondition', async input => {
    addComponent((await import('@/components/Dialogs/Setup/PostUpdateActions/UpdateDialogs/PostUpdateActionUpdateConditionDialog.vue')).default, input);
});

bus.on('cronJobEdit', async input => {
    addComponent((await import('@/components/Dialogs/Setup/CronJobs/CronJobEditDialog.vue')).default, input);
});

bus.on('knativeMinScaleScheduleEdit', async input => {
    addComponent((await import('@/components/Dialogs/Setup/KNativeMinScaleSchedules/KNativeMinScaleScheduleEditDialog.vue')).default, input);
});




function addComponent(component: Component, input: any, keepOnNavigation = false) {
    const stackEntry: StackEntry = {
        reference: `component-${dynamicReferenceCounter.value++}`,
        keepOnNavigation,
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

/**
 * A dialog belongs to the page it was opened on. A link in one - a name in a list inside a
 * dialog, going to that thing's page - leaves the page, and the dialogs with it.
 */
useRouter().afterEach((to, from) => {
    if (to.path != from.path) {
        [...stack.value]
            .filter(entry => !entry.keepOnNavigation)
            .forEach(entry => dismissDynamicComponent(entry));
    }
});

onMounted(() => {
    document.addEventListener("keydown", onKeyDownEventListener);
    document.addEventListener("input", onChangeEventListener, true);
    document.addEventListener("change", onChangeEventListener, true);
});

onUnmounted(() => {
    document.removeEventListener("keydown", onKeyDownEventListener);
    document.removeEventListener("input", onChangeEventListener, true);
    document.removeEventListener("change", onChangeEventListener, true);
});

// <editor-fold desc="Keyboard shortcuts">

/**
 * The dialog on top. Vuetify appends each overlay to the same container as it opens, so
 * the last active one is the one in front.
 */
function topDialog(): HTMLElement | null {
    const dialogs = document.querySelectorAll<HTMLElement>(".v-overlay--active.v-dialog");
    return dialogs.length ? dialogs[dialogs.length - 1] : null;
}

/**
 * A button in the dialog's action bar, by its label. Pressing it rather than removing the
 * dialog lets the dialog close the way it does when clicked: its own events, its route.
 */
function actionButton(dialog: HTMLElement, labels: string[]): HTMLElement | null {
    const buttons = dialog.querySelectorAll<HTMLButtonElement>(".v-card-actions .v-btn");
    for (const button of Array.from(buttons).reverse()) {
        if (!button.disabled && labels.includes(button.textContent?.trim() ?? "")) {
            return button;
        }
    }
    return null;
}

function isTyping(target: EventTarget | null): boolean {
    const element = target as HTMLElement | null;
    return !!element && (element.isContentEditable || ["INPUT", "TEXTAREA", "SELECT"].includes(element.tagName));
}

function onChangeEventListener(event: Event) {
    const top = stack.value[stack.value.length - 1];
    if (top && topDialog()?.contains(event.target as Node)) {
        top.dirty = true;
    }
}

function onKeyDownEventListener(keyboardEvent: KeyboardEvent) {
    if (stack.value.length > 0) {
        onDialogKeyDown(keyboardEvent);
    } else {
        onPageKeyDown(keyboardEvent);
    }
}

function onDialogKeyDown(event: KeyboardEvent) {
    // An open dropdown or menu takes Esc and Enter for itself.
    if (document.querySelector(".v-overlay--active.v-menu")) {
        return;
    }
    const dialog = topDialog();

    if (event.key == "Escape") {
        const entry = stack.value[stack.value.length - 1];
        const close = () => {
            const button = dialog ? actionButton(dialog, ["Close", "Cancel", "No"]) : null;
            button ? button.click() : popStack();
        };
        if (entry.dirty) {
            entry.dirty = false;
            bus.emit("confirm", {
                body: "Close without saving your changes?",
                responseCallback: (confirmed: boolean) => (confirmed ? close() : (entry.dirty = true)),
            });
        } else {
            close();
        }
        return;
    }

    // Enter saves from a one-line field; Ctrl/Cmd+Enter from anywhere, textareas included.
    // Not from a combobox or select, where Enter picks an item.
    if (event.key == "Enter" && dialog) {
        const target = event.target as HTMLElement;
        const oneLineField = target.tagName == "INPUT"
            && !["checkbox", "radio"].includes((target as HTMLInputElement).type)
            && !target.closest(".v-combobox, .v-autocomplete, .v-select");
        if (event.ctrlKey || event.metaKey || oneLineField) {
            const save = actionButton(dialog, ["Save"]);
            if (save) {
                event.preventDefault();
                save.click();
            }
        }
    }
}

/**
 * `/` searches and `n` creates, on a list page - marked in the list with `data-shortcut`.
 */
function onPageKeyDown(event: KeyboardEvent) {
    if (isTyping(event.target) || event.ctrlKey || event.metaKey || event.altKey) {
        return;
    }
    if (event.key == "/") {
        const search = document.querySelector<HTMLInputElement>('[data-shortcut="search"] input');
        if (search) {
            event.preventDefault();
            search.focus();
        }
    } else if (event.key == "n") {
        const create = document.querySelector<HTMLElement>('[data-shortcut="create"]');
        if (create) {
            event.preventDefault();
            create.click();
        }
    }
}

// </editor-fold>

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
