/**
 * Created by ModelParser
 * Date: 09-01-2023.
 * Time: 12:33.
 */
import {WorkspaceDefinition} from './definitions/WorkspaceDefinition';
import {DeploymentStatusTypes} from "@/constants";
import {DeploymentPackage} from "@/core/services/Deploy/models/DeploymentPackage";

export class Workspace extends WorkspaceDefinition {

    constructor(json?: any) {
        super(json);
    }

    public static CreateDefault(deploymentPackage: DeploymentPackage): Workspace {
        const item = new Workspace();
        item.domain_id = deploymentPackage.default_domain_id;
        return item;
    }

    public get name(): string | undefined {
        return this.name_readable;
    }

    public get status(): string {
        const deploymentsInDraftStatus = this.deployments?.filter(deployment => deployment.status == DeploymentStatusTypes.Draft);
        const deploymentsInDeploymentStatus = this.deployments?.filter(deployment => deployment.status == DeploymentStatusTypes.Deploying);
        const deploymentsInActiveStatus = this.deployments?.filter(deployment => deployment.status == DeploymentStatusTypes.Active);
        const deploymentsInErrorStatus = this.deployments?.filter(deployment => deployment.status == DeploymentStatusTypes.Error);
        if (deploymentsInErrorStatus?.length) {
            return DeploymentStatusTypes.Error;
        } else if (deploymentsInDeploymentStatus?.length) {
            return DeploymentStatusTypes.Deploying;
        } else if (deploymentsInDraftStatus?.length) {
            return DeploymentStatusTypes.Draft;
        } else if (deploymentsInActiveStatus?.length) {
            return DeploymentStatusTypes.Active;
        } else {
            return DeploymentStatusTypes.Draft;
        }
    }

}
