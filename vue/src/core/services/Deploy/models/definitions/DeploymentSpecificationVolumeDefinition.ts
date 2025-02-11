/**
 * Created by ModelParser
 */
import {DeploymentSpecification} from '../DeploymentSpecification';
import {User} from '../User';
import {Deletion} from '../Deletion';
import {BaseModel} from '../BaseModel';

export class DeploymentSpecificationVolumeDefinition extends BaseModel {
    deployment_specification_id?: number;
    deployment_specification?: DeploymentSpecification;
    mount_path?: string;
    sub_path?: string;
    capacity?: number;
    volume_mode?: string;
    reclaim_policy?: string;
    nfs_server?: string;
    nfs_path?: string;
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
            delete this.mount_path;
            delete this.sub_path;
            delete this.capacity;
            delete this.volume_mode;
            delete this.reclaim_policy;
            delete this.nfs_server;
            delete this.nfs_path;
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
        if (data.mount_path != null) {
            this.mount_path = data.mount_path;
        }
        if (data.sub_path != null) {
            this.sub_path = data.sub_path;
        }
        if (data.capacity != null) {
            this.capacity = data.capacity;
        }
        if (data.volume_mode != null) {
            this.volume_mode = data.volume_mode;
        }
        if (data.reclaim_policy != null) {
            this.reclaim_policy = data.reclaim_policy;
        }
        if (data.nfs_server != null) {
            this.nfs_server = data.nfs_server;
        }
        if (data.nfs_path != null) {
            this.nfs_path = data.nfs_path;
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
