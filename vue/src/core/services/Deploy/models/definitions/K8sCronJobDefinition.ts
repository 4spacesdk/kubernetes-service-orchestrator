/**
 * Created by ModelParser
 */
import {ContainerImage} from '../ContainerImage';
import {DeploymentSpecification} from '../DeploymentSpecification';
import {User} from '../User';
import {Deletion} from '../Deletion';
import {BaseModel} from '../BaseModel';

export class K8sCronJobDefinition extends BaseModel {
    name?: string;
    schedule?: string;
    concurrency_policy?: string;
    restart_policy?: string;
    successful_jobs_history_limit?: number;
    failed_jobs_history_limit?: number;
    cpu_limit?: number;
    cpu_request?: number;
    memory_limit?: number;
    memory_request?: number;
    container_image_id?: number;
    container_image?: ContainerImage;
    command?: string;
    args?: string;
    container_image_tag_policy?: string;
    container_image_tag_value?: string;
    container_image_pull_policy?: string;
    include_deployment_environment_variables?: boolean;
    deployment_specifications?: DeploymentSpecification[];
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
            delete this.name;
            delete this.schedule;
            delete this.concurrency_policy;
            delete this.restart_policy;
            delete this.successful_jobs_history_limit;
            delete this.failed_jobs_history_limit;
            delete this.cpu_limit;
            delete this.cpu_request;
            delete this.memory_limit;
            delete this.memory_request;
            delete this.container_image_id;
            delete this.container_image;
            delete this.command;
            delete this.args;
            delete this.container_image_tag_policy;
            delete this.container_image_tag_value;
            delete this.container_image_pull_policy;
            delete this.include_deployment_environment_variables;
            delete this.deployment_specifications;
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
        if (data.name != null) {
            this.name = data.name;
        }
        if (data.schedule != null) {
            this.schedule = data.schedule;
        }
        if (data.concurrency_policy != null) {
            this.concurrency_policy = data.concurrency_policy;
        }
        if (data.restart_policy != null) {
            this.restart_policy = data.restart_policy;
        }
        if (data.successful_jobs_history_limit != null) {
            this.successful_jobs_history_limit = data.successful_jobs_history_limit;
        }
        if (data.failed_jobs_history_limit != null) {
            this.failed_jobs_history_limit = data.failed_jobs_history_limit;
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
        if (data.container_image_id != null) {
            this.container_image_id = data.container_image_id;
        }
        if (data.container_image != null) {
            this.container_image = new ContainerImage(data.container_image);
        }
        if (data.command != null) {
            this.command = data.command;
        }
        if (data.args != null) {
            this.args = data.args;
        }
        if (data.container_image_tag_policy != null) {
            this.container_image_tag_policy = data.container_image_tag_policy;
        }
        if (data.container_image_tag_value != null) {
            this.container_image_tag_value = data.container_image_tag_value;
        }
        if (data.container_image_pull_policy != null) {
            this.container_image_pull_policy = data.container_image_pull_policy;
        }
        if (data.include_deployment_environment_variables != null) {
            this.include_deployment_environment_variables = data.include_deployment_environment_variables;
        }
        if (data.deployment_specifications != null) {
            this.deployment_specifications = data.deployment_specifications.map((i: any) => new DeploymentSpecification(i));
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
