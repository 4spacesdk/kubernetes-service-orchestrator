/**
 * Created by ModelParser
 * Date: 09-01-2023.
 * Time: 12:33.
 */
import {WorkspaceDefinition} from './definitions/WorkspaceDefinition';
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

}
