/**
 * Created by ModelParser
 */
import {PodioFieldReference} from '../PodioFieldReference';
import {User} from '../User';
import {Deletion} from '../Deletion';
import {BaseModel} from '../BaseModel';

export class PodioIntegrationDefinition extends BaseModel {
    name?: string;
    client_id?: string;
    client_secret?: string;
    app_id?: string;
    app_token?: string;
    podio_field_references?: PodioFieldReference[];
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
            delete this.name;
            delete this.client_id;
            delete this.client_secret;
            delete this.app_id;
            delete this.app_token;
            delete this.podio_field_references;
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
        if (data.name != null) {
            this.name = data.name;
        }
        if (data.client_id != null) {
            this.client_id = data.client_id;
        }
        if (data.client_secret != null) {
            this.client_secret = data.client_secret;
        }
        if (data.app_id != null) {
            this.app_id = data.app_id;
        }
        if (data.app_token != null) {
            this.app_token = data.app_token;
        }
        if (data.podio_field_references != null) {
            this.podio_field_references = data.podio_field_references.map((i: any) => new PodioFieldReference(i));
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
