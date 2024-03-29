/**
 * Created by ModelParser
 */
import {Workspace} from '../Workspace';
import {Deployment} from '../Deployment';
import {User} from '../User';
import {Deletion} from '../Deletion';
import {BaseModel} from '../BaseModel';

export class DatabaseServiceDefinition extends BaseModel {
    name?: string;
    driver?: string;
    host?: string;
    azure_host?: string;
    port?: string;
    user?: string;
    pass?: string;
    workspaces?: Workspace[];
    deployments?: Deployment[];
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
            delete this.driver;
            delete this.host;
            delete this.azure_host;
            delete this.port;
            delete this.user;
            delete this.pass;
            delete this.workspaces;
            delete this.deployments;
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
        if (data.driver != null) {
            this.driver = data.driver;
        }
        if (data.host != null) {
            this.host = data.host;
        }
        if (data.azure_host != null) {
            this.azure_host = data.azure_host;
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
        if (data.deletion_id != null) {
            this.deletion_id = data.deletion_id;
        }
        if (data.deletion != null) {
            this.deletion = new Deletion(data.deletion);
        }
    }

}
