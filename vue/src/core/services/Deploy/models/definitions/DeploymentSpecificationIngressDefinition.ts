/**
 * Created by ModelParser
 */
import {DeploymentSpecification} from '../DeploymentSpecification';
import {DeploymentSpecificationIngressRulePath} from '../DeploymentSpecificationIngressRulePath';
import {DeploymentSpecificationIngressAnnotation} from '../DeploymentSpecificationIngressAnnotation';
import {User} from '../User';
import {Deletion} from '../Deletion';
import {BaseModel} from '../BaseModel';

export class DeploymentSpecificationIngressDefinition extends BaseModel {
    deployment_specification_id?: number;
    deployment_specification?: DeploymentSpecification;
    ingress_class?: string;
    proxy_body_size?: number;
    proxy_connect_timeout?: number;
    proxy_read_timeout?: number;
    proxy_send_timeout?: number;
    ssl_redirect?: boolean;
    enable_tls?: boolean;
    deployment_specification_ingress_rule_paths?: DeploymentSpecificationIngressRulePath[];
    deployment_specification_ingress_annotations?: DeploymentSpecificationIngressAnnotation[];
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
            delete this.deployment_specification_id;
            delete this.deployment_specification;
            delete this.ingress_class;
            delete this.proxy_body_size;
            delete this.proxy_connect_timeout;
            delete this.proxy_read_timeout;
            delete this.proxy_send_timeout;
            delete this.ssl_redirect;
            delete this.enable_tls;
            delete this.deployment_specification_ingress_rule_paths;
            delete this.deployment_specification_ingress_annotations;
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
        if (data.deployment_specification_id != null) {
            this.deployment_specification_id = data.deployment_specification_id;
        }
        if (data.deployment_specification != null) {
            this.deployment_specification = new DeploymentSpecification(data.deployment_specification);
        }
        if (data.ingress_class != null) {
            this.ingress_class = data.ingress_class;
        }
        if (data.proxy_body_size != null) {
            this.proxy_body_size = data.proxy_body_size;
        }
        if (data.proxy_connect_timeout != null) {
            this.proxy_connect_timeout = data.proxy_connect_timeout;
        }
        if (data.proxy_read_timeout != null) {
            this.proxy_read_timeout = data.proxy_read_timeout;
        }
        if (data.proxy_send_timeout != null) {
            this.proxy_send_timeout = data.proxy_send_timeout;
        }
        if (data.ssl_redirect != null) {
            this.ssl_redirect = data.ssl_redirect;
        }
        if (data.enable_tls != null) {
            this.enable_tls = data.enable_tls;
        }
        if (data.deployment_specification_ingress_rule_paths != null) {
            this.deployment_specification_ingress_rule_paths = data.deployment_specification_ingress_rule_paths.map((i: any) => new DeploymentSpecificationIngressRulePath(i));
        }
        if (data.deployment_specification_ingress_annotations != null) {
            this.deployment_specification_ingress_annotations = data.deployment_specification_ingress_annotations.map((i: any) => new DeploymentSpecificationIngressAnnotation(i));
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
