/**
 * Created by ModelParser
 */
import {PostUpdateAction} from '../PostUpdateAction';
import {PodioFieldReference} from '../PodioFieldReference';
import {User} from '../User';
import {Deletion} from '../Deletion';
import {BaseModel} from '../BaseModel';

export class PostUpdateActionConditionDefinition extends BaseModel {
    post_update_action_id?: number;
    post_update_action?: PostUpdateAction;
    type?: string;
    podio_field_reference_id?: number;
    podio_field_reference?: PodioFieldReference;
    value?: string;
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
            delete this.post_update_action_id;
            delete this.post_update_action;
            delete this.type;
            delete this.podio_field_reference_id;
            delete this.podio_field_reference;
            delete this.value;
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
        if (data.post_update_action_id != null) {
            this.post_update_action_id = data.post_update_action_id;
        }
        if (data.post_update_action != null) {
            this.post_update_action = new PostUpdateAction(data.post_update_action);
        }
        if (data.type != null) {
            this.type = data.type;
        }
        if (data.podio_field_reference_id != null) {
            this.podio_field_reference_id = data.podio_field_reference_id;
        }
        if (data.podio_field_reference != null) {
            this.podio_field_reference = new PodioFieldReference(data.podio_field_reference);
        }
        if (data.value != null) {
            this.value = data.value;
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
