/**
 * Created by ModelParser
 */
import {User} from '../User';
import {BaseModel} from '../BaseModel';

export class AuditEventDefinition extends BaseModel {
    created?: string;
    user_id?: number;
    user?: User;
    client_id?: string;
    ip_address?: string;
    source?: string;
    action?: string;
    resource_type?: string;
    resource_id?: number;
    resource_name?: string;
    details?: string;
    id?: number;

    constructor(data?: any) {
        super();
        this.populate(data);
    }

    public populate(data?: any, patch = false) {
        if (!patch) {
            delete this.created;
            delete this.user_id;
            delete this.user;
            delete this.client_id;
            delete this.ip_address;
            delete this.source;
            delete this.action;
            delete this.resource_type;
            delete this.resource_id;
            delete this.resource_name;
            delete this.details;
            delete this.id;
        }

        if (!data) return;
        if (data.created != null) {
            this.created = data.created;
        }
        if (data.user_id != null) {
            this.user_id = data.user_id;
        }
        if (data.user != null) {
            this.user = new User(data.user);
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
    }

}
