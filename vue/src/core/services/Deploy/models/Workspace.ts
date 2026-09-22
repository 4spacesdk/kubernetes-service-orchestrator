/**
 * Created by ModelParser
 * Date: 09-01-2023.
 * Time: 12:33.
 */
import {WorkspaceDefinition} from './definitions/WorkspaceDefinition';
import {WorkspaceTemplate} from "@/core/services/Deploy/models/WorkspaceTemplate";

export class Workspace extends WorkspaceDefinition {

    constructor(json?: any) {
        super(json);
    }

    public static CreateDefault(workspaceTemplate: WorkspaceTemplate): Workspace {
        const item = new Workspace();
        item.domain_id = workspaceTemplate.default_domain_id;
        return item;
    }

    public get name(): string | undefined {
        return this.name_readable;
    }

}
