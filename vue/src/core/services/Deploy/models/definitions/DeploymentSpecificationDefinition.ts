/**
 * Created by ModelParser
 */
import {ContainerImage} from '../ContainerImage';
import {Deployment} from '../Deployment';
import {DeploymentSpecificationPostCommand} from '../DeploymentSpecificationPostCommand';
import {DeploymentSpecificationEnvironmentVariable} from '../DeploymentSpecificationEnvironmentVariable';
import {DeploymentSpecificationServicePort} from '../DeploymentSpecificationServicePort';
import {DeploymentSpecificationIngress} from '../DeploymentSpecificationIngress';
import {DeploymentSpecificationClusterRoleRule} from '../DeploymentSpecificationClusterRoleRule';
import {DeploymentSpecificationRoleRule} from '../DeploymentSpecificationRoleRule';
import {DeploymentSpecificationServiceAnnotation} from '../DeploymentSpecificationServiceAnnotation';
import {DeploymentSpecificationDeploymentAnnotation} from '../DeploymentSpecificationDeploymentAnnotation';
import {DeploymentSpecificationIngressAnnotation} from '../DeploymentSpecificationIngressAnnotation';
import {DeploymentSpecificationQuickCommand} from '../DeploymentSpecificationQuickCommand';
import {DeploymentSpecificationInitContainer} from '../DeploymentSpecificationInitContainer';
import {DeploymentSpecificationPostUpdateAction} from '../DeploymentSpecificationPostUpdateAction';
import {DeploymentSpecificationCronJob} from '../DeploymentSpecificationCronJob';
import {DeploymentSpecificationHttpProxyRoute} from '../DeploymentSpecificationHttpProxyRoute';
import {Label} from '../Label';
import {DeploymentStep} from '../DeploymentStep';
import {User} from '../User';
import {Deletion} from '../Deletion';
import {BaseModel} from '../BaseModel';

