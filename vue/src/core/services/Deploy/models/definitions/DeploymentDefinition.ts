/**
 * Created by ModelParser
 */
import {Workspace} from '../Workspace';
import {DeploymentSpecification} from '../DeploymentSpecification';
import {DatabaseService} from '../DatabaseService';
import {MigrationJob} from '../MigrationJob';
import {EnvironmentVariable} from '../EnvironmentVariable';
import {DeploymentVolume} from '../DeploymentVolume';
import {Label} from '../Label';
import {User} from '../User';
import {Deletion} from '../Deletion';
import {BaseModel} from '../BaseModel';

export class DeploymentDefinition extends BaseModel {
    workspace_id?: number;
    workspace?: Workspace;
    deployment_specification_id?: number;
    deployment_specification?: DeploymentSpecification;
    name?: string;
    namespace?: string;
    image?: string;
    version?: string;
    image_pull_policy?: string;
    environment?: string;
    status?: string;
    last_updated?: string;
    custom_resource?: string;
    database_service_id?: number;
    database_service?: DatabaseService;
    database_user?: string;
    database_name?: string;
    database_pass?: string;
    auto_update_enabled?: boolean;
    auto_update_tag_regex?: string;
    auto_update_require_approval?: boolean;
    cpu_limit?: number;
    cpu_request?: number;
    memory_limit?: number;
    memory_request?: number;
    replicas?: number;
    knative_concurrency_limit_soft?: number;
    knative_concurrency_limit_hard?: number;
    last_migration_job_id?: number;
    last_migration_job?: MigrationJob;
    environment_variables?: EnvironmentVariable[];
    deployment_volumes?: DeploymentVolume[];
    last_migration_jobs?: MigrationJob[];
    labels?: Label[];
    url_external?: string;
    url_internal?: string;
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
            delete this.workspace_id;
            delete this.workspace;
            delete this.deployment_specification_id;
            delete this.deployment_specification;
            delete this.name;
            delete this.namespace;
            delete this.image;
            delete this.version;
            delete this.image_pull_policy;
            delete this.environment;
            delete this.status;
            delete this.last_updated;
            delete this.custom_resource;
            delete this.database_service_id;
            delete this.database_service;
            delete this.database_user;
            delete this.database_name;
            delete this.database_pass;
            delete this.auto_update_enabled;
            delete this.auto_update_tag_regex;
            delete this.auto_update_require_approval;
            delete this.cpu_limit;
            delete this.cpu_request;
            delete this.memory_limit;
            delete this.memory_request;
            delete this.replicas;
            delete this.knative_concurrency_limit_soft;
            delete this.knative_concurrency_limit_hard;
            delete this.last_migration_job_id;
            delete this.last_migration_job;
            delete this.environment_variables;
            delete this.deployment_volumes;
            delete this.last_migration_jobs;
            delete this.labels;
            delete this.url_external;
            delete this.url_internal;
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
        if (data.workspace_id != null) {
            this.workspace_id = data.workspace_id;
        }
        if (data.workspace != null) {
            this.workspace = new Workspace(data.workspace);
        }
        if (data.deployment_specification_id != null) {
            this.deployment_specification_id = data.deployment_specification_id;
        }
        if (data.deployment_specification != null) {
            this.deployment_specification = new DeploymentSpecification(data.deployment_specification);
        }
        if (data.name != null) {
            this.name = data.name;
        }
        if (data.namespace != null) {
            this.namespace = data.namespace;
        }
        if (data.image != null) {
            this.image = data.image;
        }
        if (data.version != null) {
            this.version = data.version;
        }
        if (data.image_pull_policy != null) {
            this.image_pull_policy = data.image_pull_policy;
        }
        if (data.environment != null) {
            this.environment = data.environment;
        }
        if (data.status != null) {
            this.status = data.status;
        }
        if (data.last_updated != null) {
            this.last_updated = data.last_updated;
        }
        if (data.custom_resource != null) {
            this.custom_resource = data.custom_resource;
        }
        if (data.database_service_id != null) {
            this.database_service_id = data.database_service_id;
        }
        if (data.database_service != null) {
            this.database_service = new DatabaseService(data.database_service);
        }
        if (data.database_user != null) {
            this.database_user = data.database_user;
        }
        if (data.database_name != null) {
            this.database_name = data.database_name;
        }
        if (data.database_pass != null) {
            this.database_pass = data.database_pass;
        }
        if (data.auto_update_enabled != null) {
            this.auto_update_enabled = data.auto_update_enabled;
        }
        if (data.auto_update_tag_regex != null) {
            this.auto_update_tag_regex = data.auto_update_tag_regex;
        }
        if (data.auto_update_require_approval != null) {
            this.auto_update_require_approval = data.auto_update_require_approval;
        }
        if (data.cpu_limit != null) {
            this.cpu_limit = data.cpu_limit;
        }
        if (data.cpu_request != null) {
            this.cpu_request = data.cpu_request;
        }
        if (data.memory_limit != null) {
            this.memory_limit = data.memory_limit;
        }
        if (data.memory_request != null) {
            this.memory_request = data.memory_request;
        }
        if (data.replicas != null) {
            this.replicas = data.replicas;
        }
        if (data.knative_concurrency_limit_soft != null) {
            this.knative_concurrency_limit_soft = data.knative_concurrency_limit_soft;
        }
        if (data.knative_concurrency_limit_hard != null) {
            this.knative_concurrency_limit_hard = data.knative_concurrency_limit_hard;
        }
        if (data.last_migration_job_id != null) {
            this.last_migration_job_id = data.last_migration_job_id;
        }
        if (data.last_migration_job != null) {
            this.last_migration_job = new MigrationJob(data.last_migration_job);
        }
        if (data.environment_variables != null) {
            this.environment_variables = data.environment_variables.map((i: any) => new EnvironmentVariable(i));
        }
        if (data.deployment_volumes != null) {
            this.deployment_volumes = data.deployment_volumes.map((i: any) => new DeploymentVolume(i));
        }
        if (data.last_migration_jobs != null) {
            this.last_migration_jobs = data.last_migration_jobs.map((i: any) => new MigrationJob(i));
        }
        if (data.labels != null) {
            this.labels = data.labels.map((i: any) => new Label(i));
        }
        if (data.url_external != null) {
            this.url_external = data.url_external;
        }
        if (data.url_internal != null) {
            this.url_internal = data.url_internal;
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
