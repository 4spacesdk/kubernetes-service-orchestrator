/**
 * Created by ModelParser
 */
import {ContainerRegistry} from '../ContainerRegistry';
import {GithubIntegration} from '../GithubIntegration';
import {User} from '../User';
import {Deletion} from '../Deletion';
import {BaseModel} from '../BaseModel';

export class ContainerImageDefinition extends BaseModel {
    name?: string;
    url?: string;
    pull_secret?: string;
    default_tag?: string;
    default_image_pull_policy?: string;
    container_registry_id?: number;
    container_registry?: ContainerRegistry;
    security_context_fs_group?: string;
    security_context_run_as_user?: string;
    security_context_run_as_group?: string;
    security_context_allow_privilege_escalation?: boolean;
    security_context_read_only_root_filesystem?: boolean;
    version_control_enabled?: boolean;
    version_control_provider?: string;
    version_control_repository_name?: string;
    github_integration_id?: number;
    github_integration?: GithubIntegration;
    commit_identification_enabled?: boolean;
    commit_identification_method?: string;
    commit_identification_environment_variable_name?: string;
    running_deployment_ids?: number[];
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
            delete this.url;
            delete this.pull_secret;
            delete this.default_tag;
            delete this.default_image_pull_policy;
            delete this.container_registry_id;
            delete this.container_registry;
            delete this.security_context_fs_group;
            delete this.security_context_run_as_user;
            delete this.security_context_run_as_group;
            delete this.security_context_allow_privilege_escalation;
            delete this.security_context_read_only_root_filesystem;
            delete this.version_control_enabled;
            delete this.version_control_provider;
            delete this.version_control_repository_name;
            delete this.github_integration_id;
            delete this.github_integration;
            delete this.commit_identification_enabled;
            delete this.commit_identification_method;
            delete this.commit_identification_environment_variable_name;
            delete this.running_deployment_ids;
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
        if (data.url != null) {
            this.url = data.url;
        }
        if (data.pull_secret != null) {
            this.pull_secret = data.pull_secret;
        }
        if (data.default_tag != null) {
            this.default_tag = data.default_tag;
        }
        if (data.default_image_pull_policy != null) {
            this.default_image_pull_policy = data.default_image_pull_policy;
        }
        if (data.container_registry_id != null) {
            this.container_registry_id = data.container_registry_id;
        }
        if (data.container_registry != null) {
            this.container_registry = new ContainerRegistry(data.container_registry);
        }
        if (data.security_context_fs_group != null) {
            this.security_context_fs_group = data.security_context_fs_group;
        }
        if (data.security_context_run_as_user != null) {
            this.security_context_run_as_user = data.security_context_run_as_user;
        }
        if (data.security_context_run_as_group != null) {
            this.security_context_run_as_group = data.security_context_run_as_group;
        }
        if (data.security_context_allow_privilege_escalation != null) {
            this.security_context_allow_privilege_escalation = data.security_context_allow_privilege_escalation;
        }
        if (data.security_context_read_only_root_filesystem != null) {
            this.security_context_read_only_root_filesystem = data.security_context_read_only_root_filesystem;
        }
        if (data.version_control_enabled != null) {
            this.version_control_enabled = data.version_control_enabled;
        }
        if (data.version_control_provider != null) {
            this.version_control_provider = data.version_control_provider;
        }
        if (data.version_control_repository_name != null) {
            this.version_control_repository_name = data.version_control_repository_name;
        }
        if (data.github_integration_id != null) {
            this.github_integration_id = data.github_integration_id;
        }
        if (data.github_integration != null) {
            this.github_integration = new GithubIntegration(data.github_integration);
        }
        if (data.commit_identification_enabled != null) {
            this.commit_identification_enabled = data.commit_identification_enabled;
        }
        if (data.commit_identification_method != null) {
            this.commit_identification_method = data.commit_identification_method;
        }
        if (data.commit_identification_environment_variable_name != null) {
            this.commit_identification_environment_variable_name = data.commit_identification_environment_variable_name;
        }
        if (data.running_deployment_ids != null) {
            this.running_deployment_ids = data.running_deployment_ids;
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
