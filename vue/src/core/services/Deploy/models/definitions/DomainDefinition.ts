/**
 * Created by ModelParser
 */
import {Workspace} from '../Workspace';
import {Deployment} from '../Deployment';
import {User} from '../User';
import {Deletion} from '../Deletion';
import {BaseModel} from '../BaseModel';

export class DomainDefinition extends BaseModel {
    name?: string;
    certificate_namespace?: string;
    certificate_name?: string;
    issuer_ref_name?: string;
    enable_istio_gateway?: boolean;
    enable_contour?: boolean;
    contour_ingress_class_name?: string;
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
            delete this.certificate_namespace;
            delete this.certificate_name;
            delete this.issuer_ref_name;
            delete this.enable_istio_gateway;
            delete this.enable_contour;
            delete this.contour_ingress_class_name;
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
        if (data.certificate_namespace != null) {
            this.certificate_namespace = data.certificate_namespace;
        }
        if (data.certificate_name != null) {
            this.certificate_name = data.certificate_name;
        }
        if (data.issuer_ref_name != null) {
            this.issuer_ref_name = data.issuer_ref_name;
        }
        if (data.enable_istio_gateway != null) {
            this.enable_istio_gateway = data.enable_istio_gateway;
        }
        if (data.enable_contour != null) {
            this.enable_contour = data.enable_contour;
        }
        if (data.contour_ingress_class_name != null) {
            this.contour_ingress_class_name = data.contour_ingress_class_name;
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
