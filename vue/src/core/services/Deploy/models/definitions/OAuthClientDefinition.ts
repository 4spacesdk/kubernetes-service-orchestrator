/**
 * Created by ModelParser
 */
import {User} from '../User';
import {Deletion} from '../Deletion';
import {BaseModel} from '../BaseModel';

export class OAuthClientDefinition extends BaseModel {
    client_id?: string;
    client_secret?: string;
    redirect_uri?: string;
    grant_types?: string;
    scope?: string;
    user_id?: string;
    user?: User;
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
            delete this.client_id;
            delete this.client_secret;
            delete this.redirect_uri;
            delete this.grant_types;
            delete this.scope;
            delete this.user_id;
            delete this.user;
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
        if (data.client_id != null) {
            this.client_id = data.client_id;
        }
        if (data.client_secret != null) {
            this.client_secret = data.client_secret;
        }
        if (data.redirect_uri != null) {
            this.redirect_uri = data.redirect_uri;
        }
        if (data.grant_types != null) {
            this.grant_types = data.grant_types;
        }
        if (data.scope != null) {
            this.scope = data.scope;
        }
        if (data.user_id != null) {
            this.user_id = data.user_id;
        }
        if (data.user != null) {
            this.user = new User(data.user);
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
