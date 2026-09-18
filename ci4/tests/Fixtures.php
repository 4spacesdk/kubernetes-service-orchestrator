<?php namespace App;

use OrmExtension\Extensions\Entity;
use App\Entities\Deployment;
use App\Entities\DeploymentPackage;
use App\Entities\DeploymentPackageDeploymentSpecification;
use App\Entities\DeploymentSpecification;
use App\Entities\ContainerImage;
use App\Entities\ContainerRegistry;
use App\Entities\DatabaseService;
use App\Entities\EmailService;
use App\Entities\DeploymentSpecificationHttpProxyRoute;
use App\Entities\DeploymentSpecificationClusterRoleRule;
use App\Entities\DeploymentSpecificationDeploymentAnnotation;
use App\Entities\DeploymentSpecificationEnvironmentVariable;
use App\Entities\DeploymentSpecificationIngress;
use App\Entities\DeploymentSpecificationIngressRulePath;
use App\Entities\DeploymentSpecificationRoleRule;
use App\Entities\DeploymentSpecificationServiceAnnotation;
use App\Entities\DeploymentSpecificationServicePort;
use App\Entities\DeploymentSpecificationInitContainer;
use App\Entities\DeploymentSpecificationVolume;
use App\Entities\DeploymentCronJob;
use App\Entities\DeploymentSpecificationCronJob;
use App\Entities\DeploymentVolume;
use App\Entities\InitContainer;
use App\Entities\KNativeMinScaleSchedule;
use App\Entities\PodioFieldReference;
use App\Entities\PodioIntegration;
use App\Entities\PostUpdateAction;
use App\Entities\PostUpdateActionCondition;
use App\Entities\K8sCronJob;
use App\Entities\EnvironmentVariable;
use App\Entities\Domain;
use App\Entities\Gateway;
use App\Entities\GatewayAddress;
use App\Entities\User;
use App\Entities\System;
use App\Entities\Workspace;

/**
 * Rows for tests that need a real schema to read from.
 *
 * Every helper takes an overrides array rather than positional arguments: a test states
 * only the fields it depends on, and the rest are whatever the helper considers ordinary.
 * Adding a field to an entity therefore never touches an existing call site.
 *
 *     $workspace = Fixtures::workspace(['status' => \WorkspaceStatusTypes::Active]);
 *     $deployment = Fixtures::deployment(['workspace_id' => $workspace->id]);
 *
 * Defaults cover only what a test is likely to care about. Columns the schema gives a
 * default are left alone, so this list does not have to track every NOT NULL column.
 *
 * Everything is written inside the test's transaction and disappears when the test ends.
 */
class Fixtures {

    /**
     * Build an entity from defaults plus whatever the test cares about, and save it.
     *
     * @template T of Entity
     * @param class-string<T> $class
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $overrides
     * @return T
     */
    private static function make(string $class, array $defaults, array $overrides): Entity {
        $entity = new $class();
        foreach (array_merge($defaults, $overrides) as $field => $value) {
            $entity->{$field} = $value;
        }
        $entity->save();

        // Read it back rather than handing out the object that was just written. An entity
        // built in code carries only the fields that were assigned, while production code
        // always works with rows loaded from the database, where every column has a value.
        // Without this, tests reach null attributes that never occur in production.
        $saved = new $class();
        $saved->find($entity->id);

        return $saved;
    }

    /**
     * A user to sign in as.
     *
     * The password is bcrypt, as the application writes it, but at the cheapest cost
     * bcrypt allows. At the default cost a hash takes 173 ms and a verify another 160,
     * which is the whole point of bcrypt and entirely wasted here - it made the sign-in
     * tests the slowest in the suite by an order of magnitude. `password_verify()` reads
     * the cost from the hash, so the application's own code needs no knowledge of this.
     *
     * @param array<string, mixed> $overrides
     */
    public static function user(array $overrides = []): User {
        $password = $overrides['password'] ?? 'correct horse battery staple';
        unset($overrides['password']);

        return self::make(User::class, [
            'username' => 'tester',
            'first_name' => 'Test',
            'last_name' => 'User',
            'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]),
            'renew_password' => false,
            'mfa_secret_hash' => '',
        ], $overrides);
    }

