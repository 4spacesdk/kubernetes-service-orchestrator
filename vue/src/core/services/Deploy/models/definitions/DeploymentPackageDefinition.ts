/**
 * Created by ModelParser
 */
import {Workspace} from '../Workspace';
import {DeploymentPackageDeploymentSpecification} from '../DeploymentPackageDeploymentSpecification';
import {User} from '../User';
import {BaseModel} from '../BaseModel';

export class DeploymentPackageDefinition extends BaseModel {
    name?: string;
    namespace?: string;
    default_email_service_id?: number;
    default_database_service_id?: number;
    default_domain_id?: number;
    workspaces?: Workspace[];
    deployment_package_deployment_specifications?: DeploymentPackageDeploymentSpecification[];
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
            delete this.name;
            delete this.namespace;
            delete this.default_email_service_id;
            delete this.default_database_service_id;
            delete this.default_domain_id;
            delete this.workspaces;
            delete this.deployment_package_deployment_specifications;
            delete this.id;
            delete this.created;
            delete this.updated;
            delete this.created_by_id;
            delete this.created_by;
            delete this.updated_by_id;
            delete this.updated_by;
        }

        if (!data) return;
        if (data.name != null) {
            this.name = data.name;
        }
        if (data.namespace != null) {
            this.namespace = data.namespace;
        }
        if (data.default_email_service_id != null) {
            this.default_email_service_id = data.default_email_service_id;
        }
        if (data.default_database_service_id != null) {
            this.default_database_service_id = data.default_database_service_id;
        }
        if (data.default_domain_id != null) {
            this.default_domain_id = data.default_domain_id;
        }
        if (data.workspaces != null) {
            this.workspaces = data.workspaces.map((i: any) => new Workspace(i));
        }
        if (data.deployment_package_deployment_specifications != null) {
            this.deployment_package_deployment_specifications = data.deployment_package_deployment_specifications.map((i: any) => new DeploymentPackageDeploymentSpecification(i));
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
