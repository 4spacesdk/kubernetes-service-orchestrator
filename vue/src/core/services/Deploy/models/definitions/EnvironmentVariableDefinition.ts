/**
 * Created by ModelParser
 */
import {Deployment} from '../Deployment';
import {User} from '../User';
import {BaseModel} from '../BaseModel';

export class EnvironmentVariableDefinition extends BaseModel {
    deployment_id?: number;
    deployment?: Deployment;
    name?: string;
    value?: string;
    deployments?: Deployment[];
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
            delete this.deployment_id;
            delete this.deployment;
            delete this.name;
            delete this.value;
            delete this.deployments;
            delete this.id;
            delete this.created;
            delete this.updated;
            delete this.created_by_id;
            delete this.created_by;
            delete this.updated_by_id;
            delete this.updated_by;
        }

        if (!data) return;
        if (data.deployment_id != null) {
            this.deployment_id = data.deployment_id;
        }
        if (data.deployment != null) {
            this.deployment = new Deployment(data.deployment);
        }
        if (data.name != null) {
            this.name = data.name;
        }
        if (data.value != null) {
            this.value = data.value;
        }
        if (data.deployments != null) {
            this.deployments = data.deployments.map((i: any) => new Deployment(i));
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
