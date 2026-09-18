/**
 * Created by ModelParser
 */
import {ContainerImage} from '../ContainerImage';
import {User} from '../User';
import {Deletion} from '../Deletion';
import {BaseModel} from '../BaseModel';

export class ContainerRegistryDefinition extends BaseModel {
    name?: string;
    provider?: string;
    gcloud_project?: string;
    gcloud_location?: string;
    gcloud_registry_name?: string;
    gcloud_credentials?: string;
    azure_registry_name?: string;
    azure_tenant?: string;
    azure_client_id?: string;
    azure_client_secret?: string;
    azure_subscription_id?: string;
    azure_resource_group?: string;
    harbor_url?: string;
    harbor_username?: string;
    harbor_password?: string;
    pull_username?: string;
    pull_password?: string;
    events_enabled?: boolean;
    webhook_secret?: string;
    has_gcloud_credentials?: boolean;
    has_azure_client_secret?: boolean;
    has_harbor_password?: boolean;
    has_webhook_secret?: boolean;
    has_pull_password?: boolean;
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
            delete this.provider;
            delete this.gcloud_project;
            delete this.gcloud_location;
            delete this.gcloud_registry_name;
            delete this.gcloud_credentials;
            delete this.azure_registry_name;
            delete this.azure_tenant;
            delete this.azure_client_id;
            delete this.azure_client_secret;
            delete this.azure_subscription_id;
            delete this.azure_resource_group;
            delete this.harbor_url;
            delete this.harbor_username;
            delete this.harbor_password;
            delete this.pull_username;
            delete this.pull_password;
            delete this.events_enabled;
            delete this.webhook_secret;
            delete this.has_gcloud_credentials;
            delete this.has_azure_client_secret;
            delete this.has_harbor_password;
            delete this.has_webhook_secret;
            delete this.has_pull_password;
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
        if (data.provider != null) {
            this.provider = data.provider;
        }
        if (data.gcloud_project != null) {
            this.gcloud_project = data.gcloud_project;
        }
        if (data.gcloud_location != null) {
            this.gcloud_location = data.gcloud_location;
        }
        if (data.gcloud_registry_name != null) {
            this.gcloud_registry_name = data.gcloud_registry_name;
        }
        if (data.gcloud_credentials != null) {
            this.gcloud_credentials = data.gcloud_credentials;
        }
        if (data.azure_registry_name != null) {
            this.azure_registry_name = data.azure_registry_name;
        }
        if (data.azure_tenant != null) {
            this.azure_tenant = data.azure_tenant;
        }
        if (data.azure_client_id != null) {
            this.azure_client_id = data.azure_client_id;
        }
        if (data.azure_client_secret != null) {
            this.azure_client_secret = data.azure_client_secret;
        }
        if (data.azure_subscription_id != null) {
            this.azure_subscription_id = data.azure_subscription_id;
        }
        if (data.azure_resource_group != null) {
            this.azure_resource_group = data.azure_resource_group;
        }
        if (data.harbor_url != null) {
            this.harbor_url = data.harbor_url;
        }
        if (data.harbor_username != null) {
            this.harbor_username = data.harbor_username;
        }
        if (data.harbor_password != null) {
            this.harbor_password = data.harbor_password;
        }
        if (data.pull_username != null) {
            this.pull_username = data.pull_username;
        }
        if (data.pull_password != null) {
            this.pull_password = data.pull_password;
        }
        if (data.events_enabled != null) {
            this.events_enabled = data.events_enabled;
        }
        if (data.webhook_secret != null) {
            this.webhook_secret = data.webhook_secret;
        }
        if (data.has_gcloud_credentials != null) {
            this.has_gcloud_credentials = data.has_gcloud_credentials;
        }
        if (data.has_azure_client_secret != null) {
            this.has_azure_client_secret = data.has_azure_client_secret;
        }
        if (data.has_harbor_password != null) {
            this.has_harbor_password = data.has_harbor_password;
        }
        if (data.has_webhook_secret != null) {
            this.has_webhook_secret = data.has_webhook_secret;
        }
        if (data.has_pull_password != null) {
            this.has_pull_password = data.has_pull_password;
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
