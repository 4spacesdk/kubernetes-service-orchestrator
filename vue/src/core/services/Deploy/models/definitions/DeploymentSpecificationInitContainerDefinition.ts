/**
 * Created by ModelParser
 */
import {DeploymentSpecification} from '../DeploymentSpecification';
import {InitContainer} from '../InitContainer';
import {User} from '../User';
import {Deletion} from '../Deletion';
import {BaseModel} from '../BaseModel';

export class DeploymentSpecificationInitContainerDefinition extends BaseModel {
    deployment_specification_id?: number;
    deployment_specification?: DeploymentSpecification;
    init_container_id?: number;
    init_container?: InitContainer;
    position?: number;
    include_in_migration_job?: boolean;
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
            delete this.deployment_specification_id;
            delete this.deployment_specification;
            delete this.init_container_id;
            delete this.init_container;
            delete this.position;
            delete this.include_in_migration_job;
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
        if (data.deployment_specification_id != null) {
            this.deployment_specification_id = data.deployment_specification_id;
        }
        if (data.deployment_specification != null) {
            this.deployment_specification = new DeploymentSpecification(data.deployment_specification);
        }
        if (data.init_container_id != null) {
            this.init_container_id = data.init_container_id;
        }
        if (data.init_container != null) {
            this.init_container = new InitContainer(data.init_container);
        }
        if (data.position != null) {
            this.position = data.position;
        }
        if (data.include_in_migration_job != null) {
            this.include_in_migration_job = data.include_in_migration_job;
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
