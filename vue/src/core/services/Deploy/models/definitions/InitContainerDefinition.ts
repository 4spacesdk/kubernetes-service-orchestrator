/**
 * Created by ModelParser
 */
import {ContainerImage} from '../ContainerImage';
import {InitContainerEnvironmentVariable} from '../InitContainerEnvironmentVariable';
import {DeploymentSpecification} from '../DeploymentSpecification';
import {User} from '../User';
import {Deletion} from '../Deletion';
import {BaseModel} from '../BaseModel';

export class InitContainerDefinition extends BaseModel {
    name?: string;
    container_image_id?: number;
    container_image?: ContainerImage;
    command?: string;
    args?: string;
    container_image_tag_policy?: string;
    container_image_tag_value?: string;
    container_image_pull_policy?: string;
    include_deployment_environment_variables?: boolean;
    init_container_environment_variables?: InitContainerEnvironmentVariable[];
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
            delete this.container_image_id;
            delete this.container_image;
            delete this.command;
            delete this.args;
            delete this.container_image_tag_policy;
            delete this.container_image_tag_value;
            delete this.container_image_pull_policy;
            delete this.include_deployment_environment_variables;
            delete this.init_container_environment_variables;
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
        if (data.init_container_environment_variables != null) {
            this.init_container_environment_variables = data.init_container_environment_variables.map((i: any) => new InitContainerEnvironmentVariable(i));
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
