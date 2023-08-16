/**
 * Created by ModelParser
 */
import {Webhook} from '../Webhook';
import {User} from '../User';
import {BaseModel} from '../BaseModel';

export class WebhookDeliveryDefinition extends BaseModel {
    webhook_id?: number;
    webhook?: Webhook;
    url?: string;
    method?: string;
    content_type?: string;
    auth_bearer_token?: string;
    payload?: string;
    response_code?: number;
    response_headers?: string;
    response_body?: string;
    response_time?: number;
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
            delete this.webhook_id;
            delete this.webhook;
            delete this.url;
            delete this.method;
            delete this.content_type;
            delete this.auth_bearer_token;
            delete this.payload;
            delete this.response_code;
            delete this.response_headers;
            delete this.response_body;
            delete this.response_time;
            delete this.id;
            delete this.created;
            delete this.updated;
            delete this.created_by_id;
            delete this.created_by;
            delete this.updated_by_id;
            delete this.updated_by;
        }

        if (!data) return;
        if (data.webhook_id != null) {
            this.webhook_id = data.webhook_id;
        }
        if (data.webhook != null) {
            this.webhook = new Webhook(data.webhook);
        }
        if (data.url != null) {
            this.url = data.url;
        }
        if (data.method != null) {
            this.method = data.method;
        }
        if (data.content_type != null) {
            this.content_type = data.content_type;
        }
        if (data.auth_bearer_token != null) {
            this.auth_bearer_token = data.auth_bearer_token;
        }
        if (data.payload != null) {
            this.payload = data.payload;
        }
        if (data.response_code != null) {
            this.response_code = data.response_code;
        }
        if (data.response_headers != null) {
            this.response_headers = data.response_headers;
        }
        if (data.response_body != null) {
            this.response_body = data.response_body;
        }
        if (data.response_time != null) {
            this.response_time = data.response_time;
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
