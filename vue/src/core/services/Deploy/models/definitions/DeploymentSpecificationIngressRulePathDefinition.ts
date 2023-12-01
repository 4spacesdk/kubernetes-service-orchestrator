/**
 * Created by ModelParser
 */
import {DeploymentSpecificationIngress} from '../DeploymentSpecificationIngress';
import {User} from '../User';
import {BaseModel} from '../BaseModel';

export class DeploymentSpecificationIngressRulePathDefinition extends BaseModel {
    deployment_specification_ingress_id?: number;
    deployment_specification_ingress?: DeploymentSpecificationIngress;
    path?: string;
    path_type?: string;
    backend_service_port_name?: string;
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
            delete this.deployment_specification_ingress_id;
            delete this.deployment_specification_ingress;
            delete this.path;
            delete this.path_type;
            delete this.backend_service_port_name;
            delete this.id;
            delete this.created;
            delete this.updated;
            delete this.created_by_id;
            delete this.created_by;
            delete this.updated_by_id;
            delete this.updated_by;
        }

        if (!data) return;
        if (data.deployment_specification_ingress_id != null) {
            this.deployment_specification_ingress_id = data.deployment_specification_ingress_id;
        }
        if (data.deployment_specification_ingress != null) {
            this.deployment_specification_ingress = new DeploymentSpecificationIngress(data.deployment_specification_ingress);
        }
        if (data.path != null) {
            this.path = data.path;
        }
        if (data.path_type != null) {
            this.path_type = data.path_type;
        }
        if (data.backend_service_port_name != null) {
            this.backend_service_port_name = data.backend_service_port_name;
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
