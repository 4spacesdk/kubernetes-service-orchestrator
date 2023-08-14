/**
 * Created by ModelParser
 */
import {DeploymentSpecification} from '../DeploymentSpecification';
import {User} from '../User';
import {BaseModel} from '../BaseModel';

export class DeploymentSpecificationClusterRoleRuleDefinition extends BaseModel {
    deploymennt_specification_id?: number;
    deployment_specification?: DeploymentSpecification;
    api_group?: string;
    resource?: string;
    verbs?: string;
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
            delete this.deploymennt_specification_id;
            delete this.deployment_specification;
            delete this.api_group;
            delete this.resource;
            delete this.verbs;
            delete this.id;
            delete this.created;
            delete this.updated;
            delete this.created_by_id;
            delete this.created_by;
            delete this.updated_by_id;
            delete this.updated_by;
        }

        if (!data) return;
        if (data.deploymennt_specification_id != null) {
            this.deploymennt_specification_id = data.deploymennt_specification_id;
        }
        if (data.deployment_specification != null) {
            this.deployment_specification = new DeploymentSpecification(data.deployment_specification);
        }
        if (data.api_group != null) {
            this.api_group = data.api_group;
        }
        if (data.resource != null) {
            this.resource = data.resource;
        }
        if (data.verbs != null) {
            this.verbs = data.verbs;
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
