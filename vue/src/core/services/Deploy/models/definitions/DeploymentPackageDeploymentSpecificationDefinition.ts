/**
 * Created by ModelParser
 */
import {DeploymentPackage} from '../DeploymentPackage';
import {DeploymentSpecification} from '../DeploymentSpecification';
import {User} from '../User';
import {Deletion} from '../Deletion';
import {BaseModel} from '../BaseModel';

export class DeploymentPackageDeploymentSpecificationDefinition extends BaseModel {
    deployment_package_id?: number;
    deployment_package?: DeploymentPackage;
    deployment_specification_id?: number;
    deployment_specification?: DeploymentSpecification;
    default_version?: string;
    default_environment?: string;
    default_cpu_request?: number;
    default_cpu_limit?: number;
    default_memory_request?: number;
    default_memory_limit?: number;
    default_replicas?: number;
    default_knative_concurrency_limit_soft?: number;
    default_knative_concurrency_limit_hard?: number;
    default_auto_update_enabled?: boolean;
    default_auto_update_tag_regex?: string;
    default_auto_update_require_approval?: boolean;
    id?: number;
    created?: string;
    updated?: string;
    created_by_id?: number;
    created_by?: User;
    updated_by_id?: number;
    updated_by?: User;
    deletion_id?: number;
    deletion?: Deletion;

    constructor(data?: any) {
        super();
        this.populate(data);
    }

    public populate(data?: any, patch = false) {
        if (!patch) {
            delete this.deployment_package_id;
            delete this.deployment_package;
            delete this.deployment_specification_id;
            delete this.deployment_specification;
            delete this.default_version;
            delete this.default_environment;
            delete this.default_cpu_request;
            delete this.default_cpu_limit;
            delete this.default_memory_request;
            delete this.default_memory_limit;
            delete this.default_replicas;
            delete this.default_knative_concurrency_limit_soft;
            delete this.default_knative_concurrency_limit_hard;
            delete this.default_auto_update_enabled;
            delete this.default_auto_update_tag_regex;
            delete this.default_auto_update_require_approval;
            delete this.id;
            delete this.created;
            delete this.updated;
            delete this.created_by_id;
            delete this.created_by;
            delete this.updated_by_id;
            delete this.updated_by;
            delete this.deletion_id;
            delete this.deletion;
        }

        if (!data) return;
        if (data.deployment_package_id != null) {
            this.deployment_package_id = data.deployment_package_id;
        }
        if (data.deployment_package != null) {
            this.deployment_package = new DeploymentPackage(data.deployment_package);
        }
        if (data.deployment_specification_id != null) {
            this.deployment_specification_id = data.deployment_specification_id;
        }
        if (data.deployment_specification != null) {
            this.deployment_specification = new DeploymentSpecification(data.deployment_specification);
        }
        if (data.default_version != null) {
            this.default_version = data.default_version;
        }
        if (data.default_environment != null) {
            this.default_environment = data.default_environment;
        }
        if (data.default_cpu_request != null) {
            this.default_cpu_request = data.default_cpu_request;
        }
        if (data.default_cpu_limit != null) {
            this.default_cpu_limit = data.default_cpu_limit;
        }
        if (data.default_memory_request != null) {
            this.default_memory_request = data.default_memory_request;
        }
        if (data.default_memory_limit != null) {
            this.default_memory_limit = data.default_memory_limit;
        }
        if (data.default_replicas != null) {
            this.default_replicas = data.default_replicas;
        }
        if (data.default_knative_concurrency_limit_soft != null) {
            this.default_knative_concurrency_limit_soft = data.default_knative_concurrency_limit_soft;
        }
        if (data.default_knative_concurrency_limit_hard != null) {
            this.default_knative_concurrency_limit_hard = data.default_knative_concurrency_limit_hard;
        }
        if (data.default_auto_update_enabled != null) {
            this.default_auto_update_enabled = data.default_auto_update_enabled;
        }
        if (data.default_auto_update_tag_regex != null) {
            this.default_auto_update_tag_regex = data.default_auto_update_tag_regex;
        }
        if (data.default_auto_update_require_approval != null) {
            this.default_auto_update_require_approval = data.default_auto_update_require_approval;
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
        if (data.deletion_id != null) {
            this.deletion_id = data.deletion_id;
        }
        if (data.deletion != null) {
            this.deletion = new Deletion(data.deletion);
        }
    }

}
