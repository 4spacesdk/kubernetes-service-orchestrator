/**
 * Created by ModelParser
 */
import {PodioIntegration} from '../PodioIntegration';
import {PodioFieldReference} from '../PodioFieldReference';
import {PostUpdateActionCondition} from '../PostUpdateActionCondition';
import {DeploymentSpecification} from '../DeploymentSpecification';
import {User} from '../User';
import {Deletion} from '../Deletion';
import {BaseModel} from '../BaseModel';

export class PostUpdateActionDefinition extends BaseModel {
    name?: string;
    type?: string;
    podio_add_comment_integration_id?: number;
    podio_add_comment_integration?: PodioIntegration;
    podio_add_comment_value?: string;
    podio_field_update_field_reference_id?: number;
    podio_field_update_field_reference?: PodioFieldReference;
    podio_field_update_value?: string;
    post_update_action_conditions?: PostUpdateActionCondition[];
    deployment_specifications?: DeploymentSpecification[];
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
            delete this.type;
            delete this.podio_add_comment_integration_id;
            delete this.podio_add_comment_integration;
            delete this.podio_add_comment_value;
            delete this.podio_field_update_field_reference_id;
            delete this.podio_field_update_field_reference;
            delete this.podio_field_update_value;
            delete this.post_update_action_conditions;
            delete this.deployment_specifications;
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
        if (data.type != null) {
            this.type = data.type;
        }
        if (data.podio_add_comment_integration_id != null) {
            this.podio_add_comment_integration_id = data.podio_add_comment_integration_id;
        }
        if (data.podio_add_comment_integration != null) {
            this.podio_add_comment_integration = new PodioIntegration(data.podio_add_comment_integration);
        }
        if (data.podio_add_comment_value != null) {
            this.podio_add_comment_value = data.podio_add_comment_value;
        }
        if (data.podio_field_update_field_reference_id != null) {
            this.podio_field_update_field_reference_id = data.podio_field_update_field_reference_id;
        }
        if (data.podio_field_update_field_reference != null) {
            this.podio_field_update_field_reference = new PodioFieldReference(data.podio_field_update_field_reference);
        }
        if (data.podio_field_update_value != null) {
            this.podio_field_update_value = data.podio_field_update_value;
        }
        if (data.post_update_action_conditions != null) {
            this.post_update_action_conditions = data.post_update_action_conditions.map((i: any) => new PostUpdateActionCondition(i));
        }
        if (data.deployment_specifications != null) {
            this.deployment_specifications = data.deployment_specifications.map((i: any) => new DeploymentSpecification(i));
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
