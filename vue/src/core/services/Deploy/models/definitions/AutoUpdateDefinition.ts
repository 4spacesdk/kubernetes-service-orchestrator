/**
 * Created by ModelParser
 */
import {Deployment} from '../Deployment';
import {User} from '../User';
import {Deletion} from '../Deletion';
import {BaseModel} from '../BaseModel';

export class AutoUpdateDefinition extends BaseModel {
    deployment_id?: number;
    deployment?: Deployment;
    image?: string;
    previous_tag?: string;
    next_tag?: string;
    is_approved?: boolean;
    approved_date?: string;
    log?: string;
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
            delete this.deployment_id;
            delete this.deployment;
            delete this.image;
            delete this.previous_tag;
            delete this.next_tag;
            delete this.is_approved;
            delete this.approved_date;
            delete this.log;
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
        if (data.deployment_id != null) {
            this.deployment_id = data.deployment_id;
        }
        if (data.deployment != null) {
            this.deployment = new Deployment(data.deployment);
        }
        if (data.image != null) {
            this.image = data.image;
        }
        if (data.previous_tag != null) {
            this.previous_tag = data.previous_tag;
        }
        if (data.next_tag != null) {
            this.next_tag = data.next_tag;
        }
        if (data.is_approved != null) {
            this.is_approved = data.is_approved;
        }
        if (data.approved_date != null) {
            this.approved_date = data.approved_date;
        }
        if (data.log != null) {
            this.log = data.log;
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
