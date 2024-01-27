/**
 * Created by ModelParser
 */
import {DeploymentPackage} from '../DeploymentPackage';
import {EmailService} from '../EmailService';
import {Domain} from '../Domain';
import {DatabaseService} from '../DatabaseService';
import {Deployment} from '../Deployment';
import {Label} from '../Label';
import {User} from '../User';
import {Deletion} from '../Deletion';
import {BaseModel} from '../BaseModel';

export class WorkspaceDefinition extends BaseModel {
    type?: string;
    deployment_package_id?: number;
    deployment_package?: DeploymentPackage;
    name_readable?: string;
    name_system?: string;
    namespace?: string;
    email_service_id?: number;
    email_service?: EmailService;
    domain_id?: number;
    domain?: Domain;
    subdomain?: string;
    aliases?: string;
    database_service_id?: number;
    database_service?: DatabaseService;
    deployments?: Deployment[];
    labels?: Label[];
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
            delete this.type;
            delete this.deployment_package_id;
            delete this.deployment_package;
            delete this.name_readable;
            delete this.name_system;
            delete this.namespace;
            delete this.email_service_id;
            delete this.email_service;
            delete this.domain_id;
            delete this.domain;
            delete this.subdomain;
            delete this.aliases;
            delete this.database_service_id;
            delete this.database_service;
            delete this.deployments;
            delete this.labels;
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
        if (data.type != null) {
            this.type = data.type;
        }
        if (data.deployment_package_id != null) {
            this.deployment_package_id = data.deployment_package_id;
        }
        if (data.deployment_package != null) {
            this.deployment_package = new DeploymentPackage(data.deployment_package);
        }
        if (data.name_readable != null) {
            this.name_readable = data.name_readable;
        }
        if (data.name_system != null) {
            this.name_system = data.name_system;
        }
        if (data.namespace != null) {
            this.namespace = data.namespace;
        }
        if (data.email_service_id != null) {
            this.email_service_id = data.email_service_id;
        }
        if (data.email_service != null) {
            this.email_service = new EmailService(data.email_service);
        }
        if (data.domain_id != null) {
            this.domain_id = data.domain_id;
        }
        if (data.domain != null) {
            this.domain = new Domain(data.domain);
        }
        if (data.subdomain != null) {
            this.subdomain = data.subdomain;
        }
        if (data.aliases != null) {
            this.aliases = data.aliases;
        }
        if (data.database_service_id != null) {
            this.database_service_id = data.database_service_id;
        }
        if (data.database_service != null) {
            this.database_service = new DatabaseService(data.database_service);
        }
        if (data.deployments != null) {
            this.deployments = data.deployments.map((i: any) => new Deployment(i));
        }
        if (data.labels != null) {
            this.labels = data.labels.map((i: any) => new Label(i));
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
