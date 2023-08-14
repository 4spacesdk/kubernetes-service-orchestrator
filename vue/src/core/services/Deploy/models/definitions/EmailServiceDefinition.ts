/**
 * Created by ModelParser
 */
import {Workspace} from '../Workspace';
import {Deployment} from '../Deployment';
import {User} from '../User';
import {BaseModel} from '../BaseModel';

export class EmailServiceDefinition extends BaseModel {
    name?: string;
    host?: string;
    port?: number;
    user?: string;
    pass?: string;
    from?: string;
    workspaces?: Workspace[];
    deployments?: Deployment[];
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
            delete this.name;
            delete this.host;
            delete this.port;
            delete this.user;
            delete this.pass;
            delete this.from;
            delete this.workspaces;
            delete this.deployments;
            delete this.id;
            delete this.created;
            delete this.updated;
            delete this.created_by_id;
            delete this.created_by;
            delete this.updated_by_id;
            delete this.updated_by;
        }

        if (!data) return;
        if (data.name != null) {
            this.name = data.name;
        }
        if (data.host != null) {
            this.host = data.host;
        }
        if (data.port != null) {
            this.port = data.port;
        }
        if (data.user != null) {
            this.user = data.user;
        }
        if (data.pass != null) {
            this.pass = data.pass;
        }
        if (data.from != null) {
            this.from = data.from;
        }
        if (data.workspaces != null) {
            this.workspaces = data.workspaces.map((i: any) => new Workspace(i));
        }
        if (data.deployments != null) {
            this.deployments = data.deployments.map((i: any) => new Deployment(i));
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
