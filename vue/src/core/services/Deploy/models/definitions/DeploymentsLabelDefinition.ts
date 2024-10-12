/**
 * Created by ModelParser
 */
import {Deployment} from '../Deployment';
import {Label} from '../Label';
import {User} from '../User';
import {Deletion} from '../Deletion';
import {BaseModel} from '../BaseModel';

export class DeploymentsLabelDefinition extends BaseModel {
    deployment_id?: number;
    deployment?: Deployment;
    label_id?: number;
    label?: Label;
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
            delete this.deployment_id;
            delete this.deployment;
            delete this.label_id;
            delete this.label;
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
        if (data.deployment_id != null) {
            this.deployment_id = data.deployment_id;
        }
        if (data.deployment != null) {
            this.deployment = new Deployment(data.deployment);
        }
        if (data.label_id != null) {
            this.label_id = data.label_id;
        }
        if (data.label != null) {
            this.label = new Label(data.label);
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
