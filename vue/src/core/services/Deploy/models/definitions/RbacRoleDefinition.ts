/**
 * Created by ModelParser
 */
import {User} from '../User';
import {RbacPermission} from '../RbacPermission';
import {Deletion} from '../Deletion';
import {BaseModel} from '../BaseModel';

export class RbacRoleDefinition extends BaseModel {
    name?: string;
    identifier?: string;
    description?: string;
    users?: User[];
    rbac_permissions?: RbacPermission[];
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
            delete this.identifier;
            delete this.description;
            delete this.users;
            delete this.rbac_permissions;
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
        if (data.identifier != null) {
            this.identifier = data.identifier;
        }
        if (data.description != null) {
            this.description = data.description;
        }
        if (data.users != null) {
            this.users = data.users.map((i: any) => new User(i));
        }
        if (data.rbac_permissions != null) {
            this.rbac_permissions = data.rbac_permissions.map((i: any) => new RbacPermission(i));
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
