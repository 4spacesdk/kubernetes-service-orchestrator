/**
 * Created by ModelParser
 */
import {User} from '../User';
import {Deletion} from '../Deletion';
import {BaseModel} from '../BaseModel';

export class AuditEventDefinition extends BaseModel {
    user_id?: number;
    client_id?: string;
    ip_address?: string;
    source?: string;
    action?: string;
    resource_type?: string;
    resource_id?: number;
    resource_name?: string;
    details?: string;
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
            delete this.user_id;
            delete this.client_id;
            delete this.ip_address;
            delete this.source;
            delete this.action;
            delete this.resource_type;
            delete this.resource_id;
            delete this.resource_name;
            delete this.details;
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
        if (data.user_id != null) {
            this.user_id = data.user_id;
        }
        if (data.client_id != null) {
            this.client_id = data.client_id;
        }
        if (data.ip_address != null) {
            this.ip_address = data.ip_address;
        }
        if (data.source != null) {
            this.source = data.source;
        }
        if (data.action != null) {
            this.action = data.action;
        }
        if (data.resource_type != null) {
            this.resource_type = data.resource_type;
        }
        if (data.resource_id != null) {
            this.resource_id = data.resource_id;
        }
        if (data.resource_name != null) {
            this.resource_name = data.resource_name;
        }
        if (data.details != null) {
            this.details = data.details;
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