    /**
     * The System row the application reads its own configuration from.
     *
     * Row 1, always - that is the one `System::Get()` looks up, and the schema setup seeds
     * it so the test database matches every real installation. `DatabaseTestCase` fails the
     * run if it is missing, so there is nothing to create here.
     *
     * The changes are rolled back with the test like any other write.
     *
     * @param array<string, mixed> $overrides
     */
    public static function system(array $overrides = []): System {
        $system = new System();
        $system->find(1);

        foreach ($overrides as $field => $value) {
            $system->{$field} = $value;
        }
        $system->save();

        return $system;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function gateway(array $overrides = []): Gateway {
        return self::make(Gateway::class, [
            'name' => 'test-gateway',
            'namespace' => 'test',
            'gateway_class_name' => 'gke-l7-regional-external-managed',
        ], $overrides);
    }

    /**
     * An address a gateway is pinned to, rather than one the cloud hands out. `type` is a
     * Gateway API enum: IPAddress or Hostname.
     *
     * @param array<string, mixed> $overrides
     */
    public static function gatewayAddress(array $overrides = []): GatewayAddress {
        return self::make(GatewayAddress::class, [
            'type' => 'IPAddress',
            'value' => '10.0.0.1',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function containerImage(array $overrides = []): ContainerImage {
        return self::make(ContainerImage::class, [
            'name' => 'test-image',
            'url' => 'registry.example.org/test/app',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    /**
     * A connection with no network behind it. Harbor, because its names are the easiest to
     * read in a test; the provider decides nothing until a client is asked for.
     *
     * @param array<string, mixed> $overrides
     */
    public static function containerRegistry(array $overrides = []): ContainerRegistry {
        return self::make(ContainerRegistry::class, [
            'name' => 'test-registry',
            'provider' => \ContainerRegistries::Harbor,
            'harbor_url' => 'registry.example.org',
            'harbor_username' => 'robot',
            'harbor_password' => 'secret',
        ], $overrides);
    }

    public static function databaseService(array $overrides = []): DatabaseService {
        return self::make(DatabaseService::class, [
            'name' => 'test-database',
            'driver' => \DatabaseDrivers::MySQL,
            'host' => 'db.test',
            'port' => 3306,
            'user' => 'root',
            'pass' => 'secret',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function emailService(array $overrides = []): EmailService {
        return self::make(EmailService::class, [
            'name' => 'test-mail',
            'host' => 'smtp.test',
            'port' => 587,
            'user' => 'mailer',
            'pass' => 'mail-secret',
            'from' => 'noreply@test.example.org',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function domain(array $overrides = []): Domain {
        return self::make(Domain::class, [
            'name' => 'test.example.org',
            'certificate_name' => 'test-cert',
            'certificate_namespace' => 'default',
            'issuer_ref_name' => 'test-issuer',
            'https_redirect' => false,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function workspace(array $overrides = []): Workspace {
        return self::make(Workspace::class, [
            'name_readable' => 'test',
            'name_system' => 'test',
            'namespace' => 'test',
            'subdomain' => 'tenant',
            'status' => \WorkspaceStatusTypes::Draft,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function deploymentSpecification(array $overrides = []): DeploymentSpecification {
        return self::make(DeploymentSpecification::class, [
            'name' => 'test-spec',
            'workload_type' => \WorkloadTypes::Deployment,
            'network_type' => \NetworkTypes::GatewayApi,
            'domain_tls' => 'https',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function deploymentPackage(array $overrides = []): DeploymentPackage {
        return self::make(DeploymentPackage::class, [
            'name' => 'test-package',
            'namespace' => 'test',
        ], $overrides);
    }

    /**
     * A specification inside a package, with the defaults a new deployment inherits.
     *
     * `default_version` is always set: without it `createDeploymentFromPackage()` asks the
     * container registry for the newest tag, which is a network call.
     *
     * @param array<string, mixed> $overrides
     */
    public static function packageSpecification(array $overrides = []): DeploymentPackageDeploymentSpecification {
        return self::make(DeploymentPackageDeploymentSpecification::class, [
            'default_version' => '1.0.0',
            'default_environment' => 'production',
            'default_image_pull_policy' => \ImagePullPolicies::IfNotPresent,
            'default_replicas' => 1,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function deployment(array $overrides = []): Deployment {
        return self::make(Deployment::class, [
            'name' => 'test-deployment',
            'namespace' => 'test',
            'status' => \DeploymentStatusTypes::Draft,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function httpProxyRoute(array $overrides = []): DeploymentSpecificationHttpProxyRoute {
        // Empty, as the UI leaves it for every network type but Contour - which offers
        // only `tls`, `h2` and `h2c`. `http` was the default here for a while and is a
        // value the product cannot produce: Contour's schema refuses it outright.
        return self::make(DeploymentSpecificationHttpProxyRoute::class, [
            'path' => '/',
            'port' => 80,
            'protocol' => '',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function servicePort(array $overrides = []): DeploymentSpecificationServicePort {
        return self::make(DeploymentSpecificationServicePort::class, [
            'name' => 'port-80',
            'protocol' => 'TCP',
            'port' => 80,
            'target_port' => 80,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function serviceAnnotation(array $overrides = []): DeploymentSpecificationServiceAnnotation {
        return self::make(DeploymentSpecificationServiceAnnotation::class, [
            'name' => 'example.org/annotation',
            'value' => 'value',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function ingress(array $overrides = []): DeploymentSpecificationIngress {
        return self::make(DeploymentSpecificationIngress::class, [
            'ingress_class' => 'nginx',
            'proxy_body_size' => 8,
            'proxy_connect_timeout' => 60,
            'proxy_read_timeout' => 60,
            'proxy_send_timeout' => 60,
            'ssl_redirect' => true,
            'enable_tls' => true,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function ingressRulePath(array $overrides = []): DeploymentSpecificationIngressRulePath {
        return self::make(DeploymentSpecificationIngressRulePath::class, [
            'path' => '/',
            'path_type' => 'Prefix',
            'backend_service_port_name' => 'http',
        ], $overrides);
    }

    /**
     * A volume attached to one deployment. NFS by default, because that is the type that
     * needs no driver installed in the cluster.
     *
     * @param array<string, mixed> $overrides
     */
    public static function deploymentVolume(array $overrides = []): DeploymentVolume {
        return self::make(DeploymentVolume::class, self::volumeDefaults(), $overrides);
    }

    /**
     * A volume every deployment of a specification gets.
     *
     * @param array<string, mixed> $overrides
     */
    public static function specificationVolume(array $overrides = []): DeploymentSpecificationVolume {
        return self::make(DeploymentSpecificationVolume::class, self::volumeDefaults(), $overrides);
    }

    /**
     * Both volume entities carry the same columns, so they share their defaults.
     *
     * @return array<string, mixed>
     */
    private static function volumeDefaults(): array {
        return [
            'type' => 'nfs',
            'mount_path' => '/data',
            'sub_path' => '',
            'capacity' => 10,
            'volume_mode' => 'Filesystem',
            'reclaim_policy' => 'Retain',
            'nfs_server' => '10.0.0.1',
            'nfs_path' => '/exports/test',
            'storage_class' => 'nfs',
            'csi_driver' => '',
            'csi_volume_handle' => '',
        ];
    }

    /**
     * One rule in the Role a specification grants its deployments. `verbs` is a
     * comma-separated string, which the step splits.
     *
     * @param array<string, mixed> $overrides
     */
    public static function roleRule(array $overrides = []): DeploymentSpecificationRoleRule {
        return self::make(DeploymentSpecificationRoleRule::class, self::roleRuleDefaults(), $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function clusterRoleRule(array $overrides = []): DeploymentSpecificationClusterRoleRule {
        return self::make(DeploymentSpecificationClusterRoleRule::class, self::roleRuleDefaults(), $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private static function roleRuleDefaults(): array {
        return [
            'api_group' => '',
            'resource' => 'pods',
            'verbs' => 'get,list',
        ];
    }

    /**
     * An annotation put on the workload a specification generates. `level` decides whether
     * it lands on the Deployment itself or on its pods.
     *
     * @param array<string, mixed> $overrides
     */
    public static function deploymentAnnotation(array $overrides = []): DeploymentSpecificationDeploymentAnnotation {
        return self::make(DeploymentSpecificationDeploymentAnnotation::class, [
            'level' => \DeploymentAnnotationLevels::Deployment,
            'name' => 'example.org/annotation',
            'value' => 'value',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function podioIntegration(array $overrides = []): PodioIntegration {
        return self::make(PodioIntegration::class, [
            'name' => 'test-podio',
            'client_id' => 'client',
            'client_secret' => 'secret',
            'app_id' => '1234',
            'app_token' => 'token',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function podioFieldReference(array $overrides = []): PodioFieldReference {
        return self::make(PodioFieldReference::class, [
            'field_id' => '99',
        ], $overrides);
    }

    /**
     * An action that runs after a deployment is updated.
     *
     * @param array<string, mixed> $overrides
     */
    public static function postUpdateAction(array $overrides = []): PostUpdateAction {
        return self::make(PostUpdateAction::class, [
            'name' => 'test-action',
            'type' => \PostUpdateActionTypes::Podio_AddComment,
            'podio_add_comment_value' => 'Deployed ${commit.url}',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function postUpdateActionCondition(array $overrides = []): PostUpdateActionCondition {
        return self::make(PostUpdateActionCondition::class, [
            'type' => \PostUpdateActionConditionTypes::PodioFieldEquals,
            'value' => 'ready',
        ], $overrides);
    }

    /**
     * One row in a deployment's warm-pod schedule. Attaching it to a deployment is a
     * junction row the caller writes; the entity on its own belongs to nothing.
     *
     * @param array<string, mixed> $overrides
     */
    public static function minScaleSchedule(array $overrides = []): KNativeMinScaleSchedule {
        return self::make(KNativeMinScaleSchedule::class, [
            'min_scale' => 1,
            'cron_expression' => '0 7 * * 1-5',
            'timezone' => 'UTC',
            'description' => 'test schedule',
            'priority' => 0,
        ], $overrides);
    }

    /**
     * A cron job definition. It is not attached to anything on its own - a junction row
     * hangs it off a specification or off one deployment.
     *
     * @param array<string, mixed> $overrides
     */
    public static function cronJob(array $overrides = []): K8sCronJob {
        return self::make(K8sCronJob::class, [
            'name' => 'cleanup',
            'schedule' => '0 3 * * *',
            'concurrency_policy' => 'Forbid',
            'restart_policy' => 'OnFailure',
            'container_image_tag_policy' => \ContainerImageTagPolicies::MatchDeployment,
            'container_image_pull_policy' => \ImagePullPolicies::IfNotPresent,
            'command' => '',
            'args' => '',
            'include_deployment_environment_variables' => false,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function specificationCronJob(array $overrides = []): DeploymentSpecificationCronJob {
        return self::make(DeploymentSpecificationCronJob::class, [
            'position' => 0,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function deploymentCronJob(array $overrides = []): DeploymentCronJob {
        return self::make(DeploymentCronJob::class, [
            'position' => 0,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function initContainer(array $overrides = []): InitContainer {
        return self::make(InitContainer::class, [
            'name' => 'wait-for-db',
            'container_image_tag_policy' => \ContainerImageTagPolicies::MatchDeployment,
            'container_image_pull_policy' => \ImagePullPolicies::IfNotPresent,
            'command' => '',
            'args' => '',
            'include_deployment_environment_variables' => false,
            'include_volumes' => false,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function specificationInitContainer(array $overrides = []): DeploymentSpecificationInitContainer {
        return self::make(DeploymentSpecificationInitContainer::class, [
            'position' => 0,
            'include_in_migration_job' => false,
        ], $overrides);
    }

    /**
     * An environment variable inherited by every deployment of a specification.
     *
     * @param array<string, mixed> $overrides
     */
    public static function specificationEnvironmentVariable(array $overrides = []): DeploymentSpecificationEnvironmentVariable {
        return self::make(DeploymentSpecificationEnvironmentVariable::class, [
            'name' => 'TEST',
            'value' => 'value',
        ], $overrides);
    }

    /**
     * An environment variable set on one deployment, which wins over the specification's.
     *
     * @param array<string, mixed> $overrides
     */
    public static function deploymentEnvironmentVariable(array $overrides = []): EnvironmentVariable {
        return self::make(EnvironmentVariable::class, [
            'name' => 'TEST',
            'value' => 'value',
        ], $overrides);
    }

    // <editor-fold desc="Scenarios">

    /**
     * A workspace on a gateway, with a domain in between. The arrangement several
     * deployment steps require before they generate anything at all.
     *
     * @param array<string, mixed> $domain overrides for the domain
     * @param array<string, mixed> $workspace overrides for the workspace
     * @param array<string, mixed> $gateway overrides for the gateway
     */
    public static function workspaceOnGateway(array $domain = [], array $workspace = [], array $gateway = []): Workspace {
        $gatewayRow = self::gateway($gateway);
        $domainRow = self::domain(array_merge(['gateway_id' => $gatewayRow->id], $domain));

        return self::workspace(array_merge(['domain_id' => $domainRow->id], $workspace));
    }

    /**
     * A deployment behind a GKE gateway, which is what the policy steps look for.
     *
     * @param array<string, mixed> $specification overrides for the deployment specification
     */
    public static function deploymentBehindGkeGateway(array $specification = []): Deployment {
        $workspace = self::workspaceOnGateway();
        $spec = self::deploymentSpecification($specification);

        return self::deployment([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $spec->id,
        ]);
    }

    /**
     * A deployment with everything the Deployment manifest is built from: a workspace, a
     * specification and the container image the specification points at.
     *
     * @param array<string, mixed> $deployment overrides for the deployment
     * @param array<string, mixed> $specification overrides for the specification
     * @param array<string, mixed> $image overrides for the container image
     */
    public static function deployableDeployment(
        array $deployment = [],
        array $specification = [],
        array $image = []
    ): Deployment {
        $workspace = self::workspaceOnGateway();
        $containerImage = self::containerImage($image);
        $spec = self::deploymentSpecification(array_merge(
            ['container_image_id' => $containerImage->id],
            $specification
        ));

        return self::deployment(array_merge([
            'workspace_id' => $workspace->id,
            'deployment_specification_id' => $spec->id,
            'version' => '1.2.3',
            'environment' => 'production',
            'image_pull_policy' => \ImagePullPolicies::IfNotPresent,
            'replicas' => 1,
        ], $deployment));
    }

    /**
     * A deployment wired up for auto update, with its workspace.
     *
     * `workspace_status` sets the status of the workspace that is created alongside;
     * everything else is applied to the deployment.
     *
     * @param array<string, mixed> $overrides
     */
    public static function autoUpdatableDeployment(array $overrides = []): Deployment {
        $workspaceStatus = $overrides['workspace_status'] ?? \WorkspaceStatusTypes::Active;
        unset($overrides['workspace_status']);

        $workspace = self::workspace(['status' => $workspaceStatus]);

        return self::deployment(array_merge([
            'workspace_id' => $workspace->id,
            'status' => \DeploymentStatusTypes::Active,
            'version' => 'old',
            'auto_update_enabled' => true,
            'auto_update_require_approval' => false,
        ], $overrides));
    }

    // </editor-fold>

}