export class DeploymentSpecificationDefinition extends BaseModel {
    name?: string;
    container_image_id?: number;
    container_image?: ContainerImage;
    workload_type?: string;
    enable_database?: boolean;
    enable_cronjob?: boolean;
    enable_external_access?: boolean;
    enable_internal_access?: boolean;
    enable_rbac?: boolean;
    enable_volumes?: boolean;
    network_type?: string;
    domain_tls?: string;
    domain_prefix?: string;
    domain_suffix?: string;
    domain_aliases?: string;
    database_migration_container_image_id?: number;
    database_migration_container_image?: ContainerImage;
    database_migration_container_image_tag_policy?: string;
    database_migration_container_image_tag_value?: string;
    database_migration_command?: string;
    database_migration_verification_type?: string;
    database_migration_verification_value?: string;
    custom_resource?: string;
    deployments?: Deployment[];
    deployment_specification_post_commands?: DeploymentSpecificationPostCommand[];
    deployment_specification_environment_variables?: DeploymentSpecificationEnvironmentVariable[];
    deployment_specification_service_ports?: DeploymentSpecificationServicePort[];
    deployment_specification_ingresses?: DeploymentSpecificationIngress[];
    deployment_specification_cluster_role_rules?: DeploymentSpecificationClusterRoleRule[];
    deployment_specification_role_rules?: DeploymentSpecificationRoleRule[];
    deployment_specification_service_annotations?: DeploymentSpecificationServiceAnnotation[];
    deployment_specification_deployment_annotations?: DeploymentSpecificationDeploymentAnnotation[];
    deployment_specification_ingress_annotations?: DeploymentSpecificationIngressAnnotation[];
    deployment_specification_quick_commands?: DeploymentSpecificationQuickCommand[];
    deployment_specification_init_containers?: DeploymentSpecificationInitContainer[];
    deployment_specification_post_update_actions?: DeploymentSpecificationPostUpdateAction[];
    deployment_specification_cron_jobs?: DeploymentSpecificationCronJob[];
    deployment_specification_http_proxy_routes?: DeploymentSpecificationHttpProxyRoute[];
    labels?: Label[];
    deploymentSteps?: DeploymentStep[];
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
            delete this.container_image_id;
            delete this.container_image;
            delete this.workload_type;
            delete this.enable_database;
            delete this.enable_cronjob;
            delete this.enable_external_access;
            delete this.enable_internal_access;
            delete this.enable_rbac;
            delete this.enable_volumes;
            delete this.network_type;
            delete this.domain_tls;
            delete this.domain_prefix;
            delete this.domain_suffix;
            delete this.domain_aliases;
            delete this.database_migration_container_image_id;
            delete this.database_migration_container_image;
            delete this.database_migration_container_image_tag_policy;
            delete this.database_migration_container_image_tag_value;
            delete this.database_migration_command;
            delete this.database_migration_verification_type;
            delete this.database_migration_verification_value;
            delete this.custom_resource;
            delete this.deployments;
            delete this.deployment_specification_post_commands;
            delete this.deployment_specification_environment_variables;
            delete this.deployment_specification_service_ports;
            delete this.deployment_specification_ingresses;
            delete this.deployment_specification_cluster_role_rules;
            delete this.deployment_specification_role_rules;
            delete this.deployment_specification_service_annotations;
            delete this.deployment_specification_deployment_annotations;
            delete this.deployment_specification_ingress_annotations;
            delete this.deployment_specification_quick_commands;
            delete this.deployment_specification_init_containers;
            delete this.deployment_specification_post_update_actions;
            delete this.deployment_specification_cron_jobs;
            delete this.deployment_specification_http_proxy_routes;
            delete this.labels;
            delete this.deploymentSteps;
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
        if (data.container_image_id != null) {
            this.container_image_id = data.container_image_id;
        }
        if (data.container_image != null) {
            this.container_image = new ContainerImage(data.container_image);
        }
        if (data.workload_type != null) {
            this.workload_type = data.workload_type;
        }
        if (data.enable_database != null) {
            this.enable_database = data.enable_database;
        }
        if (data.enable_cronjob != null) {
            this.enable_cronjob = data.enable_cronjob;
        }
        if (data.enable_external_access != null) {
            this.enable_external_access = data.enable_external_access;
        }
        if (data.enable_internal_access != null) {
            this.enable_internal_access = data.enable_internal_access;
        }
        if (data.enable_rbac != null) {
            this.enable_rbac = data.enable_rbac;
        }
        if (data.enable_volumes != null) {
            this.enable_volumes = data.enable_volumes;
        }
        if (data.network_type != null) {
            this.network_type = data.network_type;
        }
        if (data.domain_tls != null) {
            this.domain_tls = data.domain_tls;
        }
        if (data.domain_prefix != null) {
            this.domain_prefix = data.domain_prefix;
        }
        if (data.domain_suffix != null) {
            this.domain_suffix = data.domain_suffix;
        }
        if (data.domain_aliases != null) {
            this.domain_aliases = data.domain_aliases;
        }
        if (data.database_migration_container_image_id != null) {
            this.database_migration_container_image_id = data.database_migration_container_image_id;
        }
        if (data.database_migration_container_image != null) {
            this.database_migration_container_image = new ContainerImage(data.database_migration_container_image);
        }
        if (data.database_migration_container_image_tag_policy != null) {
            this.database_migration_container_image_tag_policy = data.database_migration_container_image_tag_policy;
        }
        if (data.database_migration_container_image_tag_value != null) {
            this.database_migration_container_image_tag_value = data.database_migration_container_image_tag_value;
        }
        if (data.database_migration_command != null) {
            this.database_migration_command = data.database_migration_command;
        }
        if (data.database_migration_verification_type != null) {
            this.database_migration_verification_type = data.database_migration_verification_type;
        }
        if (data.database_migration_verification_value != null) {
            this.database_migration_verification_value = data.database_migration_verification_value;
        }
        if (data.custom_resource != null) {
            this.custom_resource = data.custom_resource;
        }
        if (data.deployments != null) {
            this.deployments = data.deployments.map((i: any) => new Deployment(i));
        }
        if (data.deployment_specification_post_commands != null) {
            this.deployment_specification_post_commands = data.deployment_specification_post_commands.map((i: any) => new DeploymentSpecificationPostCommand(i));
        }
        if (data.deployment_specification_environment_variables != null) {
            this.deployment_specification_environment_variables = data.deployment_specification_environment_variables.map((i: any) => new DeploymentSpecificationEnvironmentVariable(i));
        }
        if (data.deployment_specification_service_ports != null) {
            this.deployment_specification_service_ports = data.deployment_specification_service_ports.map((i: any) => new DeploymentSpecificationServicePort(i));
        }
        if (data.deployment_specification_ingresses != null) {
            this.deployment_specification_ingresses = data.deployment_specification_ingresses.map((i: any) => new DeploymentSpecificationIngress(i));
        }
        if (data.deployment_specification_cluster_role_rules != null) {
            this.deployment_specification_cluster_role_rules = data.deployment_specification_cluster_role_rules.map((i: any) => new DeploymentSpecificationClusterRoleRule(i));
        }
        if (data.deployment_specification_role_rules != null) {
            this.deployment_specification_role_rules = data.deployment_specification_role_rules.map((i: any) => new DeploymentSpecificationRoleRule(i));
        }
        if (data.deployment_specification_service_annotations != null) {
            this.deployment_specification_service_annotations = data.deployment_specification_service_annotations.map((i: any) => new DeploymentSpecificationServiceAnnotation(i));
        }
        if (data.deployment_specification_deployment_annotations != null) {
            this.deployment_specification_deployment_annotations = data.deployment_specification_deployment_annotations.map((i: any) => new DeploymentSpecificationDeploymentAnnotation(i));
        }
        if (data.deployment_specification_ingress_annotations != null) {
            this.deployment_specification_ingress_annotations = data.deployment_specification_ingress_annotations.map((i: any) => new DeploymentSpecificationIngressAnnotation(i));
        }
        if (data.deployment_specification_quick_commands != null) {
            this.deployment_specification_quick_commands = data.deployment_specification_quick_commands.map((i: any) => new DeploymentSpecificationQuickCommand(i));
        }
        if (data.deployment_specification_init_containers != null) {
            this.deployment_specification_init_containers = data.deployment_specification_init_containers.map((i: any) => new DeploymentSpecificationInitContainer(i));
        }
        if (data.deployment_specification_post_update_actions != null) {
            this.deployment_specification_post_update_actions = data.deployment_specification_post_update_actions.map((i: any) => new DeploymentSpecificationPostUpdateAction(i));
        }
        if (data.deployment_specification_cron_jobs != null) {
            this.deployment_specification_cron_jobs = data.deployment_specification_cron_jobs.map((i: any) => new DeploymentSpecificationCronJob(i));
        }
        if (data.deployment_specification_http_proxy_routes != null) {
            this.deployment_specification_http_proxy_routes = data.deployment_specification_http_proxy_routes.map((i: any) => new DeploymentSpecificationHttpProxyRoute(i));
        }
        if (data.labels != null) {
            this.labels = data.labels.map((i: any) => new Label(i));
        }
        if (data.deploymentSteps != null) {
            this.deploymentSteps = data.deploymentSteps.map((i: any) => new DeploymentStep(i));
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
