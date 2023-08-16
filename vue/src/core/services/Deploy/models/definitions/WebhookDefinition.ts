/**
 * Created by ModelParser
 */
import {WebhookDelivery} from '../WebhookDelivery';
import {User} from '../User';
import {BaseModel} from '../BaseModel';

export class WebhookDefinition extends BaseModel {
    type?: string;
    name?: string;
    url?: string;
    content_type?: string;
    auth_bearer_token?: string;
    webhook_deliveries?: WebhookDelivery[];
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
            delete this.type;
            delete this.name;
            delete this.url;
            delete this.content_type;
            delete this.auth_bearer_token;
            delete this.webhook_deliveries;
            delete this.id;
            delete this.created;
            delete this.updated;
            delete this.created_by_id;
            delete this.created_by;
            delete this.updated_by_id;
            delete this.updated_by;
        }

        if (!data) return;
        if (data.type != null) {
            this.type = data.type;
        }
        if (data.name != null) {
            this.name = data.name;
        }
        if (data.url != null) {
            this.url = data.url;
        }
        if (data.content_type != null) {
            this.content_type = data.content_type;
        }
        if (data.auth_bearer_token != null) {
            this.auth_bearer_token = data.auth_bearer_token;
        }
        if (data.webhook_deliveries != null) {
            this.webhook_deliveries = data.webhook_deliveries.map((i: any) => new WebhookDelivery(i));
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
