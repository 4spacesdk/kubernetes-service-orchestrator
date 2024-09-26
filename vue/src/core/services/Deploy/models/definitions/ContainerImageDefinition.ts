/**
 * Created by ModelParser
 */
import {User} from '../User';
import {Deletion} from '../Deletion';
import {BaseModel} from '../BaseModel';

export class ContainerImageDefinition extends BaseModel {
    name?: string;
    url?: string;
    pull_secret?: string;
    registry_subscribe?: boolean;
    registry_provider?: string;
    registry_provider_gcloud_registry_name?: string;
    registry_provider_gcloud_project?: string;
    registry_provider_gcloud_location?: string;
    registry_provider_gcloud_credentials?: string;
    registry_provider_azure_registry_name?: string;
    registry_provider_azure_tenant?: string;
    registry_provider_azure_client_id?: string;
    registry_provider_azure_client_secret?: string;
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
            delete this.url;
            delete this.pull_secret;
            delete this.registry_subscribe;
            delete this.registry_provider;
            delete this.registry_provider_gcloud_registry_name;
            delete this.registry_provider_gcloud_project;
            delete this.registry_provider_gcloud_location;
            delete this.registry_provider_gcloud_credentials;
            delete this.registry_provider_azure_registry_name;
            delete this.registry_provider_azure_tenant;
            delete this.registry_provider_azure_client_id;
            delete this.registry_provider_azure_client_secret;
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
        if (data.url != null) {
            this.url = data.url;
        }
        if (data.pull_secret != null) {
            this.pull_secret = data.pull_secret;
        }
        if (data.registry_subscribe != null) {
            this.registry_subscribe = data.registry_subscribe;
        }
        if (data.registry_provider != null) {
            this.registry_provider = data.registry_provider;
        }
        if (data.registry_provider_gcloud_registry_name != null) {
            this.registry_provider_gcloud_registry_name = data.registry_provider_gcloud_registry_name;
        }
        if (data.registry_provider_gcloud_project != null) {
            this.registry_provider_gcloud_project = data.registry_provider_gcloud_project;
        }
        if (data.registry_provider_gcloud_location != null) {
            this.registry_provider_gcloud_location = data.registry_provider_gcloud_location;
        }
        if (data.registry_provider_gcloud_credentials != null) {
            this.registry_provider_gcloud_credentials = data.registry_provider_gcloud_credentials;
        }
        if (data.registry_provider_azure_registry_name != null) {
            this.registry_provider_azure_registry_name = data.registry_provider_azure_registry_name;
        }
        if (data.registry_provider_azure_tenant != null) {
            this.registry_provider_azure_tenant = data.registry_provider_azure_tenant;
        }
        if (data.registry_provider_azure_client_id != null) {
            this.registry_provider_azure_client_id = data.registry_provider_azure_client_id;
        }
        if (data.registry_provider_azure_client_secret != null) {
            this.registry_provider_azure_client_secret = data.registry_provider_azure_client_secret;
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
