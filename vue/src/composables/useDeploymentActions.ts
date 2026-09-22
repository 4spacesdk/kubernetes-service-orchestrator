import bus from "@/plugins/bus";
import { Api } from "@/core/services/Deploy/Api";
import { Deployment } from "@/core/services/Deploy/models";
import { EventEmitter } from "@/helpers/EventEmitter";

/**
 * Deploy and terminate one deployment, every step of it - what Deploy and Terminate on a
 * workspace do for each of its deployments. Each asks first and says `deploymentSaved` when
 * done.
 */
export function useDeploymentActions() {

    function run(item: Deployment, verb: string, api: { setErrorHandler: any; save: any }) {
        const workerProps = {
            title: `${verb} ${item.name}`,
            body: "This may take a minute",
            onFinishBody: "All done",
            onIsWorkingChangeEventEmitter: new EventEmitter<boolean>(),
        };
        bus.emit("worker", workerProps);

        workerProps.onIsWorkingChangeEventEmitter.emit(true);
        // What failed is shown where "All done" would be; the rest goes on as a success would.
        api.setErrorHandler((response: any) => {
            if (response.error) {
                workerProps.onFinishBody = response.error;
            }
            return true;
        });
        api.save(null, () => {
            workerProps.onIsWorkingChangeEventEmitter.emit(false);
            bus.emit("deploymentSaved");
        });
    }

    function deploy(item: Deployment) {
        bus.emit("confirm", {
            body: `Do you want to deploy "${item.name}"?`,
            confirmIcon: "fa fa-play",
            confirmColor: "green",

            responseCallback: (confirmed: boolean) => {
                if (confirmed) {
                    run(item, "Deploying", Api.deployments().deployPutById(item.id!));
                }
            },
        });
    }

    function terminate(item: Deployment) {
        bus.emit("confirm", {
            body: `Do you want to terminate "${item.name}"?\n\nEverything it runs in the cluster is taken down. It stays Inactive until it is deployed again.`,
            confirmIcon: "fa fa-skull",
            confirmColor: "red",

            responseCallback: (confirmed: boolean) => {
                if (confirmed) {
                    run(item, "Terminating", Api.deployments().terminatePutById(item.id!));
                }
            },
        });
    }

    return { deploy, terminate };
}
