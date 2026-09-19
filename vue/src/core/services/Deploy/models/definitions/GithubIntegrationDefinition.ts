/**
 * Created by ModelParser
 */
import {ContainerImage} from '../ContainerImage';
import {User} from '../User';
import {Deletion} from '../Deletion';
import {BaseModel} from '../BaseModel';

export class GithubIntegrationDefinition extends BaseModel {
    name?: string;
    organization?: string;
    app_id?: number;
    client_id?: string;
    client_secret?: string;
    private_key?: string;
    webhook_secret?: string;
    slug?: string;
    installation_id?: number;
    setup_state?: string;
    has_client_secret?: boolean;
    has_private_key?: boolean;
    has_webhook_secret?: boolean;
    container_images?: ContainerImage[];
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
            delete this.organization;
            delete this.app_id;
            delete this.client_id;
            delete this.client_secret;
            delete this.private_key;
            delete this.webhook_secret;
            delete this.slug;
            delete this.installation_id;
            delete this.setup_state;
            delete this.has_client_secret;
            delete this.has_private_key;
            delete this.has_webhook_secret;
            delete this.container_images;
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
        if (data.organization != null) {
            this.organization = data.organization;
        }
        if (data.app_id != null) {
            this.app_id = data.app_id;
        }
        if (data.client_id != null) {
            this.client_id = data.client_id;
        }
        if (data.client_secret != null) {
            this.client_secret = data.client_secret;
        }
        if (data.private_key != null) {
            this.private_key = data.private_key;
        }
        if (data.webhook_secret != null) {
            this.webhook_secret = data.webhook_secret;
        }
        if (data.slug != null) {
            this.slug = data.slug;
        }
        if (data.installation_id != null) {
            this.installation_id = data.installation_id;
        }
        if (data.setup_state != null) {
            this.setup_state = data.setup_state;
        }
        if (data.has_client_secret != null) {
            this.has_client_secret = data.has_client_secret;
        }
        if (data.has_private_key != null) {
            this.has_private_key = data.has_private_key;
        }
        if (data.has_webhook_secret != null) {
            this.has_webhook_secret = data.has_webhook_secret;
        }
        if (data.container_images != null) {
            this.container_images = data.container_images.map((i: any) => new ContainerImage(i));
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
