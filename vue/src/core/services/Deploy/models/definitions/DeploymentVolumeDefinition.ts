/**
 * Created by ModelParser
 */
import {Deployment} from '../Deployment';
import {User} from '../User';
import {BaseModel} from '../BaseModel';

export class DeploymentVolumeDefinition extends BaseModel {
    deployment_id?: number;
    deployment?: Deployment;
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

    constructor(data?: any) {
        super();
        this.populate(data);
    }

    public populate(data?: any, patch = false) {
        if (!patch) {
            delete this.deployment_id;
            delete this.deployment;
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
        }

        if (!data) return;
        if (data.deployment_id != null) {
            this.deployment_id = data.deployment_id;
        }
        if (data.deployment != null) {
            this.deployment = new Deployment(data.deployment);
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
    }

}
