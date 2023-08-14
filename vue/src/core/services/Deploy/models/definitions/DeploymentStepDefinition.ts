/**
 * Created by ModelParser
 */
import {User} from '../User';
import {BaseModel} from '../BaseModel';

export class DeploymentStepDefinition extends BaseModel {
    identifier?: string;
    name?: string;
    hasPreviewCommand?: boolean;
    hasStatusCommand?: boolean;
    hasDeployCommand?: boolean;
    hasTerminateCommand?: boolean;
    hasKubernetesEvents?: boolean;
    hasKubernetesStatus?: boolean;
    id?: number;
    created?: string;
    updated?: string;
    created_by_id?: number;
    created_by?: User;
    updated_by_id?: number;
    updated_by?: User;

    constructor(data?: any) {
        super();
        this.populate(data);
    }

    public populate(data?: any, patch = false) {
        if (!patch) {
            delete this.identifier;
            delete this.name;
            delete this.hasPreviewCommand;
            delete this.hasStatusCommand;
            delete this.hasDeployCommand;
            delete this.hasTerminateCommand;
            delete this.hasKubernetesEvents;
            delete this.hasKubernetesStatus;
            delete this.id;
            delete this.created;
            delete this.updated;
            delete this.created_by_id;
            delete this.created_by;
            delete this.updated_by_id;
            delete this.updated_by;
        }

        if (!data) return;
        if (data.identifier != null) {
            this.identifier = data.identifier;
        }
        if (data.name != null) {
            this.name = data.name;
        }
        if (data.hasPreviewCommand != null) {
            this.hasPreviewCommand = data.hasPreviewCommand;
        }
        if (data.hasStatusCommand != null) {
            this.hasStatusCommand = data.hasStatusCommand;
        }
        if (data.hasDeployCommand != null) {
            this.hasDeployCommand = data.hasDeployCommand;
        }
        if (data.hasTerminateCommand != null) {
            this.hasTerminateCommand = data.hasTerminateCommand;
        }
        if (data.hasKubernetesEvents != null) {
            this.hasKubernetesEvents = data.hasKubernetesEvents;
        }
        if (data.hasKubernetesStatus != null) {
            this.hasKubernetesStatus = data.hasKubernetesStatus;
        }
        if (data.id != null) {
            this.id = data.id;
        }
        if (data.created != null) {
            this.created = data.created;
        }
        if (data.updated != null) {
            this.updated = data.updated;
        }
        if (data.created_by_id != null) {
            this.created_by_id = data.created_by_id;
        }
        if (data.created_by != null) {
            this.created_by = new User(data.created_by);
        }
        if (data.updated_by_id != null) {
            this.updated_by_id = data.updated_by_id;
        }
        if (data.updated_by != null) {
            this.updated_by = new User(data.updated_by);
        }
    }

}
