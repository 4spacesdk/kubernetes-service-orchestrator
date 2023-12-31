/**
 * Created by ModelParser
 */
import {User} from '../User';
import {BaseModel} from '../BaseModel';

export class UserDefinition extends BaseModel {
    username?: string;
    first_name?: string;
    last_name?: string;
    password?: string;
    scope?: string;
    type?: string;
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
            delete this.username;
            delete this.first_name;
            delete this.last_name;
            delete this.password;
            delete this.scope;
            delete this.type;
            delete this.id;
            delete this.created;
            delete this.updated;
            delete this.created_by_id;
            delete this.created_by;
            delete this.updated_by_id;
            delete this.updated_by;
        }

        if (!data) return;
        if (data.username != null) {
            this.username = data.username;
        }
        if (data.first_name != null) {
            this.first_name = data.first_name;
        }
        if (data.last_name != null) {
            this.last_name = data.last_name;
        }
        if (data.password != null) {
            this.password = data.password;
        }
        if (data.scope != null) {
            this.scope = data.scope;
        }
        if (data.type != null) {
            this.type = data.type;
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
