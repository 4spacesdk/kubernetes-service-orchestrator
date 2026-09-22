import { computed } from "vue";
import bus from "@/plugins/bus";
import { Api } from "@/core/services/Deploy/Api";
import { Workspace } from "@/core/services/Deploy/models";
import { EventEmitter } from "@/helpers/EventEmitter";
import AuthService from "@/services/AuthService";
import { RbacPermissions } from "@/constants";

/**
 * What can be done to a workspace, from its row in the list and from its page alike, and who
 * may. Each asks first where it should and says `workspaceSaved` when done.
 */
export function useWorkspaceActions() {
    const user = AuthService.currentAuthUser;
    const rbacDeveloper = computed(() => user?.hasPermission(RbacPermissions.Developer) ?? false);
    const rbacWorkspaceCreate = computed(() => user?.hasPermission(RbacPermissions.Workspaces.Create) ?? false);
    const rbacWorkspaceUpdate = computed(() => user?.hasPermission(RbacPermissions.Workspaces.Update) ?? false);
    const rbacWorkspaceDelete = computed(() => user?.hasPermission(RbacPermissions.Workspaces.Delete) ?? false);

    function deploy(item: Workspace) {
        bus.emit("confirm", {
            body: `Do you want to deploy "${item.name}"?`,
            confirmIcon: "fa fa-play",
            confirmColor: "green",

            responseCallback: (confirmed: boolean) => {
                if (confirmed) {
                    const workerProps = {
                        title: `Deploying ${item.name}`,
                        body: "This may take a minute",
                        onFinishBody: "All done",
                        onIsWorkingChangeEventEmitter: new EventEmitter<boolean>(),
                    };
                    bus.emit("worker", workerProps);

                    workerProps.onIsWorkingChangeEventEmitter.emit(true);
                    const api = Api.workspaces().deployPutById(item.id!);
                    api.setErrorHandler((response) => {
                        if (response.error) {
                            workerProps.onFinishBody = response.error;
                        }
                        return true;
                    });
                    api.save(null, () => {
                        workerProps.onIsWorkingChangeEventEmitter.emit(false);
                        bus.emit("workspaceSaved");
                    });
                }
            },
        });
    }

    function terminate(item: Workspace) {
        bus.emit("confirm", {
            body: `Do you want to terminate "${item.name}"?`,
            confirmIcon: "fa fa-skull",
            confirmColor: "red",

            responseCallback: (confirmed: boolean) => {
                if (confirmed) {
                    const workerProps = {
                        title: `Terminating ${item.name}`,
                        body: "This may take a minute",
                        onFinishBody: "All done",
                        onIsWorkingChangeEventEmitter: new EventEmitter<boolean>(),
                    };
                    bus.emit("worker", workerProps);

                    workerProps.onIsWorkingChangeEventEmitter.emit(true);
                    const api = Api.workspaces().terminatePutById(item.id!);
                    api.setErrorHandler((response) => {
                        if (response.error) {
                            workerProps.onFinishBody = response.error;
                        }
                        return true;
                    });
                    api.save(null, () => {
                        workerProps.onIsWorkingChangeEventEmitter.emit(false);
                        bus.emit("workspaceSaved");
                    });
                }
            },
        });
    }

    /**
     * Pause terminates the workspace and records that a person decided to, so the pause is not
     * recomputed away. The confirmation says what that costs: it is the same shutdown as
     * Terminate, disks and all.
     */
    function pause(item: Workspace) {
        bus.emit("confirm", {
            body:
                `Do you want to pause "${item.name}"?` +
                "\n\nThis shuts the workspace down like Terminate does, and its disks go with it" +
                " unless their reclaim policy keeps them. The pause stays until someone takes it off.",
            confirmIcon: "fa fa-pause",
            confirmColor: "red",

            responseCallback: (confirmed: boolean) => {
                if (confirmed) {
                    const workerProps = {
                        title: `Pausing ${item.name}`,
                        body: "This may take a minute",
                        onFinishBody: "All done",
                        onIsWorkingChangeEventEmitter: new EventEmitter<boolean>(),
                    };
                    bus.emit("worker", workerProps);

                    workerProps.onIsWorkingChangeEventEmitter.emit(true);
                    const api = Api.workspaces().pausePutById(item.id!);
                    api.setErrorHandler((response) => {
                        if (response.error) {
                            workerProps.onFinishBody = response.error;
                        }
                        return true;
                    });
                    api.save(null, () => {
                        workerProps.onIsWorkingChangeEventEmitter.emit(false);
                        bus.emit("workspaceSaved");
                    });
                }
            },
        });
    }

    /**
     * Takes the pause off. The workspace stays shut down - Deploy is the button that brings it
     * back, and that is a decision of its own.
     */
    function resume(item: Workspace) {
        const api = Api.workspaces().resumePutById(item.id!);
        api.setErrorHandler((response) => {
            if (response.error) {
                bus.emit("toast", {text: response.error});
            }
            return false;
        });
        api.save(null, () => bus.emit("workspaceSaved"));
    }

    /**
     * Only a workspace without deployments can go. `onBusy` follows the check for them, for a
     * spinner; `onDeleted` is called once it is gone.
     */
    function remove(item: Workspace, options: { onBusy?: (busy: boolean) => void; onDeleted?: () => void } = {}) {
        bus.emit("confirm", {
            body: `Do you want to delete "${item.name}"?`,
            confirmIcon: "fa fa-trash",
            confirmColor: "red",

            responseCallback: (confirmed: boolean) => {
                if (confirmed) {
                    options.onBusy?.(true);
                    Api.workspaces()
                        .get()
                        .where("id", item.id!)
                        .include("deployment")
                        .find((workspaces) => {
                            options.onBusy?.(false);

                            if (workspaces[0].deployments?.length) {
                                bus.emit("toast", {
                                    text: "All deployments must be terminated and deleted before you can delete the workspace",
                                });
                            } else {
                                Api.workspaces()
                                    .deleteById(item.id!)
                                    .delete(() => {
                                        bus.emit("workspaceSaved");
                                        options.onDeleted?.();
                                    });
                            }
                        });
                }
            },
        });
    }

    function showMigrationJobs(item: Workspace) {
        bus.emit("migrationJobList", {
            workspace: item,
        });
    }

    function showHistory(item: Workspace) {
        bus.emit("auditEventList", {
            resourceType: "Workspace",
            resourceId: item.id!,
            title: item.name_readable,
        });
    }

    function showLogs(item: Workspace) {
        bus.emit("workspaceLogs", {
            workspace: item,
        });
    }

    return {
        rbacDeveloper,
        rbacWorkspaceCreate,
        rbacWorkspaceUpdate,
        rbacWorkspaceDelete,
        deploy,
        terminate,
        pause,
        resume,
        remove,
        showMigrationJobs,
        showHistory,
        showLogs,
    };
}
