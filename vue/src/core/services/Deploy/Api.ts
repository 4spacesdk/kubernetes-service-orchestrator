import {BaseApi} from "./BaseApi";
import {ContainerImage} from "./models";
import {DatabaseService} from "./models";
import {DeploymentPackage} from "./models";
import {DeploymentSpecification} from "./models";
import {Deployment} from "./models";
import {MigrationJob} from "./models";
import {Domain} from "./models";
import {EmailService} from "./models";
import {KeelHookQueueItem} from "./models";
import {OAuthClient} from "./models";
import {User} from "./models";
import {Webhook} from "./models";
import {WebhookDelivery} from "./models";
import {Workspace} from "./models";

export interface ClusterRoleRule {
    apiGroup?: string;
    resource?: string;
    verbs?: string;
}

export interface ClusterRoleRuleList {
    values?: ClusterRoleRule[];
}

export interface DeploymentPackageDeploymentSpecification {
    deploymentSpecification?: DeploymentSpecification;
    defaultEnablePodioNotification?: boolean;
    defaultVersion?: string;
    defaultKeelPolicy?: string;
    defaultKeelAutoUpdate?: boolean;
    defaultEnvironment?: string;
    defaultCpuRequest?: number;
    defaultCpuLimit?: number;
    defaultMemoryRequest?: number;
    defaultMemoryLimit?: number;
    defaultReplicas?: number;
}

export interface DeploymentPackageDeploymentSpecificationList {
    values?: DeploymentPackageDeploymentSpecification[];
}

export interface DeploymentSpecGetResponse {
    identifier?: string;
    name?: string;
    image?: string;
    tenantType?: string;
    requireDatabase?: boolean;
    requireCronJob?: boolean;
    requireIngress?: boolean;
    deploymentSteps?: DeploymentStep[];
}

export interface DeploymentSpecificationTagsGetResponse {
    tags?: string[];
}

export interface DeploymentStep {
    identifier?: string;
    name?: string;
    hasPreviewCommand?: boolean;
    hasStatusCommand?: boolean;
    hasDeployCommand?: boolean;
    hasTerminateCommand?: boolean;
    hasKubernetesEvents?: boolean;
    hasKubernetesStatus?: boolean;
}

export interface DeploymentVolume {
    mount_path?: string;
    sub_path?: string;
    capacity?: number;
    volume_mode?: string;
    reclaim_policy?: string;
    nfs_server?: string;
    nfs_path?: string;
}

export interface DeploymentVolumeList {
    values?: DeploymentVolume[];
}

export interface DomainsGetCertificateEventsResponse {
    type?: string;
    reason?: string;
    age?: string;
    from?: string;
    message?: string;
}

export interface DomainsGetCertificateStatusResponse {
    conditions?: DomainsGetCertificateStatusResponse_Condition[];
    notAfter?: string;
    notBefore?: string;
    renewalTime?: string;
}

export interface DomainsGetCertificateStatusResponse_Condition {
    type?: string;
    status?: string;
    reason?: string;
    lastTransitionTime?: string;
    message?: string;
}

export interface EnvironmentVariable {
    name?: string;
    value?: string;
}

export interface EnvironmentVariableList {
    values?: EnvironmentVariable[];
}

export interface EnvironmentsGetResponse {
    name?: string;
}

export interface Ingress {
    ingressClass?: string;
    proxyBodySize?: number;
    proxyConnectTimeout?: number;
    proxyReadTimeout?: number;
    proxySendTimeout?: number;
    sslRedirect?: boolean;
    enableTls?: boolean;
    paths?: IngressRulePath[];
}

export interface IngressList {
    values?: Ingress[];
}

export interface IngressRulePath {
    path?: string;
    pathType?: string;
    backendServicePortName?: string;
}

export interface KeelPoliciesGetResponse {
    name?: string;
}

export interface KubernetesExecResponse {
    lines?: string[];
}

export interface KubernetesLogEntry {
    date?: string;
    line?: string;
}

export interface KubernetesPod {
    namespace?: string;
    name?: string;
    created?: string;
    status?: string;
}

export interface KubernetesRbacRule {
    apiGroup?: string;
    resource?: string;
    verbs?: string[];
}

export interface KubernetesRbacShowResponse {
    rules?: KubernetesRbacRule[];
}

export interface PostCommand {
    name?: string;
    command?: string;
    allPods?: boolean;
}

export interface PostCommandList {
    values?: PostCommand[];
}

export interface ResourceManagementProfile {
    workspaceType?: string;
    cpuRequest?: number;
    cpuLimit?: number;
    memoryRequest?: number;
    memoryLimit?: number;
    replicas?: number;
}

export interface ResourceManagementProfileList {
    values?: ResourceManagementProfile[];
}

export interface ServicePort {
    protocol?: string;
    name?: string;
    port?: number;
    targetPort?: number;
}

export interface ServicePortList {
    values?: ServicePort[];
}

export interface StringArrayInterface {
    values?: string[];
}

export interface StringInterface {
    value?: string;
}

export interface WebhookTypesGetResponse {
    name?: string;
}


export class ContainerImagesGet extends BaseApi<ContainerImage> {

    public topic = 'Resources.ContainerImages';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/container_images`;
    }

    protected convertToResource(data: any): ContainerImage {
        return new ContainerImage(data);
    }

    public where(name: string, value: any): ContainerImagesGet {
        this.filter().where(name, value);
        return this;
    }

    public whereEquals(name: string, value: any): ContainerImagesGet {
        this.filter().whereEquals(name, value);
        return this;
    }

    public whereIn(name: string, value: any[]): ContainerImagesGet {
        this.filter().whereIn(name, value);
        return this;
    }

    public whereInArray(name: string, value: any[]): ContainerImagesGet {
        this.filter().whereInArray(name, value);
        return this;
    }

    public whereNot(name: string, value: any): ContainerImagesGet {
        this.filter().whereNot(name, value);
        return this;
    }

    public whereNotIn(name: string, value: any[]): ContainerImagesGet {
        this.filter().whereNotIn(name, value);
        return this;
    }

    public whereGreaterThan(name: string, value: any): ContainerImagesGet {
        this.filter().whereGreaterThan(name, value);
        return this;
    }

    public whereGreaterThanOrEqual(name: string, value: any): ContainerImagesGet {
        this.filter().whereGreaterThanOrEqual(name, value);
        return this;
    }

    public whereLessThan(name: string, value: any): ContainerImagesGet {
        this.filter().whereLessThan(name, value);
        return this;
    }

    public whereLessThanOrEqual(name: string, value: any): ContainerImagesGet {
        this.filter().whereLessThanOrEqual(name, value);
        return this;
    }

    public search(name: string, value: any): ContainerImagesGet {
        this.filter().search(name, value);
        return this;
    }

    public include(name: string): ContainerImagesGet {
        this.getInclude().include(name);
        return this;
    }

    public orderBy(name: string, direction: string): ContainerImagesGet {
        this.ordering().orderBy(name, direction);
        return this;
    }

    public orderAsc(name: string): ContainerImagesGet {
        this.ordering().orderAsc(name);
        return this;
    }

    public orderDesc(name: string): ContainerImagesGet {
        this.ordering().orderDesc(name);
        return this;
    }

    public limit(value: number): ContainerImagesGet {
        this.limitValue = value;
        return this;
    }

    public offset(value: number): ContainerImagesGet {
        this.offsetValue = value;
        return this;
    }

    public count(next?: (value: number) => void) {
        return this.executeCount(next);
    }

    public find(next?: (value: ContainerImage[]) => void) {
        return super.executeFind(next);
    }
}

export class ContainerImagesGetById extends BaseApi<ContainerImage> {

    public topic = 'Resources.ContainerImages';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/container_images/${id}`;
    }

    protected convertToResource(data: any): ContainerImage {
        return new ContainerImage(data);
    }

    public include(name: string): ContainerImagesGetById {
        this.getInclude().include(name);
        return this;
    }

    public find(next?: (value: ContainerImage[]) => void) {
        return super.executeFind(next);
    }
}

export class ContainerImagesPost extends BaseApi<ContainerImage> {

    public topic = 'Resources.ContainerImages';
    protected method = 'post';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/container_images`;
    }

    protected convertToResource(data: any): ContainerImage {
        return new ContainerImage(data);
    }

    public save(data: ContainerImage, next?: (value: ContainerImage) => void) {
        return super.executeSave(data, next);
    }
}

export class ContainerImagesPatchById extends BaseApi<ContainerImage> {

    public topic = 'Resources.ContainerImages';
    protected method = 'patch';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/container_images/${id}`;
    }

    protected convertToResource(data: any): ContainerImage {
        return new ContainerImage(data);
    }

    public save(data: ContainerImage, next?: (value: ContainerImage) => void) {
        return super.executeSave(data, next);
    }
}

export class ContainerImagesPatch extends BaseApi<ContainerImage> {

    public topic = 'Resources.ContainerImages';
    protected method = 'patch';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/container_images`;
    }

    protected convertToResource(data: any): ContainerImage {
        return new ContainerImage(data);
    }

    public save(data: ContainerImage, next?: (value: ContainerImage) => void) {
        return super.executeSave(data, next);
    }
}

export class ContainerImagesDeleteById extends BaseApi<ContainerImage> {

    public topic = 'Resources.ContainerImages';
    protected method = 'delete';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/container_images/${id}`;
    }

    protected convertToResource(data: any): ContainerImage {
        return new ContainerImage(data);
    }

    public delete(next?: (value: ContainerImage) => void) {
        return super.executeDelete(next);
    }
}

class ContainerImages {

    public get(): ContainerImagesGet {
        return new ContainerImagesGet();
    }

    public getById(id: number): ContainerImagesGetById {
        return new ContainerImagesGetById(id);
    }

    public post(): ContainerImagesPost {
        return new ContainerImagesPost();
    }

    public patchById(id: number): ContainerImagesPatchById {
        return new ContainerImagesPatchById(id);
    }

    public patch(): ContainerImagesPatch {
        return new ContainerImagesPatch();
    }

    public deleteById(id: number): ContainerImagesDeleteById {
        return new ContainerImagesDeleteById(id);
    }

}


export class DatabaseServicesGet extends BaseApi<DatabaseService> {

    public topic = 'Resources.DatabaseServices';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/database_services`;
    }

    protected convertToResource(data: any): DatabaseService {
        return new DatabaseService(data);
    }

    public where(name: string, value: any): DatabaseServicesGet {
        this.filter().where(name, value);
        return this;
    }

    public whereEquals(name: string, value: any): DatabaseServicesGet {
        this.filter().whereEquals(name, value);
        return this;
    }

    public whereIn(name: string, value: any[]): DatabaseServicesGet {
        this.filter().whereIn(name, value);
        return this;
    }

    public whereInArray(name: string, value: any[]): DatabaseServicesGet {
        this.filter().whereInArray(name, value);
        return this;
    }

    public whereNot(name: string, value: any): DatabaseServicesGet {
        this.filter().whereNot(name, value);
        return this;
    }

    public whereNotIn(name: string, value: any[]): DatabaseServicesGet {
        this.filter().whereNotIn(name, value);
        return this;
    }

    public whereGreaterThan(name: string, value: any): DatabaseServicesGet {
        this.filter().whereGreaterThan(name, value);
        return this;
    }

    public whereGreaterThanOrEqual(name: string, value: any): DatabaseServicesGet {
        this.filter().whereGreaterThanOrEqual(name, value);
        return this;
    }

    public whereLessThan(name: string, value: any): DatabaseServicesGet {
        this.filter().whereLessThan(name, value);
        return this;
    }

    public whereLessThanOrEqual(name: string, value: any): DatabaseServicesGet {
        this.filter().whereLessThanOrEqual(name, value);
        return this;
    }

    public search(name: string, value: any): DatabaseServicesGet {
        this.filter().search(name, value);
        return this;
    }

    public include(name: string): DatabaseServicesGet {
        this.getInclude().include(name);
        return this;
    }

    public orderBy(name: string, direction: string): DatabaseServicesGet {
        this.ordering().orderBy(name, direction);
        return this;
    }

    public orderAsc(name: string): DatabaseServicesGet {
        this.ordering().orderAsc(name);
        return this;
    }

    public orderDesc(name: string): DatabaseServicesGet {
        this.ordering().orderDesc(name);
        return this;
    }

    public limit(value: number): DatabaseServicesGet {
        this.limitValue = value;
        return this;
    }

    public offset(value: number): DatabaseServicesGet {
        this.offsetValue = value;
        return this;
    }

    public count(next?: (value: number) => void) {
        return this.executeCount(next);
    }

    public find(next?: (value: DatabaseService[]) => void) {
        return super.executeFind(next);
    }
}

export class DatabaseServicesGetById extends BaseApi<DatabaseService> {

    public topic = 'Resources.DatabaseServices';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/database_services/${id}`;
    }

    protected convertToResource(data: any): DatabaseService {
        return new DatabaseService(data);
    }

    public include(name: string): DatabaseServicesGetById {
        this.getInclude().include(name);
        return this;
    }

    public find(next?: (value: DatabaseService[]) => void) {
        return super.executeFind(next);
    }
}

export class DatabaseServicesPost extends BaseApi<DatabaseService> {

    public topic = 'Resources.DatabaseServices';
    protected method = 'post';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/database_services`;
    }

    protected convertToResource(data: any): DatabaseService {
        return new DatabaseService(data);
    }

    public save(data: DatabaseService, next?: (value: DatabaseService) => void) {
        return super.executeSave(data, next);
    }
}

export class DatabaseServicesPatchById extends BaseApi<DatabaseService> {

    public topic = 'Resources.DatabaseServices';
    protected method = 'patch';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/database_services/${id}`;
    }

    protected convertToResource(data: any): DatabaseService {
        return new DatabaseService(data);
    }

    public save(data: DatabaseService, next?: (value: DatabaseService) => void) {
        return super.executeSave(data, next);
    }
}

export class DatabaseServicesPatch extends BaseApi<DatabaseService> {

    public topic = 'Resources.DatabaseServices';
    protected method = 'patch';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/database_services`;
    }

    protected convertToResource(data: any): DatabaseService {
        return new DatabaseService(data);
    }

    public save(data: DatabaseService, next?: (value: DatabaseService) => void) {
        return super.executeSave(data, next);
    }
}

export class DatabaseServicesDeleteById extends BaseApi<DatabaseService> {

    public topic = 'Resources.DatabaseServices';
    protected method = 'delete';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/database_services/${id}`;
    }

    protected convertToResource(data: any): DatabaseService {
        return new DatabaseService(data);
    }

    public delete(next?: (value: DatabaseService) => void) {
        return super.executeDelete(next);
    }
}

class DatabaseServices {

    public get(): DatabaseServicesGet {
        return new DatabaseServicesGet();
    }

    public getById(id: number): DatabaseServicesGetById {
        return new DatabaseServicesGetById(id);
    }

    public post(): DatabaseServicesPost {
        return new DatabaseServicesPost();
    }

    public patchById(id: number): DatabaseServicesPatchById {
        return new DatabaseServicesPatchById(id);
    }

    public patch(): DatabaseServicesPatch {
        return new DatabaseServicesPatch();
    }

    public deleteById(id: number): DatabaseServicesDeleteById {
        return new DatabaseServicesDeleteById(id);
    }

}


export class DeploymentPackagesGet extends BaseApi<DeploymentPackage> {

    public topic = 'Resources.DeploymentPackages';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/deployment_packages`;
    }

    protected convertToResource(data: any): DeploymentPackage {
        return new DeploymentPackage(data);
    }

    public where(name: string, value: any): DeploymentPackagesGet {
        this.filter().where(name, value);
        return this;
    }

    public whereEquals(name: string, value: any): DeploymentPackagesGet {
        this.filter().whereEquals(name, value);
        return this;
    }

    public whereIn(name: string, value: any[]): DeploymentPackagesGet {
        this.filter().whereIn(name, value);
        return this;
    }

    public whereInArray(name: string, value: any[]): DeploymentPackagesGet {
        this.filter().whereInArray(name, value);
        return this;
    }

    public whereNot(name: string, value: any): DeploymentPackagesGet {
        this.filter().whereNot(name, value);
        return this;
    }

    public whereNotIn(name: string, value: any[]): DeploymentPackagesGet {
        this.filter().whereNotIn(name, value);
        return this;
    }

    public whereGreaterThan(name: string, value: any): DeploymentPackagesGet {
        this.filter().whereGreaterThan(name, value);
        return this;
    }

    public whereGreaterThanOrEqual(name: string, value: any): DeploymentPackagesGet {
        this.filter().whereGreaterThanOrEqual(name, value);
        return this;
    }

    public whereLessThan(name: string, value: any): DeploymentPackagesGet {
        this.filter().whereLessThan(name, value);
        return this;
    }

    public whereLessThanOrEqual(name: string, value: any): DeploymentPackagesGet {
        this.filter().whereLessThanOrEqual(name, value);
        return this;
    }

    public search(name: string, value: any): DeploymentPackagesGet {
        this.filter().search(name, value);
        return this;
    }

    public include(name: string): DeploymentPackagesGet {
        this.getInclude().include(name);
        return this;
    }

    public orderBy(name: string, direction: string): DeploymentPackagesGet {
        this.ordering().orderBy(name, direction);
        return this;
    }

    public orderAsc(name: string): DeploymentPackagesGet {
        this.ordering().orderAsc(name);
        return this;
    }

    public orderDesc(name: string): DeploymentPackagesGet {
        this.ordering().orderDesc(name);
        return this;
    }

    public limit(value: number): DeploymentPackagesGet {
        this.limitValue = value;
        return this;
    }

    public offset(value: number): DeploymentPackagesGet {
        this.offsetValue = value;
        return this;
    }

    public count(next?: (value: number) => void) {
        return this.executeCount(next);
    }

    public find(next?: (value: DeploymentPackage[]) => void) {
        return super.executeFind(next);
    }
}

export class DeploymentPackagesGetById extends BaseApi<DeploymentPackage> {

    public topic = 'Resources.DeploymentPackages';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployment_packages/${id}`;
    }

    protected convertToResource(data: any): DeploymentPackage {
        return new DeploymentPackage(data);
    }

    public include(name: string): DeploymentPackagesGetById {
        this.getInclude().include(name);
        return this;
    }

    public find(next?: (value: DeploymentPackage[]) => void) {
        return super.executeFind(next);
    }
}

export class DeploymentPackagesPost extends BaseApi<DeploymentPackage> {

    public topic = 'Resources.DeploymentPackages';
    protected method = 'post';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/deployment_packages`;
    }

    protected convertToResource(data: any): DeploymentPackage {
        return new DeploymentPackage(data);
    }

    public save(data: DeploymentPackage, next?: (value: DeploymentPackage) => void) {
        return super.executeSave(data, next);
    }
}

export class DeploymentPackagesPatchById extends BaseApi<DeploymentPackage> {

    public topic = 'Resources.DeploymentPackages';
    protected method = 'patch';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployment_packages/${id}`;
    }

    protected convertToResource(data: any): DeploymentPackage {
        return new DeploymentPackage(data);
    }

    public save(data: DeploymentPackage, next?: (value: DeploymentPackage) => void) {
        return super.executeSave(data, next);
    }
}

export class DeploymentPackagesPatch extends BaseApi<DeploymentPackage> {

    public topic = 'Resources.DeploymentPackages';
    protected method = 'patch';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/deployment_packages`;
    }

    protected convertToResource(data: any): DeploymentPackage {
        return new DeploymentPackage(data);
    }

    public save(data: DeploymentPackage, next?: (value: DeploymentPackage) => void) {
        return super.executeSave(data, next);
    }
}

export class DeploymentPackagesDeleteById extends BaseApi<DeploymentPackage> {

    public topic = 'Resources.DeploymentPackages';
    protected method = 'delete';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployment_packages/${id}`;
    }

    protected convertToResource(data: any): DeploymentPackage {
        return new DeploymentPackage(data);
    }

    public delete(next?: (value: DeploymentPackage) => void) {
        return super.executeDelete(next);
    }
}

export class DeploymentPackagesUpdateDeploymentSpecificationsPutById extends BaseApi<DeploymentPackage> {

    public topic = 'Resources.DeploymentPackages';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployment-packages/${id}/deployment-specifications`;
    }

    protected convertToResource(data: any): DeploymentPackage {
        return new DeploymentPackage(data);
    }

    public save(data: DeploymentPackageDeploymentSpecificationList, next?: (value: DeploymentPackage) => void) {
        return super.executeSave(data, next);
    }
}

class DeploymentPackages {

    public get(): DeploymentPackagesGet {
        return new DeploymentPackagesGet();
    }

    public getById(id: number): DeploymentPackagesGetById {
        return new DeploymentPackagesGetById(id);
    }

    public post(): DeploymentPackagesPost {
        return new DeploymentPackagesPost();
    }

    public patchById(id: number): DeploymentPackagesPatchById {
        return new DeploymentPackagesPatchById(id);
    }

    public patch(): DeploymentPackagesPatch {
        return new DeploymentPackagesPatch();
    }

    public deleteById(id: number): DeploymentPackagesDeleteById {
        return new DeploymentPackagesDeleteById(id);
    }

    public updateDeploymentSpecificationsPutById(id: number): DeploymentPackagesUpdateDeploymentSpecificationsPutById {
        return new DeploymentPackagesUpdateDeploymentSpecificationsPutById(id);
    }

}


export class DeploymentSpecificationsGet extends BaseApi<DeploymentSpecification> {

    public topic = 'Resources.DeploymentSpecifications';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/deployment_specifications`;
    }

    protected convertToResource(data: any): DeploymentSpecification {
        return new DeploymentSpecification(data);
    }

    public where(name: string, value: any): DeploymentSpecificationsGet {
        this.filter().where(name, value);
        return this;
    }

    public whereEquals(name: string, value: any): DeploymentSpecificationsGet {
        this.filter().whereEquals(name, value);
        return this;
    }

    public whereIn(name: string, value: any[]): DeploymentSpecificationsGet {
        this.filter().whereIn(name, value);
        return this;
    }

    public whereInArray(name: string, value: any[]): DeploymentSpecificationsGet {
        this.filter().whereInArray(name, value);
        return this;
    }

    public whereNot(name: string, value: any): DeploymentSpecificationsGet {
        this.filter().whereNot(name, value);
        return this;
    }

    public whereNotIn(name: string, value: any[]): DeploymentSpecificationsGet {
        this.filter().whereNotIn(name, value);
        return this;
    }

    public whereGreaterThan(name: string, value: any): DeploymentSpecificationsGet {
        this.filter().whereGreaterThan(name, value);
        return this;
    }

    public whereGreaterThanOrEqual(name: string, value: any): DeploymentSpecificationsGet {
        this.filter().whereGreaterThanOrEqual(name, value);
        return this;
    }

    public whereLessThan(name: string, value: any): DeploymentSpecificationsGet {
        this.filter().whereLessThan(name, value);
        return this;
    }

    public whereLessThanOrEqual(name: string, value: any): DeploymentSpecificationsGet {
        this.filter().whereLessThanOrEqual(name, value);
        return this;
    }

    public search(name: string, value: any): DeploymentSpecificationsGet {
        this.filter().search(name, value);
        return this;
    }

    public include(name: string): DeploymentSpecificationsGet {
        this.getInclude().include(name);
        return this;
    }

    public orderBy(name: string, direction: string): DeploymentSpecificationsGet {
        this.ordering().orderBy(name, direction);
        return this;
    }

    public orderAsc(name: string): DeploymentSpecificationsGet {
        this.ordering().orderAsc(name);
        return this;
    }

    public orderDesc(name: string): DeploymentSpecificationsGet {
        this.ordering().orderDesc(name);
        return this;
    }

    public limit(value: number): DeploymentSpecificationsGet {
        this.limitValue = value;
        return this;
    }

    public offset(value: number): DeploymentSpecificationsGet {
        this.offsetValue = value;
        return this;
    }

    public count(next?: (value: number) => void) {
        return this.executeCount(next);
    }

    public find(next?: (value: DeploymentSpecification[]) => void) {
        return super.executeFind(next);
    }
}

export class DeploymentSpecificationsGetById extends BaseApi<DeploymentSpecification> {

    public topic = 'Resources.DeploymentSpecifications';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployment_specifications/${id}`;
    }

    protected convertToResource(data: any): DeploymentSpecification {
        return new DeploymentSpecification(data);
    }

    public include(name: string): DeploymentSpecificationsGetById {
        this.getInclude().include(name);
        return this;
    }

    public find(next?: (value: DeploymentSpecification[]) => void) {
        return super.executeFind(next);
    }
}

export class DeploymentSpecificationsPost extends BaseApi<DeploymentSpecification> {

    public topic = 'Resources.DeploymentSpecifications';
    protected method = 'post';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/deployment_specifications`;
    }

    protected convertToResource(data: any): DeploymentSpecification {
        return new DeploymentSpecification(data);
    }

    public save(data: DeploymentSpecification, next?: (value: DeploymentSpecification) => void) {
        return super.executeSave(data, next);
    }
}

export class DeploymentSpecificationsPatchById extends BaseApi<DeploymentSpecification> {

    public topic = 'Resources.DeploymentSpecifications';
    protected method = 'patch';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployment_specifications/${id}`;
    }

    protected convertToResource(data: any): DeploymentSpecification {
        return new DeploymentSpecification(data);
    }

    public save(data: DeploymentSpecification, next?: (value: DeploymentSpecification) => void) {
        return super.executeSave(data, next);
    }
}

export class DeploymentSpecificationsPatch extends BaseApi<DeploymentSpecification> {

    public topic = 'Resources.DeploymentSpecifications';
    protected method = 'patch';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/deployment_specifications`;
    }

    protected convertToResource(data: any): DeploymentSpecification {
        return new DeploymentSpecification(data);
    }

    public save(data: DeploymentSpecification, next?: (value: DeploymentSpecification) => void) {
        return super.executeSave(data, next);
    }
}

export class DeploymentSpecificationsDeleteById extends BaseApi<DeploymentSpecification> {

    public topic = 'Resources.DeploymentSpecifications';
    protected method = 'delete';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployment_specifications/${id}`;
    }

    protected convertToResource(data: any): DeploymentSpecification {
        return new DeploymentSpecification(data);
    }

    public delete(next?: (value: DeploymentSpecification) => void) {
        return super.executeDelete(next);
    }
}

export class DeploymentSpecificationsGetTagsGetById extends BaseApi<DeploymentSpecificationTagsGetResponse> {

    public topic = 'Resources.DeploymentSpecificationTagsGetResponses';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployment-specifications/${id}/tags`;
    }

    protected convertToResource(data: any): DeploymentSpecificationTagsGetResponse {
        return data;
    }

    public find(next?: (value: DeploymentSpecificationTagsGetResponse[]) => void) {
        return super.executeFind(next);
    }
}

export class DeploymentSpecificationsUpdatePostCommandsPutById extends BaseApi<DeploymentSpecification> {

    public topic = 'Resources.DeploymentSpecifications';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployment-specifications/${id}/post-commands`;
    }

    protected convertToResource(data: any): DeploymentSpecification {
        return new DeploymentSpecification(data);
    }

    public save(data: PostCommandList, next?: (value: DeploymentSpecification) => void) {
        return super.executeSave(data, next);
    }
}

export class DeploymentSpecificationsUpdateEnvironmentVariablesPutById extends BaseApi<DeploymentSpecification> {

    public topic = 'Resources.DeploymentSpecifications';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployment-specifications/${id}/environment-variables`;
    }

    protected convertToResource(data: any): DeploymentSpecification {
        return new DeploymentSpecification(data);
    }

    public save(data: EnvironmentVariableList, next?: (value: DeploymentSpecification) => void) {
        return super.executeSave(data, next);
    }
}

export class DeploymentSpecificationsUpdateServicePortsPutById extends BaseApi<DeploymentSpecification> {

    public topic = 'Resources.DeploymentSpecifications';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployment-specifications/${id}/service-ports`;
    }

    protected convertToResource(data: any): DeploymentSpecification {
        return new DeploymentSpecification(data);
    }

    public save(data: ServicePortList, next?: (value: DeploymentSpecification) => void) {
        return super.executeSave(data, next);
    }
}

export class DeploymentSpecificationsUpdateIngressesPutById extends BaseApi<DeploymentSpecification> {

    public topic = 'Resources.DeploymentSpecifications';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployment-specifications/${id}/ingresses`;
    }

    protected convertToResource(data: any): DeploymentSpecification {
        return new DeploymentSpecification(data);
    }

    public save(data: IngressList, next?: (value: DeploymentSpecification) => void) {
        return super.executeSave(data, next);
    }
}

export class DeploymentSpecificationsUpdateClusterRoleRulesPutById extends BaseApi<DeploymentSpecification> {

    public topic = 'Resources.DeploymentSpecifications';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployment-specifications/${id}/cluster-role-rules`;
    }

    protected convertToResource(data: any): DeploymentSpecification {
        return new DeploymentSpecification(data);
    }

    public save(data: ClusterRoleRuleList, next?: (value: DeploymentSpecification) => void) {
        return super.executeSave(data, next);
    }
}

class DeploymentSpecifications {

    public get(): DeploymentSpecificationsGet {
        return new DeploymentSpecificationsGet();
    }

    public getById(id: number): DeploymentSpecificationsGetById {
        return new DeploymentSpecificationsGetById(id);
    }

    public post(): DeploymentSpecificationsPost {
        return new DeploymentSpecificationsPost();
    }

    public patchById(id: number): DeploymentSpecificationsPatchById {
        return new DeploymentSpecificationsPatchById(id);
    }

    public patch(): DeploymentSpecificationsPatch {
        return new DeploymentSpecificationsPatch();
    }

    public deleteById(id: number): DeploymentSpecificationsDeleteById {
        return new DeploymentSpecificationsDeleteById(id);
    }

    public getTagsGetById(id: number): DeploymentSpecificationsGetTagsGetById {
        return new DeploymentSpecificationsGetTagsGetById(id);
    }

    public updatePostCommandsPutById(id: number): DeploymentSpecificationsUpdatePostCommandsPutById {
        return new DeploymentSpecificationsUpdatePostCommandsPutById(id);
    }

    public updateEnvironmentVariablesPutById(id: number): DeploymentSpecificationsUpdateEnvironmentVariablesPutById {
        return new DeploymentSpecificationsUpdateEnvironmentVariablesPutById(id);
    }

    public updateServicePortsPutById(id: number): DeploymentSpecificationsUpdateServicePortsPutById {
        return new DeploymentSpecificationsUpdateServicePortsPutById(id);
    }

    public updateIngressesPutById(id: number): DeploymentSpecificationsUpdateIngressesPutById {
        return new DeploymentSpecificationsUpdateIngressesPutById(id);
    }

    public updateClusterRoleRulesPutById(id: number): DeploymentSpecificationsUpdateClusterRoleRulesPutById {
        return new DeploymentSpecificationsUpdateClusterRoleRulesPutById(id);
    }

}


export class DeploymentStepsGetStatusGetByIdentifier extends BaseApi<StringInterface> {

    public topic = 'Resources.StringInterfaces';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(identifier: string) {
        super();
        this.uri = `/deployment-steps/${identifier}/status`;
    }

    protected convertToResource(data: any): StringInterface {
        return data;
    }

    public deploymentId(value: number): DeploymentStepsGetStatusGetByIdentifier {
        this.addQueryParameter('deploymentId', value);
        return this;
    }

    public find(next?: (value: StringInterface[]) => void) {
        return super.executeFind(next);
    }
}

export class DeploymentStepsGetPreviewGetByIdentifier extends BaseApi<StringInterface> {

    public topic = 'Resources.StringInterfaces';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(identifier: string) {
        super();
        this.uri = `/deployment-steps/${identifier}/preview`;
    }

    protected convertToResource(data: any): StringInterface {
        return data;
    }

    public deploymentId(value: number): DeploymentStepsGetPreviewGetByIdentifier {
        this.addQueryParameter('deploymentId', value);
        return this;
    }

    public find(next?: (value: StringInterface[]) => void) {
        return super.executeFind(next);
    }
}

export class DeploymentStepsDeployPutByIdentifier extends BaseApi<any> {

    public topic = 'UnknownResource';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(identifier: string) {
        super();
        this.uri = `/deployment-steps/${identifier}/deploy`;
    }

    protected convertToResource(data: any): any {
        return data;
    }

    public deploymentId(value: number): DeploymentStepsDeployPutByIdentifier {
        this.addQueryParameter('deploymentId', value);
        return this;
    }

    public save(data: any, next?: (value: any) => void) {
        return super.executeSave(data, next);
    }
}

export class DeploymentStepsTerminatePutByIdentifier extends BaseApi<any> {

    public topic = 'UnknownResource';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(identifier: string) {
        super();
        this.uri = `/deployment-steps/${identifier}/terminate`;
    }

    protected convertToResource(data: any): any {
        return data;
    }

    public deploymentId(value: number): DeploymentStepsTerminatePutByIdentifier {
        this.addQueryParameter('deploymentId', value);
        return this;
    }

    public save(data: any, next?: (value: any) => void) {
        return super.executeSave(data, next);
    }
}

export class DeploymentStepsGetKubernetesStatusGetByIdentifier extends BaseApi<StringInterface> {

    public topic = 'Resources.StringInterfaces';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(identifier: string) {
        super();
        this.uri = `/deployment-steps/${identifier}/kubernetes-status`;
    }

    protected convertToResource(data: any): StringInterface {
        return data;
    }

    public deploymentId(value: number): DeploymentStepsGetKubernetesStatusGetByIdentifier {
        this.addQueryParameter('deploymentId', value);
        return this;
    }

    public find(next?: (value: StringInterface[]) => void) {
        return super.executeFind(next);
    }
}

export class DeploymentStepsGetKubernetesEventsGetByIdentifier extends BaseApi<StringInterface> {

    public topic = 'Resources.StringInterfaces';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(identifier: string) {
        super();
        this.uri = `/deployment-steps/${identifier}/kubernetes-events`;
    }

    protected convertToResource(data: any): StringInterface {
        return data;
    }

    public deploymentId(value: number): DeploymentStepsGetKubernetesEventsGetByIdentifier {
        this.addQueryParameter('deploymentId', value);
        return this;
    }

    public find(next?: (value: StringInterface[]) => void) {
        return super.executeFind(next);
    }
}

class DeploymentSteps {

    public getStatusGetByIdentifier(identifier: string): DeploymentStepsGetStatusGetByIdentifier {
        return new DeploymentStepsGetStatusGetByIdentifier(identifier);
    }

    public getPreviewGetByIdentifier(identifier: string): DeploymentStepsGetPreviewGetByIdentifier {
        return new DeploymentStepsGetPreviewGetByIdentifier(identifier);
    }

    public deployPutByIdentifier(identifier: string): DeploymentStepsDeployPutByIdentifier {
        return new DeploymentStepsDeployPutByIdentifier(identifier);
    }

    public terminatePutByIdentifier(identifier: string): DeploymentStepsTerminatePutByIdentifier {
        return new DeploymentStepsTerminatePutByIdentifier(identifier);
    }

    public getKubernetesStatusGetByIdentifier(identifier: string): DeploymentStepsGetKubernetesStatusGetByIdentifier {
        return new DeploymentStepsGetKubernetesStatusGetByIdentifier(identifier);
    }

    public getKubernetesEventsGetByIdentifier(identifier: string): DeploymentStepsGetKubernetesEventsGetByIdentifier {
        return new DeploymentStepsGetKubernetesEventsGetByIdentifier(identifier);
    }

}


export class DeploymentsGet extends BaseApi<Deployment> {

    public topic = 'Resources.Deployments';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/deployments`;
    }

    protected convertToResource(data: any): Deployment {
        return new Deployment(data);
    }

    public where(name: string, value: any): DeploymentsGet {
        this.filter().where(name, value);
        return this;
    }

    public whereEquals(name: string, value: any): DeploymentsGet {
        this.filter().whereEquals(name, value);
        return this;
    }

    public whereIn(name: string, value: any[]): DeploymentsGet {
        this.filter().whereIn(name, value);
        return this;
    }

    public whereInArray(name: string, value: any[]): DeploymentsGet {
        this.filter().whereInArray(name, value);
        return this;
    }

    public whereNot(name: string, value: any): DeploymentsGet {
        this.filter().whereNot(name, value);
        return this;
    }

    public whereNotIn(name: string, value: any[]): DeploymentsGet {
        this.filter().whereNotIn(name, value);
        return this;
    }

    public whereGreaterThan(name: string, value: any): DeploymentsGet {
        this.filter().whereGreaterThan(name, value);
        return this;
    }

    public whereGreaterThanOrEqual(name: string, value: any): DeploymentsGet {
        this.filter().whereGreaterThanOrEqual(name, value);
        return this;
    }

    public whereLessThan(name: string, value: any): DeploymentsGet {
        this.filter().whereLessThan(name, value);
        return this;
    }

    public whereLessThanOrEqual(name: string, value: any): DeploymentsGet {
        this.filter().whereLessThanOrEqual(name, value);
        return this;
    }

    public search(name: string, value: any): DeploymentsGet {
        this.filter().search(name, value);
        return this;
    }

    public include(name: string): DeploymentsGet {
        this.getInclude().include(name);
        return this;
    }

    public orderBy(name: string, direction: string): DeploymentsGet {
        this.ordering().orderBy(name, direction);
        return this;
    }

    public orderAsc(name: string): DeploymentsGet {
        this.ordering().orderAsc(name);
        return this;
    }

    public orderDesc(name: string): DeploymentsGet {
        this.ordering().orderDesc(name);
        return this;
    }

    public limit(value: number): DeploymentsGet {
        this.limitValue = value;
        return this;
    }

    public offset(value: number): DeploymentsGet {
        this.offsetValue = value;
        return this;
    }

    public count(next?: (value: number) => void) {
        return this.executeCount(next);
    }

    public find(next?: (value: Deployment[]) => void) {
        return super.executeFind(next);
    }
}

export class DeploymentsGetById extends BaseApi<Deployment> {

    public topic = 'Resources.Deployments';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployments/${id}`;
    }

    protected convertToResource(data: any): Deployment {
        return new Deployment(data);
    }

    public include(name: string): DeploymentsGetById {
        this.getInclude().include(name);
        return this;
    }

    public find(next?: (value: Deployment[]) => void) {
        return super.executeFind(next);
    }
}

export class DeploymentsPatchById extends BaseApi<Deployment> {

    public topic = 'Resources.Deployments';
    protected method = 'patch';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployments/${id}`;
    }

    protected convertToResource(data: any): Deployment {
        return new Deployment(data);
    }

    public save(data: Deployment, next?: (value: Deployment) => void) {
        return super.executeSave(data, next);
    }
}

export class DeploymentsPatch extends BaseApi<Deployment> {

    public topic = 'Resources.Deployments';
    protected method = 'patch';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/deployments`;
    }

    protected convertToResource(data: any): Deployment {
        return new Deployment(data);
    }

    public save(data: Deployment, next?: (value: Deployment) => void) {
        return super.executeSave(data, next);
    }
}

export class DeploymentsDeleteById extends BaseApi<Deployment> {

    public topic = 'Resources.Deployments';
    protected method = 'delete';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployments/${id}`;
    }

    protected convertToResource(data: any): Deployment {
        return new Deployment(data);
    }

    public delete(next?: (value: Deployment) => void) {
        return super.executeDelete(next);
    }
}

export class DeploymentsCreatePost extends BaseApi<Deployment> {

    public topic = 'Resources.Deployments';
    protected method = 'post';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/deployments/create`;
    }

    protected convertToResource(data: any): Deployment {
        return new Deployment(data);
    }

    public deploymentSpecificationId(value: number): DeploymentsCreatePost {
        this.addQueryParameter('deploymentSpecificationId', value);
        return this;
    }

    public name(value: string): DeploymentsCreatePost {
        this.addQueryParameter('name', value);
        return this;
    }

    public namespace(value: string): DeploymentsCreatePost {
        this.addQueryParameter('namespace', value);
        return this;
    }

    public save(data: any, next?: (value: Deployment) => void) {
        return super.executeSave(data, next);
    }
}

export class DeploymentsUpdateVersionPutById extends BaseApi<Deployment> {

    public topic = 'Resources.Deployments';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployments/${id}/version`;
    }

    protected convertToResource(data: any): Deployment {
        return new Deployment(data);
    }

    public value(value: string): DeploymentsUpdateVersionPutById {
        this.addQueryParameter('value', value);
        return this;
    }

    public save(data: any, next?: (value: Deployment) => void) {
        return super.executeSave(data, next);
    }
}

export class DeploymentsUpdateEnvironmentPutById extends BaseApi<Deployment> {

    public topic = 'Resources.Deployments';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployments/${id}/environment`;
    }

    protected convertToResource(data: any): Deployment {
        return new Deployment(data);
    }

    public value(value: string): DeploymentsUpdateEnvironmentPutById {
        this.addQueryParameter('value', value);
        return this;
    }

    public save(data: any, next?: (value: Deployment) => void) {
        return super.executeSave(data, next);
    }
}

export class DeploymentsUpdateDatabaseServiceIdPutById extends BaseApi<Deployment> {

    public topic = 'Resources.Deployments';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployments/${id}/databaseServiceId`;
    }

    protected convertToResource(data: any): Deployment {
        return new Deployment(data);
    }

    public value(value: number): DeploymentsUpdateDatabaseServiceIdPutById {
        this.addQueryParameter('value', value);
        return this;
    }

    public save(data: any, next?: (value: Deployment) => void) {
        return super.executeSave(data, next);
    }
}

export class DeploymentsUpdateIngressPutById extends BaseApi<Deployment> {

    public topic = 'Resources.Deployments';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployments/${id}/ingress`;
    }

    protected convertToResource(data: any): Deployment {
        return new Deployment(data);
    }

    public domainId(value: number): DeploymentsUpdateIngressPutById {
        this.addQueryParameter('domainId', value);
        return this;
    }

    public subdomain(value: string): DeploymentsUpdateIngressPutById {
        this.addQueryParameter('subdomain', value);
        return this;
    }

    public aliases(value: string): DeploymentsUpdateIngressPutById {
        this.addQueryParameter('aliases', value);
        return this;
    }

    public save(data: any, next?: (value: Deployment) => void) {
        return super.executeSave(data, next);
    }
}

export class DeploymentsUpdateResourceManagemnetPutById extends BaseApi<Deployment> {

    public topic = 'Resources.Deployments';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployments/${id}/resourceManagement`;
    }

    protected convertToResource(data: any): Deployment {
        return new Deployment(data);
    }

    public cpuLimit(value: number): DeploymentsUpdateResourceManagemnetPutById {
        this.addQueryParameter('cpuLimit', value);
        return this;
    }

    public cpuRequest(value: number): DeploymentsUpdateResourceManagemnetPutById {
        this.addQueryParameter('cpuRequest', value);
        return this;
    }

    public memoryLimit(value: number): DeploymentsUpdateResourceManagemnetPutById {
        this.addQueryParameter('memoryLimit', value);
        return this;
    }

    public memoryRequest(value: number): DeploymentsUpdateResourceManagemnetPutById {
        this.addQueryParameter('memoryRequest', value);
        return this;
    }

    public replicas(value: number): DeploymentsUpdateResourceManagemnetPutById {
        this.addQueryParameter('replicas', value);
        return this;
    }

    public save(data: any, next?: (value: Deployment) => void) {
        return super.executeSave(data, next);
    }
}

export class DeploymentsUpdateUpdateManagementPutById extends BaseApi<Deployment> {

    public topic = 'Resources.Deployments';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployments/${id}/updateManagement`;
    }

    protected convertToResource(data: any): Deployment {
        return new Deployment(data);
    }

    public keelPolicy(value: string): DeploymentsUpdateUpdateManagementPutById {
        this.addQueryParameter('keelPolicy', value);
        return this;
    }

    public keelAutoUpdate(value: boolean): DeploymentsUpdateUpdateManagementPutById {
        this.addQueryParameter('keelAutoUpdate', value);
        return this;
    }

    public enablePodioNotification(value: boolean): DeploymentsUpdateUpdateManagementPutById {
        this.addQueryParameter('enablePodioNotification', value);
        return this;
    }

    public save(data: any, next?: (value: Deployment) => void) {
        return super.executeSave(data, next);
    }
}

export class DeploymentsUpdateEnvironmentVariablesPutById extends BaseApi<Deployment> {

    public topic = 'Resources.Deployments';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployments/${id}/environment-variables`;
    }

    protected convertToResource(data: any): Deployment {
        return new Deployment(data);
    }

    public save(data: EnvironmentVariableList, next?: (value: Deployment) => void) {
        return super.executeSave(data, next);
    }
}

export class DeploymentsUpdateDeploymentVolumesPutById extends BaseApi<Deployment> {

    public topic = 'Resources.Deployments';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployments/${id}/volumes`;
    }

    protected convertToResource(data: any): Deployment {
        return new Deployment(data);
    }

    public save(data: DeploymentVolumeList, next?: (value: Deployment) => void) {
        return super.executeSave(data, next);
    }
}

export class DeploymentsGetStatusGetById extends BaseApi<Deployment> {

    public topic = 'Resources.Deployments';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployments/${id}/status`;
    }

    protected convertToResource(data: any): Deployment {
        return new Deployment(data);
    }

    public find(next?: (value: Deployment[]) => void) {
        return super.executeFind(next);
    }
}

export class DeploymentsGetMigrationJobsGetById extends BaseApi<MigrationJob> {

    public topic = 'Resources.MigrationJobs';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployments/${id}/migration-jobs`;
    }

    protected convertToResource(data: any): MigrationJob {
        return new MigrationJob(data);
    }

    public find(next?: (value: MigrationJob[]) => void) {
        return super.executeFind(next);
    }
}

export class DeploymentsGetDeploymentSpecificationGetById extends BaseApi<DeploymentSpecification> {

    public topic = 'Resources.DeploymentSpecifications';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/deployments/${id}/deployment-specification`;
    }

    protected convertToResource(data: any): DeploymentSpecification {
        return new DeploymentSpecification(data);
    }

    public find(next?: (value: DeploymentSpecification[]) => void) {
        return super.executeFind(next);
    }
}

class Deployments {

    public get(): DeploymentsGet {
        return new DeploymentsGet();
    }

    public getById(id: number): DeploymentsGetById {
        return new DeploymentsGetById(id);
    }

    public patchById(id: number): DeploymentsPatchById {
        return new DeploymentsPatchById(id);
    }

    public patch(): DeploymentsPatch {
        return new DeploymentsPatch();
    }

    public deleteById(id: number): DeploymentsDeleteById {
        return new DeploymentsDeleteById(id);
    }

    public createPost(): DeploymentsCreatePost {
        return new DeploymentsCreatePost();
    }

    public updateVersionPutById(id: number): DeploymentsUpdateVersionPutById {
        return new DeploymentsUpdateVersionPutById(id);
    }

    public updateEnvironmentPutById(id: number): DeploymentsUpdateEnvironmentPutById {
        return new DeploymentsUpdateEnvironmentPutById(id);
    }

    public updateDatabaseServiceIdPutById(id: number): DeploymentsUpdateDatabaseServiceIdPutById {
        return new DeploymentsUpdateDatabaseServiceIdPutById(id);
    }

    public updateIngressPutById(id: number): DeploymentsUpdateIngressPutById {
        return new DeploymentsUpdateIngressPutById(id);
    }

    public updateResourceManagemnetPutById(id: number): DeploymentsUpdateResourceManagemnetPutById {
        return new DeploymentsUpdateResourceManagemnetPutById(id);
    }

    public updateUpdateManagementPutById(id: number): DeploymentsUpdateUpdateManagementPutById {
        return new DeploymentsUpdateUpdateManagementPutById(id);
    }

    public updateEnvironmentVariablesPutById(id: number): DeploymentsUpdateEnvironmentVariablesPutById {
        return new DeploymentsUpdateEnvironmentVariablesPutById(id);
    }

    public updateDeploymentVolumesPutById(id: number): DeploymentsUpdateDeploymentVolumesPutById {
        return new DeploymentsUpdateDeploymentVolumesPutById(id);
    }

    public getStatusGetById(id: number): DeploymentsGetStatusGetById {
        return new DeploymentsGetStatusGetById(id);
    }

    public getMigrationJobsGetById(id: number): DeploymentsGetMigrationJobsGetById {
        return new DeploymentsGetMigrationJobsGetById(id);
    }

    public getDeploymentSpecificationGetById(id: number): DeploymentsGetDeploymentSpecificationGetById {
        return new DeploymentsGetDeploymentSpecificationGetById(id);
    }

}


export class DomainsGet extends BaseApi<Domain> {

    public topic = 'Resources.Domains';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/domains`;
    }

    protected convertToResource(data: any): Domain {
        return new Domain(data);
    }

    public where(name: string, value: any): DomainsGet {
        this.filter().where(name, value);
        return this;
    }

    public whereEquals(name: string, value: any): DomainsGet {
        this.filter().whereEquals(name, value);
        return this;
    }

    public whereIn(name: string, value: any[]): DomainsGet {
        this.filter().whereIn(name, value);
        return this;
    }

    public whereInArray(name: string, value: any[]): DomainsGet {
        this.filter().whereInArray(name, value);
        return this;
    }

    public whereNot(name: string, value: any): DomainsGet {
        this.filter().whereNot(name, value);
        return this;
    }

    public whereNotIn(name: string, value: any[]): DomainsGet {
        this.filter().whereNotIn(name, value);
        return this;
    }

    public whereGreaterThan(name: string, value: any): DomainsGet {
        this.filter().whereGreaterThan(name, value);
        return this;
    }

    public whereGreaterThanOrEqual(name: string, value: any): DomainsGet {
        this.filter().whereGreaterThanOrEqual(name, value);
        return this;
    }

    public whereLessThan(name: string, value: any): DomainsGet {
        this.filter().whereLessThan(name, value);
        return this;
    }

    public whereLessThanOrEqual(name: string, value: any): DomainsGet {
        this.filter().whereLessThanOrEqual(name, value);
        return this;
    }

    public search(name: string, value: any): DomainsGet {
        this.filter().search(name, value);
        return this;
    }

    public include(name: string): DomainsGet {
        this.getInclude().include(name);
        return this;
    }

    public orderBy(name: string, direction: string): DomainsGet {
        this.ordering().orderBy(name, direction);
        return this;
    }

    public orderAsc(name: string): DomainsGet {
        this.ordering().orderAsc(name);
        return this;
    }

    public orderDesc(name: string): DomainsGet {
        this.ordering().orderDesc(name);
        return this;
    }

    public limit(value: number): DomainsGet {
        this.limitValue = value;
        return this;
    }

    public offset(value: number): DomainsGet {
        this.offsetValue = value;
        return this;
    }

    public count(next?: (value: number) => void) {
        return this.executeCount(next);
    }

    public find(next?: (value: Domain[]) => void) {
        return super.executeFind(next);
    }
}

export class DomainsGetById extends BaseApi<Domain> {

    public topic = 'Resources.Domains';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/domains/${id}`;
    }

    protected convertToResource(data: any): Domain {
        return new Domain(data);
    }

    public include(name: string): DomainsGetById {
        this.getInclude().include(name);
        return this;
    }

    public find(next?: (value: Domain[]) => void) {
        return super.executeFind(next);
    }
}

export class DomainsPost extends BaseApi<Domain> {

    public topic = 'Resources.Domains';
    protected method = 'post';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/domains`;
    }

    protected convertToResource(data: any): Domain {
        return new Domain(data);
    }

    public save(data: Domain, next?: (value: Domain) => void) {
        return super.executeSave(data, next);
    }
}

export class DomainsPatchById extends BaseApi<Domain> {

    public topic = 'Resources.Domains';
    protected method = 'patch';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/domains/${id}`;
    }

    protected convertToResource(data: any): Domain {
        return new Domain(data);
    }

    public save(data: Domain, next?: (value: Domain) => void) {
        return super.executeSave(data, next);
    }
}

export class DomainsPatch extends BaseApi<Domain> {

    public topic = 'Resources.Domains';
    protected method = 'patch';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/domains`;
    }

    protected convertToResource(data: any): Domain {
        return new Domain(data);
    }

    public save(data: Domain, next?: (value: Domain) => void) {
        return super.executeSave(data, next);
    }
}

export class DomainsDeleteById extends BaseApi<Domain> {

    public topic = 'Resources.Domains';
    protected method = 'delete';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/domains/${id}`;
    }

    protected convertToResource(data: any): Domain {
        return new Domain(data);
    }

    public delete(next?: (value: Domain) => void) {
        return super.executeDelete(next);
    }
}

export class DomainsApplyCertificatePutById extends BaseApi<Domain> {

    public topic = 'Resources.Domains';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/domains/${id}/certificate/apply`;
    }

    protected convertToResource(data: any): Domain {
        return new Domain(data);
    }

    public save(data: any, next?: (value: Domain) => void) {
        return super.executeSave(data, next);
    }
}

export class DomainsGetCertificateEventsGetById extends BaseApi<DomainsGetCertificateEventsResponse> {

    public topic = 'Resources.DomainsGetCertificateEventsResponses';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/domains/${id}/certificate/events`;
    }

    protected convertToResource(data: any): DomainsGetCertificateEventsResponse {
        return data;
    }

    public find(next?: (value: DomainsGetCertificateEventsResponse[]) => void) {
        return super.executeFind(next);
    }
}

export class DomainsGetCertificateStatusGetById extends BaseApi<DomainsGetCertificateStatusResponse> {

    public topic = 'Resources.DomainsGetCertificateStatusResponses';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/domains/${id}/certificate/status`;
    }

    protected convertToResource(data: any): DomainsGetCertificateStatusResponse {
        return data;
    }

    public find(next?: (value: DomainsGetCertificateStatusResponse[]) => void) {
        return super.executeFind(next);
    }
}

class Domains {

    public get(): DomainsGet {
        return new DomainsGet();
    }

    public getById(id: number): DomainsGetById {
        return new DomainsGetById(id);
    }

    public post(): DomainsPost {
        return new DomainsPost();
    }

    public patchById(id: number): DomainsPatchById {
        return new DomainsPatchById(id);
    }

    public patch(): DomainsPatch {
        return new DomainsPatch();
    }

    public deleteById(id: number): DomainsDeleteById {
        return new DomainsDeleteById(id);
    }

    public applyCertificatePutById(id: number): DomainsApplyCertificatePutById {
        return new DomainsApplyCertificatePutById(id);
    }

    public getCertificateEventsGetById(id: number): DomainsGetCertificateEventsGetById {
        return new DomainsGetCertificateEventsGetById(id);
    }

    public getCertificateStatusGetById(id: number): DomainsGetCertificateStatusGetById {
        return new DomainsGetCertificateStatusGetById(id);
    }

}


export class EmailServicesGet extends BaseApi<EmailService> {

    public topic = 'Resources.EmailServices';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/email_services`;
    }

    protected convertToResource(data: any): EmailService {
        return new EmailService(data);
    }

    public where(name: string, value: any): EmailServicesGet {
        this.filter().where(name, value);
        return this;
    }

    public whereEquals(name: string, value: any): EmailServicesGet {
        this.filter().whereEquals(name, value);
        return this;
    }

    public whereIn(name: string, value: any[]): EmailServicesGet {
        this.filter().whereIn(name, value);
        return this;
    }

    public whereInArray(name: string, value: any[]): EmailServicesGet {
        this.filter().whereInArray(name, value);
        return this;
    }

    public whereNot(name: string, value: any): EmailServicesGet {
        this.filter().whereNot(name, value);
        return this;
    }

    public whereNotIn(name: string, value: any[]): EmailServicesGet {
        this.filter().whereNotIn(name, value);
        return this;
    }

    public whereGreaterThan(name: string, value: any): EmailServicesGet {
        this.filter().whereGreaterThan(name, value);
        return this;
    }

    public whereGreaterThanOrEqual(name: string, value: any): EmailServicesGet {
        this.filter().whereGreaterThanOrEqual(name, value);
        return this;
    }

    public whereLessThan(name: string, value: any): EmailServicesGet {
        this.filter().whereLessThan(name, value);
        return this;
    }

    public whereLessThanOrEqual(name: string, value: any): EmailServicesGet {
        this.filter().whereLessThanOrEqual(name, value);
        return this;
    }

    public search(name: string, value: any): EmailServicesGet {
        this.filter().search(name, value);
        return this;
    }

    public include(name: string): EmailServicesGet {
        this.getInclude().include(name);
        return this;
    }

    public orderBy(name: string, direction: string): EmailServicesGet {
        this.ordering().orderBy(name, direction);
        return this;
    }

    public orderAsc(name: string): EmailServicesGet {
        this.ordering().orderAsc(name);
        return this;
    }

    public orderDesc(name: string): EmailServicesGet {
        this.ordering().orderDesc(name);
        return this;
    }

    public limit(value: number): EmailServicesGet {
        this.limitValue = value;
        return this;
    }

    public offset(value: number): EmailServicesGet {
        this.offsetValue = value;
        return this;
    }

    public count(next?: (value: number) => void) {
        return this.executeCount(next);
    }

    public find(next?: (value: EmailService[]) => void) {
        return super.executeFind(next);
    }
}

export class EmailServicesGetById extends BaseApi<EmailService> {

    public topic = 'Resources.EmailServices';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/email_services/${id}`;
    }

    protected convertToResource(data: any): EmailService {
        return new EmailService(data);
    }

    public include(name: string): EmailServicesGetById {
        this.getInclude().include(name);
        return this;
    }

    public find(next?: (value: EmailService[]) => void) {
        return super.executeFind(next);
    }
}

export class EmailServicesPost extends BaseApi<EmailService> {

    public topic = 'Resources.EmailServices';
    protected method = 'post';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/email_services`;
    }

    protected convertToResource(data: any): EmailService {
        return new EmailService(data);
    }

    public save(data: EmailService, next?: (value: EmailService) => void) {
        return super.executeSave(data, next);
    }
}

export class EmailServicesPatchById extends BaseApi<EmailService> {

    public topic = 'Resources.EmailServices';
    protected method = 'patch';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/email_services/${id}`;
    }

    protected convertToResource(data: any): EmailService {
        return new EmailService(data);
    }

    public save(data: EmailService, next?: (value: EmailService) => void) {
        return super.executeSave(data, next);
    }
}

export class EmailServicesPatch extends BaseApi<EmailService> {

    public topic = 'Resources.EmailServices';
    protected method = 'patch';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/email_services`;
    }

    protected convertToResource(data: any): EmailService {
        return new EmailService(data);
    }

    public save(data: EmailService, next?: (value: EmailService) => void) {
        return super.executeSave(data, next);
    }
}

export class EmailServicesDeleteById extends BaseApi<EmailService> {

    public topic = 'Resources.EmailServices';
    protected method = 'delete';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/email_services/${id}`;
    }

    protected convertToResource(data: any): EmailService {
        return new EmailService(data);
    }

    public delete(next?: (value: EmailService) => void) {
        return super.executeDelete(next);
    }
}

class EmailServices {

    public get(): EmailServicesGet {
        return new EmailServicesGet();
    }

    public getById(id: number): EmailServicesGetById {
        return new EmailServicesGetById(id);
    }

    public post(): EmailServicesPost {
        return new EmailServicesPost();
    }

    public patchById(id: number): EmailServicesPatchById {
        return new EmailServicesPatchById(id);
    }

    public patch(): EmailServicesPatch {
        return new EmailServicesPatch();
    }

    public deleteById(id: number): EmailServicesDeleteById {
        return new EmailServicesDeleteById(id);
    }

}


export class EnvironmentsGetGet extends BaseApi<EnvironmentsGetResponse> {

    public topic = 'Resources.EnvironmentsGetResponses';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/environments`;
    }

    protected convertToResource(data: any): EnvironmentsGetResponse {
        return data;
    }

    public find(next?: (value: EnvironmentsGetResponse[]) => void) {
        return super.executeFind(next);
    }
}

class Environments {

    public getGet(): EnvironmentsGetGet {
        return new EnvironmentsGetGet();
    }

}


export class KeelHookQueueItemsGet extends BaseApi<KeelHookQueueItem> {

    public topic = 'Resources.KeelHookQueueItems';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/keel_hook_queue_items`;
    }

    protected convertToResource(data: any): KeelHookQueueItem {
        return new KeelHookQueueItem(data);
    }

    public where(name: string, value: any): KeelHookQueueItemsGet {
        this.filter().where(name, value);
        return this;
    }

    public whereEquals(name: string, value: any): KeelHookQueueItemsGet {
        this.filter().whereEquals(name, value);
        return this;
    }

    public whereIn(name: string, value: any[]): KeelHookQueueItemsGet {
        this.filter().whereIn(name, value);
        return this;
    }

    public whereInArray(name: string, value: any[]): KeelHookQueueItemsGet {
        this.filter().whereInArray(name, value);
        return this;
    }

    public whereNot(name: string, value: any): KeelHookQueueItemsGet {
        this.filter().whereNot(name, value);
        return this;
    }

    public whereNotIn(name: string, value: any[]): KeelHookQueueItemsGet {
        this.filter().whereNotIn(name, value);
        return this;
    }

    public whereGreaterThan(name: string, value: any): KeelHookQueueItemsGet {
        this.filter().whereGreaterThan(name, value);
        return this;
    }

    public whereGreaterThanOrEqual(name: string, value: any): KeelHookQueueItemsGet {
        this.filter().whereGreaterThanOrEqual(name, value);
        return this;
    }

    public whereLessThan(name: string, value: any): KeelHookQueueItemsGet {
        this.filter().whereLessThan(name, value);
        return this;
    }

    public whereLessThanOrEqual(name: string, value: any): KeelHookQueueItemsGet {
        this.filter().whereLessThanOrEqual(name, value);
        return this;
    }

    public search(name: string, value: any): KeelHookQueueItemsGet {
        this.filter().search(name, value);
        return this;
    }

    public include(name: string): KeelHookQueueItemsGet {
        this.getInclude().include(name);
        return this;
    }

    public orderBy(name: string, direction: string): KeelHookQueueItemsGet {
        this.ordering().orderBy(name, direction);
        return this;
    }

    public orderAsc(name: string): KeelHookQueueItemsGet {
        this.ordering().orderAsc(name);
        return this;
    }

    public orderDesc(name: string): KeelHookQueueItemsGet {
        this.ordering().orderDesc(name);
        return this;
    }

    public limit(value: number): KeelHookQueueItemsGet {
        this.limitValue = value;
        return this;
    }

    public offset(value: number): KeelHookQueueItemsGet {
        this.offsetValue = value;
        return this;
    }

    public count(next?: (value: number) => void) {
        return this.executeCount(next);
    }

    public find(next?: (value: KeelHookQueueItem[]) => void) {
        return super.executeFind(next);
    }
}

export class KeelHookQueueItemsGetById extends BaseApi<KeelHookQueueItem> {

    public topic = 'Resources.KeelHookQueueItems';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/keel_hook_queue_items/${id}`;
    }

    protected convertToResource(data: any): KeelHookQueueItem {
        return new KeelHookQueueItem(data);
    }

    public include(name: string): KeelHookQueueItemsGetById {
        this.getInclude().include(name);
        return this;
    }

    public find(next?: (value: KeelHookQueueItem[]) => void) {
        return super.executeFind(next);
    }
}

export class KeelHookQueueItemsRerunPostById extends BaseApi<KeelHookQueueItem> {

    public topic = 'Resources.KeelHookQueueItems';
    protected method = 'post';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/keel-hook-queue-items/${id}/rerun`;
    }

    protected convertToResource(data: any): KeelHookQueueItem {
        return new KeelHookQueueItem(data);
    }

    public save(data: any, next?: (value: KeelHookQueueItem) => void) {
        return super.executeSave(data, next);
    }
}

class KeelHookQueueItems {

    public get(): KeelHookQueueItemsGet {
        return new KeelHookQueueItemsGet();
    }

    public getById(id: number): KeelHookQueueItemsGetById {
        return new KeelHookQueueItemsGetById(id);
    }

    public rerunPostById(id: number): KeelHookQueueItemsRerunPostById {
        return new KeelHookQueueItemsRerunPostById(id);
    }

}


export class KeelPoliciesGetGet extends BaseApi<KeelPoliciesGetResponse> {

    public topic = 'Resources.KeelPoliciesGetResponses';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/keel_policies`;
    }

    protected convertToResource(data: any): KeelPoliciesGetResponse {
        return data;
    }

    public find(next?: (value: KeelPoliciesGetResponse[]) => void) {
        return super.executeFind(next);
    }
}

class KeelPolicies {

    public getGet(): KeelPoliciesGetGet {
        return new KeelPoliciesGetGet();
    }

}


export class KubernetesGetPodsGetByNamespace extends BaseApi<KubernetesPod> {

    public topic = 'Resources.KubernetesPods';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(namespace: string) {
        super();
        this.uri = `/kubernetes/namespaces/${namespace}/pods`;
    }

    protected convertToResource(data: any): KubernetesPod {
        return data;
    }

    public app(value: string): KubernetesGetPodsGetByNamespace {
        this.addQueryParameter('app', value);
        return this;
    }

    public role(value: string): KubernetesGetPodsGetByNamespace {
        this.addQueryParameter('role', value);
        return this;
    }

    public find(next?: (value: KubernetesPod[]) => void) {
        return super.executeFind(next);
    }
}

export class KubernetesExecPutByNamespaceByName extends BaseApi<KubernetesExecResponse> {

    public topic = 'Resources.KubernetesExecResponses';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(namespace: string, name: string) {
        super();
        this.uri = `/kubernetes/namespaces/${namespace}/pods/${name}/exec`;
    }

    protected convertToResource(data: any): KubernetesExecResponse {
        return data;
    }

    public command(value: string): KubernetesExecPutByNamespaceByName {
        this.addQueryParameter('command', value);
        return this;
    }

    public save(data: any, next?: (value: KubernetesExecResponse) => void) {
        return super.executeSave(data, next);
    }
}

export class KubernetesGetLogsGetByNamespaceByPod extends BaseApi<KubernetesLogEntry> {

    public topic = 'Resources.KubernetesLogEntries';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(namespace: string, pod: string) {
        super();
        this.uri = `/kubernetes/namespaces/${namespace}/pods/${pod}/logs`;
    }

    protected convertToResource(data: any): KubernetesLogEntry {
        return data;
    }

    public find(next?: (value: KubernetesLogEntry[]) => void) {
        return super.executeFind(next);
    }
}

export class KubernetesWatchLogsPutByNamespaceByPod extends BaseApi<any> {

    public topic = 'UnknownResource';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(namespace: string, pod: string) {
        super();
        this.uri = `/kubernetes/namespaces/${namespace}/pods/${pod}/logs/watch`;
    }

    protected convertToResource(data: any): any {
        return data;
    }

    public save(data: any, next?: (value: any) => void) {
        return super.executeSave(data, next);
    }
}

export class KubernetesRbacShowGet extends BaseApi<KubernetesRbacShowResponse> {

    public topic = 'Resources.KubernetesRbacShowResponses';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/kubernetes/rbac/show`;
    }

    protected convertToResource(data: any): KubernetesRbacShowResponse {
        return data;
    }

    public find(next?: (value: KubernetesRbacShowResponse[]) => void) {
        return super.executeFind(next);
    }
}

class Kubernetes {

    public getPodsGetByNamespace(namespace: string): KubernetesGetPodsGetByNamespace {
        return new KubernetesGetPodsGetByNamespace(namespace);
    }

    public execPutByNamespaceByName(namespace: string, name: string): KubernetesExecPutByNamespaceByName {
        return new KubernetesExecPutByNamespaceByName(namespace, name);
    }

    public getLogsGetByNamespaceByPod(namespace: string, pod: string): KubernetesGetLogsGetByNamespaceByPod {
        return new KubernetesGetLogsGetByNamespaceByPod(namespace, pod);
    }

    public watchLogsPutByNamespaceByPod(namespace: string, pod: string): KubernetesWatchLogsPutByNamespaceByPod {
        return new KubernetesWatchLogsPutByNamespaceByPod(namespace, pod);
    }

    public rbacShowGet(): KubernetesRbacShowGet {
        return new KubernetesRbacShowGet();
    }

}


export class MigrationJobsGet extends BaseApi<MigrationJob> {

    public topic = 'Resources.MigrationJobs';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/migration_jobs`;
    }

    protected convertToResource(data: any): MigrationJob {
        return new MigrationJob(data);
    }

    public where(name: string, value: any): MigrationJobsGet {
        this.filter().where(name, value);
        return this;
    }

    public whereEquals(name: string, value: any): MigrationJobsGet {
        this.filter().whereEquals(name, value);
        return this;
    }

    public whereIn(name: string, value: any[]): MigrationJobsGet {
        this.filter().whereIn(name, value);
        return this;
    }

    public whereInArray(name: string, value: any[]): MigrationJobsGet {
        this.filter().whereInArray(name, value);
        return this;
    }

    public whereNot(name: string, value: any): MigrationJobsGet {
        this.filter().whereNot(name, value);
        return this;
    }

    public whereNotIn(name: string, value: any[]): MigrationJobsGet {
        this.filter().whereNotIn(name, value);
        return this;
    }

    public whereGreaterThan(name: string, value: any): MigrationJobsGet {
        this.filter().whereGreaterThan(name, value);
        return this;
    }

    public whereGreaterThanOrEqual(name: string, value: any): MigrationJobsGet {
        this.filter().whereGreaterThanOrEqual(name, value);
        return this;
    }

    public whereLessThan(name: string, value: any): MigrationJobsGet {
        this.filter().whereLessThan(name, value);
        return this;
    }

    public whereLessThanOrEqual(name: string, value: any): MigrationJobsGet {
        this.filter().whereLessThanOrEqual(name, value);
        return this;
    }

    public search(name: string, value: any): MigrationJobsGet {
        this.filter().search(name, value);
        return this;
    }

    public include(name: string): MigrationJobsGet {
        this.getInclude().include(name);
        return this;
    }

    public orderBy(name: string, direction: string): MigrationJobsGet {
        this.ordering().orderBy(name, direction);
        return this;
    }

    public orderAsc(name: string): MigrationJobsGet {
        this.ordering().orderAsc(name);
        return this;
    }

    public orderDesc(name: string): MigrationJobsGet {
        this.ordering().orderDesc(name);
        return this;
    }

    public limit(value: number): MigrationJobsGet {
        this.limitValue = value;
        return this;
    }

    public offset(value: number): MigrationJobsGet {
        this.offsetValue = value;
        return this;
    }

    public count(next?: (value: number) => void) {
        return this.executeCount(next);
    }

    public find(next?: (value: MigrationJob[]) => void) {
        return super.executeFind(next);
    }
}

export class MigrationJobsGetById extends BaseApi<MigrationJob> {

    public topic = 'Resources.MigrationJobs';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/migration_jobs/${id}`;
    }

    protected convertToResource(data: any): MigrationJob {
        return new MigrationJob(data);
    }

    public include(name: string): MigrationJobsGetById {
        this.getInclude().include(name);
        return this;
    }

    public find(next?: (value: MigrationJob[]) => void) {
        return super.executeFind(next);
    }
}

export class MigrationJobsRerunPutById extends BaseApi<MigrationJob> {

    public topic = 'Resources.MigrationJobs';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/migration-jobs/${id}/rerun`;
    }

    protected convertToResource(data: any): MigrationJob {
        return new MigrationJob(data);
    }

    public save(data: any, next?: (value: MigrationJob) => void) {
        return super.executeSave(data, next);
    }
}

export class MigrationJobsSetStartedPutById extends BaseApi<MigrationJob> {

    public topic = 'Resources.MigrationJobs';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/migration-jobs/${id}/started`;
    }

    protected convertToResource(data: any): MigrationJob {
        return new MigrationJob(data);
    }

    public save(data: any, next?: (value: MigrationJob) => void) {
        return super.executeSave(data, next);
    }
}

export class MigrationJobsSetEndedPutById extends BaseApi<MigrationJob> {

    public topic = 'Resources.MigrationJobs';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/migration-jobs/${id}/ended`;
    }

    protected convertToResource(data: any): MigrationJob {
        return new MigrationJob(data);
    }

    public save(data: any, next?: (value: MigrationJob) => void) {
        return super.executeSave(data, next);
    }
}

class MigrationJobs {

    public get(): MigrationJobsGet {
        return new MigrationJobsGet();
    }

    public getById(id: number): MigrationJobsGetById {
        return new MigrationJobsGetById(id);
    }

    public rerunPutById(id: number): MigrationJobsRerunPutById {
        return new MigrationJobsRerunPutById(id);
    }

    public setStartedPutById(id: number): MigrationJobsSetStartedPutById {
        return new MigrationJobsSetStartedPutById(id);
    }

    public setEndedPutById(id: number): MigrationJobsSetEndedPutById {
        return new MigrationJobsSetEndedPutById(id);
    }

}


export class OAuthClientsGet extends BaseApi<OAuthClient> {

    public topic = 'Resources.OAuthClients';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/o_auth_clients`;
    }

    protected convertToResource(data: any): OAuthClient {
        return new OAuthClient(data);
    }

    public where(name: string, value: any): OAuthClientsGet {
        this.filter().where(name, value);
        return this;
    }

    public whereEquals(name: string, value: any): OAuthClientsGet {
        this.filter().whereEquals(name, value);
        return this;
    }

    public whereIn(name: string, value: any[]): OAuthClientsGet {
        this.filter().whereIn(name, value);
        return this;
    }

    public whereInArray(name: string, value: any[]): OAuthClientsGet {
        this.filter().whereInArray(name, value);
        return this;
    }

    public whereNot(name: string, value: any): OAuthClientsGet {
        this.filter().whereNot(name, value);
        return this;
    }

    public whereNotIn(name: string, value: any[]): OAuthClientsGet {
        this.filter().whereNotIn(name, value);
        return this;
    }

    public whereGreaterThan(name: string, value: any): OAuthClientsGet {
        this.filter().whereGreaterThan(name, value);
        return this;
    }

    public whereGreaterThanOrEqual(name: string, value: any): OAuthClientsGet {
        this.filter().whereGreaterThanOrEqual(name, value);
        return this;
    }

    public whereLessThan(name: string, value: any): OAuthClientsGet {
        this.filter().whereLessThan(name, value);
        return this;
    }

    public whereLessThanOrEqual(name: string, value: any): OAuthClientsGet {
        this.filter().whereLessThanOrEqual(name, value);
        return this;
    }

    public search(name: string, value: any): OAuthClientsGet {
        this.filter().search(name, value);
        return this;
    }

    public include(name: string): OAuthClientsGet {
        this.getInclude().include(name);
        return this;
    }

    public orderBy(name: string, direction: string): OAuthClientsGet {
        this.ordering().orderBy(name, direction);
        return this;
    }

    public orderAsc(name: string): OAuthClientsGet {
        this.ordering().orderAsc(name);
        return this;
    }

    public orderDesc(name: string): OAuthClientsGet {
        this.ordering().orderDesc(name);
        return this;
    }

    public limit(value: number): OAuthClientsGet {
        this.limitValue = value;
        return this;
    }

    public offset(value: number): OAuthClientsGet {
        this.offsetValue = value;
        return this;
    }

    public count(next?: (value: number) => void) {
        return this.executeCount(next);
    }

    public find(next?: (value: OAuthClient[]) => void) {
        return super.executeFind(next);
    }
}

export class OAuthClientsGetById extends BaseApi<OAuthClient> {

    public topic = 'Resources.OAuthClients';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/o_auth_clients/${id}`;
    }

    protected convertToResource(data: any): OAuthClient {
        return new OAuthClient(data);
    }

    public include(name: string): OAuthClientsGetById {
        this.getInclude().include(name);
        return this;
    }

    public find(next?: (value: OAuthClient[]) => void) {
        return super.executeFind(next);
    }
}

export class OAuthClientsPost extends BaseApi<OAuthClient> {

    public topic = 'Resources.OAuthClients';
    protected method = 'post';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/o_auth_clients`;
    }

    protected convertToResource(data: any): OAuthClient {
        return new OAuthClient(data);
    }

    public save(data: OAuthClient, next?: (value: OAuthClient) => void) {
        return super.executeSave(data, next);
    }
}

export class OAuthClientsPutById extends BaseApi<OAuthClient> {

    public topic = 'Resources.OAuthClients';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/o_auth_clients/${id}`;
    }

    protected convertToResource(data: any): OAuthClient {
        return new OAuthClient(data);
    }

    public save(data: OAuthClient, next?: (value: OAuthClient) => void) {
        return super.executeSave(data, next);
    }
}

export class OAuthClientsPut extends BaseApi<OAuthClient> {

    public topic = 'Resources.OAuthClients';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/o_auth_clients`;
    }

    protected convertToResource(data: any): OAuthClient {
        return new OAuthClient(data);
    }

    public save(data: OAuthClient, next?: (value: OAuthClient) => void) {
        return super.executeSave(data, next);
    }
}

export class OAuthClientsPatchById extends BaseApi<OAuthClient> {

    public topic = 'Resources.OAuthClients';
    protected method = 'patch';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/o_auth_clients/${id}`;
    }

    protected convertToResource(data: any): OAuthClient {
        return new OAuthClient(data);
    }

    public save(data: OAuthClient, next?: (value: OAuthClient) => void) {
        return super.executeSave(data, next);
    }
}

export class OAuthClientsPatch extends BaseApi<OAuthClient> {

    public topic = 'Resources.OAuthClients';
    protected method = 'patch';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/o_auth_clients`;
    }

    protected convertToResource(data: any): OAuthClient {
        return new OAuthClient(data);
    }

    public save(data: OAuthClient, next?: (value: OAuthClient) => void) {
        return super.executeSave(data, next);
    }
}

export class OAuthClientsDeleteById extends BaseApi<OAuthClient> {

    public topic = 'Resources.OAuthClients';
    protected method = 'delete';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/o_auth_clients/${id}`;
    }

    protected convertToResource(data: any): OAuthClient {
        return new OAuthClient(data);
    }

    public delete(next?: (value: OAuthClient) => void) {
        return super.executeDelete(next);
    }
}

export class OAuthClientsGetGetById extends BaseApi<OAuthClient> {

    public topic = 'Resources.OAuthClients';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(id: string) {
        super();
        this.uri = `/o_auth_clients/${id}`;
    }

    protected convertToResource(data: any): OAuthClient {
        return new OAuthClient(data);
    }

    public find(next?: (value: OAuthClient[]) => void) {
        return super.executeFind(next);
    }
}

export class OAuthClientsPatchPatchById extends BaseApi<OAuthClient> {

    public topic = 'Resources.OAuthClients';
    protected method = 'patch';
    protected scope = '';
    protected summary = '';

    public constructor(id: string) {
        super();
        this.uri = `/o_auth_clients/${id}`;
    }

    protected convertToResource(data: any): OAuthClient {
        return new OAuthClient(data);
    }

    public save(data: any, next?: (value: OAuthClient) => void) {
        return super.executeSave(data, next);
    }
}

export class OAuthClientsDeleteDeleteById extends BaseApi<OAuthClient> {

    public topic = 'Resources.OAuthClients';
    protected method = 'delete';
    protected scope = '';
    protected summary = '';

    public constructor(id: string) {
        super();
        this.uri = `/o_auth_clients/${id}`;
    }

    protected convertToResource(data: any): OAuthClient {
        return new OAuthClient(data);
    }

    public delete(next?: (value: OAuthClient) => void) {
        return super.executeDelete(next);
    }
}

class OAuthClients {

    public get(): OAuthClientsGet {
        return new OAuthClientsGet();
    }

    public getById(id: number): OAuthClientsGetById {
        return new OAuthClientsGetById(id);
    }

    public post(): OAuthClientsPost {
        return new OAuthClientsPost();
    }

    public putById(id: number): OAuthClientsPutById {
        return new OAuthClientsPutById(id);
    }

    public put(): OAuthClientsPut {
        return new OAuthClientsPut();
    }

    public patchById(id: number): OAuthClientsPatchById {
        return new OAuthClientsPatchById(id);
    }

    public patch(): OAuthClientsPatch {
        return new OAuthClientsPatch();
    }

    public deleteById(id: number): OAuthClientsDeleteById {
        return new OAuthClientsDeleteById(id);
    }

    public getGetById(id: string): OAuthClientsGetGetById {
        return new OAuthClientsGetGetById(id);
    }

    public patchPatchById(id: string): OAuthClientsPatchPatchById {
        return new OAuthClientsPatchPatchById(id);
    }

    public deleteDeleteById(id: string): OAuthClientsDeleteDeleteById {
        return new OAuthClientsDeleteDeleteById(id);
    }

}


export class UsersGet extends BaseApi<User> {

    public topic = 'Resources.Users';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/users`;
    }

    protected convertToResource(data: any): User {
        return new User(data);
    }

    public where(name: string, value: any): UsersGet {
        this.filter().where(name, value);
        return this;
    }

    public whereEquals(name: string, value: any): UsersGet {
        this.filter().whereEquals(name, value);
        return this;
    }

    public whereIn(name: string, value: any[]): UsersGet {
        this.filter().whereIn(name, value);
        return this;
    }

    public whereInArray(name: string, value: any[]): UsersGet {
        this.filter().whereInArray(name, value);
        return this;
    }

    public whereNot(name: string, value: any): UsersGet {
        this.filter().whereNot(name, value);
        return this;
    }

    public whereNotIn(name: string, value: any[]): UsersGet {
        this.filter().whereNotIn(name, value);
        return this;
    }

    public whereGreaterThan(name: string, value: any): UsersGet {
        this.filter().whereGreaterThan(name, value);
        return this;
    }

    public whereGreaterThanOrEqual(name: string, value: any): UsersGet {
        this.filter().whereGreaterThanOrEqual(name, value);
        return this;
    }

    public whereLessThan(name: string, value: any): UsersGet {
        this.filter().whereLessThan(name, value);
        return this;
    }

    public whereLessThanOrEqual(name: string, value: any): UsersGet {
        this.filter().whereLessThanOrEqual(name, value);
        return this;
    }

    public search(name: string, value: any): UsersGet {
        this.filter().search(name, value);
        return this;
    }

    public include(name: string): UsersGet {
        this.getInclude().include(name);
        return this;
    }

    public orderBy(name: string, direction: string): UsersGet {
        this.ordering().orderBy(name, direction);
        return this;
    }

    public orderAsc(name: string): UsersGet {
        this.ordering().orderAsc(name);
        return this;
    }

    public orderDesc(name: string): UsersGet {
        this.ordering().orderDesc(name);
        return this;
    }

    public limit(value: number): UsersGet {
        this.limitValue = value;
        return this;
    }

    public offset(value: number): UsersGet {
        this.offsetValue = value;
        return this;
    }

    public count(next?: (value: number) => void) {
        return this.executeCount(next);
    }

    public find(next?: (value: User[]) => void) {
        return super.executeFind(next);
    }
}

export class UsersGetById extends BaseApi<User> {

    public topic = 'Resources.Users';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/users/${id}`;
    }

    protected convertToResource(data: any): User {
        return new User(data);
    }

    public include(name: string): UsersGetById {
        this.getInclude().include(name);
        return this;
    }

    public find(next?: (value: User[]) => void) {
        return super.executeFind(next);
    }
}

export class UsersPost extends BaseApi<User> {

    public topic = 'Resources.Users';
    protected method = 'post';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/users`;
    }

    protected convertToResource(data: any): User {
        return new User(data);
    }

    public save(data: User, next?: (value: User) => void) {
        return super.executeSave(data, next);
    }
}

export class UsersPutById extends BaseApi<User> {

    public topic = 'Resources.Users';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/users/${id}`;
    }

    protected convertToResource(data: any): User {
        return new User(data);
    }

    public save(data: User, next?: (value: User) => void) {
        return super.executeSave(data, next);
    }
}

export class UsersPut extends BaseApi<User> {

    public topic = 'Resources.Users';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/users`;
    }

    protected convertToResource(data: any): User {
        return new User(data);
    }

    public save(data: User, next?: (value: User) => void) {
        return super.executeSave(data, next);
    }
}

export class UsersPatchById extends BaseApi<User> {

    public topic = 'Resources.Users';
    protected method = 'patch';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/users/${id}`;
    }

    protected convertToResource(data: any): User {
        return new User(data);
    }

    public save(data: User, next?: (value: User) => void) {
        return super.executeSave(data, next);
    }
}

export class UsersPatch extends BaseApi<User> {

    public topic = 'Resources.Users';
    protected method = 'patch';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/users`;
    }

    protected convertToResource(data: any): User {
        return new User(data);
    }

    public save(data: User, next?: (value: User) => void) {
        return super.executeSave(data, next);
    }
}

export class UsersDeleteById extends BaseApi<User> {

    public topic = 'Resources.Users';
    protected method = 'delete';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/users/${id}`;
    }

    protected convertToResource(data: any): User {
        return new User(data);
    }

    public delete(next?: (value: User) => void) {
        return super.executeDelete(next);
    }
}

export class UsersMeGet extends BaseApi<User> {

    public topic = 'Resources.Users';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/users/me`;
    }

    protected convertToResource(data: any): User {
        return new User(data);
    }

    public find(next?: (value: User[]) => void) {
        return super.executeFind(next);
    }
}

class Users {

    public get(): UsersGet {
        return new UsersGet();
    }

    public getById(id: number): UsersGetById {
        return new UsersGetById(id);
    }

    public post(): UsersPost {
        return new UsersPost();
    }

    public putById(id: number): UsersPutById {
        return new UsersPutById(id);
    }

    public put(): UsersPut {
        return new UsersPut();
    }

    public patchById(id: number): UsersPatchById {
        return new UsersPatchById(id);
    }

    public patch(): UsersPatch {
        return new UsersPatch();
    }

    public deleteById(id: number): UsersDeleteById {
        return new UsersDeleteById(id);
    }

    public meGet(): UsersMeGet {
        return new UsersMeGet();
    }

}


export class WebhooksGet extends BaseApi<Webhook> {

    public topic = 'Resources.Webhooks';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/webhooks`;
    }

    protected convertToResource(data: any): Webhook {
        return new Webhook(data);
    }

    public where(name: string, value: any): WebhooksGet {
        this.filter().where(name, value);
        return this;
    }

    public whereEquals(name: string, value: any): WebhooksGet {
        this.filter().whereEquals(name, value);
        return this;
    }

    public whereIn(name: string, value: any[]): WebhooksGet {
        this.filter().whereIn(name, value);
        return this;
    }

    public whereInArray(name: string, value: any[]): WebhooksGet {
        this.filter().whereInArray(name, value);
        return this;
    }

    public whereNot(name: string, value: any): WebhooksGet {
        this.filter().whereNot(name, value);
        return this;
    }

    public whereNotIn(name: string, value: any[]): WebhooksGet {
        this.filter().whereNotIn(name, value);
        return this;
    }

    public whereGreaterThan(name: string, value: any): WebhooksGet {
        this.filter().whereGreaterThan(name, value);
        return this;
    }

    public whereGreaterThanOrEqual(name: string, value: any): WebhooksGet {
        this.filter().whereGreaterThanOrEqual(name, value);
        return this;
    }

    public whereLessThan(name: string, value: any): WebhooksGet {
        this.filter().whereLessThan(name, value);
        return this;
    }

    public whereLessThanOrEqual(name: string, value: any): WebhooksGet {
        this.filter().whereLessThanOrEqual(name, value);
        return this;
    }

    public search(name: string, value: any): WebhooksGet {
        this.filter().search(name, value);
        return this;
    }

    public include(name: string): WebhooksGet {
        this.getInclude().include(name);
        return this;
    }

    public orderBy(name: string, direction: string): WebhooksGet {
        this.ordering().orderBy(name, direction);
        return this;
    }

    public orderAsc(name: string): WebhooksGet {
        this.ordering().orderAsc(name);
        return this;
    }

    public orderDesc(name: string): WebhooksGet {
        this.ordering().orderDesc(name);
        return this;
    }

    public limit(value: number): WebhooksGet {
        this.limitValue = value;
        return this;
    }

    public offset(value: number): WebhooksGet {
        this.offsetValue = value;
        return this;
    }

    public count(next?: (value: number) => void) {
        return this.executeCount(next);
    }

    public find(next?: (value: Webhook[]) => void) {
        return super.executeFind(next);
    }
}

export class WebhooksGetById extends BaseApi<Webhook> {

    public topic = 'Resources.Webhooks';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/webhooks/${id}`;
    }

    protected convertToResource(data: any): Webhook {
        return new Webhook(data);
    }

    public include(name: string): WebhooksGetById {
        this.getInclude().include(name);
        return this;
    }

    public find(next?: (value: Webhook[]) => void) {
        return super.executeFind(next);
    }
}

export class WebhooksPost extends BaseApi<Webhook> {

    public topic = 'Resources.Webhooks';
    protected method = 'post';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/webhooks`;
    }

    protected convertToResource(data: any): Webhook {
        return new Webhook(data);
    }

    public save(data: Webhook, next?: (value: Webhook) => void) {
        return super.executeSave(data, next);
    }
}

export class WebhooksPatchById extends BaseApi<Webhook> {

    public topic = 'Resources.Webhooks';
    protected method = 'patch';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/webhooks/${id}`;
    }

    protected convertToResource(data: any): Webhook {
        return new Webhook(data);
    }

    public save(data: Webhook, next?: (value: Webhook) => void) {
        return super.executeSave(data, next);
    }
}

export class WebhooksPatch extends BaseApi<Webhook> {

    public topic = 'Resources.Webhooks';
    protected method = 'patch';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/webhooks`;
    }

    protected convertToResource(data: any): Webhook {
        return new Webhook(data);
    }

    public save(data: Webhook, next?: (value: Webhook) => void) {
        return super.executeSave(data, next);
    }
}

export class WebhooksDeleteById extends BaseApi<Webhook> {

    public topic = 'Resources.Webhooks';
    protected method = 'delete';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/webhooks/${id}`;
    }

    protected convertToResource(data: any): Webhook {
        return new Webhook(data);
    }

    public delete(next?: (value: Webhook) => void) {
        return super.executeDelete(next);
    }
}

export class WebhooksTypesGetGet extends BaseApi<WebhookTypesGetResponse> {

    public topic = 'Resources.WebhookTypesGetResponses';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/webhooks/types`;
    }

    protected convertToResource(data: any): WebhookTypesGetResponse {
        return data;
    }

    public find(next?: (value: WebhookTypesGetResponse[]) => void) {
        return super.executeFind(next);
    }
}

export class WebhooksDeliveriesGetGetByWebhookId extends BaseApi<WebhookDelivery> {

    public topic = 'Resources.WebhookDeliveries';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(webhookId: number) {
        super();
        this.uri = `/webhooks/${webhookId}/deliveries`;
    }

    protected convertToResource(data: any): WebhookDelivery {
        return new WebhookDelivery(data);
    }

    public find(next?: (value: WebhookDelivery[]) => void) {
        return super.executeFind(next);
    }
}

export class WebhooksDeliveriesRetryPutByWebhookIdByWebhookDeliveryId extends BaseApi<WebhookDelivery> {

    public topic = 'Resources.WebhookDeliveries';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(webhookId: number, webhookDeliveryId: number) {
        super();
        this.uri = `/webhooks/${webhookId}/deliveries/${webhookDeliveryId}/retry`;
    }

    protected convertToResource(data: any): WebhookDelivery {
        return new WebhookDelivery(data);
    }

    public save(data: any, next?: (value: WebhookDelivery) => void) {
        return super.executeSave(data, next);
    }
}

class Webhooks {

    public get(): WebhooksGet {
        return new WebhooksGet();
    }

    public getById(id: number): WebhooksGetById {
        return new WebhooksGetById(id);
    }

    public post(): WebhooksPost {
        return new WebhooksPost();
    }

    public patchById(id: number): WebhooksPatchById {
        return new WebhooksPatchById(id);
    }

    public patch(): WebhooksPatch {
        return new WebhooksPatch();
    }

    public deleteById(id: number): WebhooksDeleteById {
        return new WebhooksDeleteById(id);
    }

    public typesGetGet(): WebhooksTypesGetGet {
        return new WebhooksTypesGetGet();
    }

    public deliveriesGetGetByWebhookId(webhookId: number): WebhooksDeliveriesGetGetByWebhookId {
        return new WebhooksDeliveriesGetGetByWebhookId(webhookId);
    }

    public deliveriesRetryPutByWebhookIdByWebhookDeliveryId(webhookId: number, webhookDeliveryId: number): WebhooksDeliveriesRetryPutByWebhookIdByWebhookDeliveryId {
        return new WebhooksDeliveriesRetryPutByWebhookIdByWebhookDeliveryId(webhookId, webhookDeliveryId);
    }

}


export class WorkspacesGet extends BaseApi<Workspace> {

    public topic = 'Resources.Workspaces';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/workspaces`;
    }

    protected convertToResource(data: any): Workspace {
        return new Workspace(data);
    }

    public where(name: string, value: any): WorkspacesGet {
        this.filter().where(name, value);
        return this;
    }

    public whereEquals(name: string, value: any): WorkspacesGet {
        this.filter().whereEquals(name, value);
        return this;
    }

    public whereIn(name: string, value: any[]): WorkspacesGet {
        this.filter().whereIn(name, value);
        return this;
    }

    public whereInArray(name: string, value: any[]): WorkspacesGet {
        this.filter().whereInArray(name, value);
        return this;
    }

    public whereNot(name: string, value: any): WorkspacesGet {
        this.filter().whereNot(name, value);
        return this;
    }

    public whereNotIn(name: string, value: any[]): WorkspacesGet {
        this.filter().whereNotIn(name, value);
        return this;
    }

    public whereGreaterThan(name: string, value: any): WorkspacesGet {
        this.filter().whereGreaterThan(name, value);
        return this;
    }

    public whereGreaterThanOrEqual(name: string, value: any): WorkspacesGet {
        this.filter().whereGreaterThanOrEqual(name, value);
        return this;
    }

    public whereLessThan(name: string, value: any): WorkspacesGet {
        this.filter().whereLessThan(name, value);
        return this;
    }

    public whereLessThanOrEqual(name: string, value: any): WorkspacesGet {
        this.filter().whereLessThanOrEqual(name, value);
        return this;
    }

    public search(name: string, value: any): WorkspacesGet {
        this.filter().search(name, value);
        return this;
    }

    public include(name: string): WorkspacesGet {
        this.getInclude().include(name);
        return this;
    }

    public orderBy(name: string, direction: string): WorkspacesGet {
        this.ordering().orderBy(name, direction);
        return this;
    }

    public orderAsc(name: string): WorkspacesGet {
        this.ordering().orderAsc(name);
        return this;
    }

    public orderDesc(name: string): WorkspacesGet {
        this.ordering().orderDesc(name);
        return this;
    }

    public limit(value: number): WorkspacesGet {
        this.limitValue = value;
        return this;
    }

    public offset(value: number): WorkspacesGet {
        this.offsetValue = value;
        return this;
    }

    public count(next?: (value: number) => void) {
        return this.executeCount(next);
    }

    public find(next?: (value: Workspace[]) => void) {
        return super.executeFind(next);
    }
}

export class WorkspacesGetById extends BaseApi<Workspace> {

    public topic = 'Resources.Workspaces';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/workspaces/${id}`;
    }

    protected convertToResource(data: any): Workspace {
        return new Workspace(data);
    }

    public include(name: string): WorkspacesGetById {
        this.getInclude().include(name);
        return this;
    }

    public find(next?: (value: Workspace[]) => void) {
        return super.executeFind(next);
    }
}

export class WorkspacesPatchById extends BaseApi<Workspace> {

    public topic = 'Resources.Workspaces';
    protected method = 'patch';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/workspaces/${id}`;
    }

    protected convertToResource(data: any): Workspace {
        return new Workspace(data);
    }

    public save(data: Workspace, next?: (value: Workspace) => void) {
        return super.executeSave(data, next);
    }
}

export class WorkspacesPatch extends BaseApi<Workspace> {

    public topic = 'Resources.Workspaces';
    protected method = 'patch';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/workspaces`;
    }

    protected convertToResource(data: any): Workspace {
        return new Workspace(data);
    }

    public save(data: Workspace, next?: (value: Workspace) => void) {
        return super.executeSave(data, next);
    }
}

export class WorkspacesDeleteById extends BaseApi<Workspace> {

    public topic = 'Resources.Workspaces';
    protected method = 'delete';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/workspaces/${id}`;
    }

    protected convertToResource(data: any): Workspace {
        return new Workspace(data);
    }

    public delete(next?: (value: Workspace) => void) {
        return super.executeDelete(next);
    }
}

export class WorkspacesCreatePost extends BaseApi<Workspace> {

    public topic = 'Resources.Workspaces';
    protected method = 'post';
    protected scope = '';
    protected summary = '';

    public constructor() {
        super();
        this.uri = `/workspaces/create`;
    }

    protected convertToResource(data: any): Workspace {
        return new Workspace(data);
    }

    public deploymentPackageId(value: number): WorkspacesCreatePost {
        this.addQueryParameter('deploymentPackageId', value);
        return this;
    }

    public name(value: string): WorkspacesCreatePost {
        this.addQueryParameter('name', value);
        return this;
    }

    public domainId(value: number): WorkspacesCreatePost {
        this.addQueryParameter('domainId', value);
        return this;
    }

    public subdomain(value: string): WorkspacesCreatePost {
        this.addQueryParameter('subdomain', value);
        return this;
    }

    public save(data: any, next?: (value: Workspace) => void) {
        return super.executeSave(data, next);
    }
}

export class WorkspacesCreateDeploymentPostById extends BaseApi<Deployment> {

    public topic = 'Resources.Deployments';
    protected method = 'post';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/workspaces/${id}/deployments`;
    }

    protected convertToResource(data: any): Deployment {
        return new Deployment(data);
    }

    public deploymentSpecificationId(value: number): WorkspacesCreateDeploymentPostById {
        this.addQueryParameter('deploymentSpecificationId', value);
        return this;
    }

    public name(value: string): WorkspacesCreateDeploymentPostById {
        this.addQueryParameter('name', value);
        return this;
    }

    public namespace(value: string): WorkspacesCreateDeploymentPostById {
        this.addQueryParameter('namespace', value);
        return this;
    }

    public save(data: any, next?: (value: Deployment) => void) {
        return super.executeSave(data, next);
    }
}

export class WorkspacesUpdateNamePutById extends BaseApi<Workspace> {

    public topic = 'Resources.Workspaces';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/workspaces/${id}/name`;
    }

    protected convertToResource(data: any): Workspace {
        return new Workspace(data);
    }

    public value(value: string): WorkspacesUpdateNamePutById {
        this.addQueryParameter('value', value);
        return this;
    }

    public save(data: any, next?: (value: Workspace) => void) {
        return super.executeSave(data, next);
    }
}

export class WorkspacesUpdateIngressPutById extends BaseApi<Workspace> {

    public topic = 'Resources.Workspaces';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/workspaces/${id}/ingress`;
    }

    protected convertToResource(data: any): Workspace {
        return new Workspace(data);
    }

    public domainId(value: number): WorkspacesUpdateIngressPutById {
        this.addQueryParameter('domainId', value);
        return this;
    }

    public subdomain(value: string): WorkspacesUpdateIngressPutById {
        this.addQueryParameter('subdomain', value);
        return this;
    }

    public aliases(value: string): WorkspacesUpdateIngressPutById {
        this.addQueryParameter('aliases', value);
        return this;
    }

    public save(data: any, next?: (value: Workspace) => void) {
        return super.executeSave(data, next);
    }
}

export class WorkspacesUpdateEmailServiceIdPutById extends BaseApi<Workspace> {

    public topic = 'Resources.Workspaces';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/workspaces/${id}/emailServiceId`;
    }

    protected convertToResource(data: any): Workspace {
        return new Workspace(data);
    }

    public value(value: number): WorkspacesUpdateEmailServiceIdPutById {
        this.addQueryParameter('value', value);
        return this;
    }

    public save(data: any, next?: (value: Workspace) => void) {
        return super.executeSave(data, next);
    }
}

export class WorkspacesUpdateDatabaseServiceIdPutById extends BaseApi<Workspace> {

    public topic = 'Resources.Workspaces';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/workspaces/${id}/databaseServiceId`;
    }

    protected convertToResource(data: any): Workspace {
        return new Workspace(data);
    }

    public value(value: number): WorkspacesUpdateDatabaseServiceIdPutById {
        this.addQueryParameter('value', value);
        return this;
    }

    public save(data: any, next?: (value: Workspace) => void) {
        return super.executeSave(data, next);
    }
}

export class WorkspacesDeployPutById extends BaseApi<Workspace> {

    public topic = 'Resources.Workspaces';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/workspaces/${id}/deploy`;
    }

    protected convertToResource(data: any): Workspace {
        return new Workspace(data);
    }

    public value(value: string): WorkspacesDeployPutById {
        this.addQueryParameter('value', value);
        return this;
    }

    public save(data: any, next?: (value: Workspace) => void) {
        return super.executeSave(data, next);
    }
}

export class WorkspacesTerminatePutById extends BaseApi<Workspace> {

    public topic = 'Resources.Workspaces';
    protected method = 'put';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/workspaces/${id}/terminate`;
    }

    protected convertToResource(data: any): Workspace {
        return new Workspace(data);
    }

    public value(value: string): WorkspacesTerminatePutById {
        this.addQueryParameter('value', value);
        return this;
    }

    public save(data: any, next?: (value: Workspace) => void) {
        return super.executeSave(data, next);
    }
}

export class WorkspacesGetStatusGetById extends BaseApi<Workspace> {

    public topic = 'Resources.Workspaces';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/workspaces/${id}/status`;
    }

    protected convertToResource(data: any): Workspace {
        return new Workspace(data);
    }

    public find(next?: (value: Workspace[]) => void) {
        return super.executeFind(next);
    }
}

export class WorkspacesGetMigrationJobsGetById extends BaseApi<MigrationJob> {

    public topic = 'Resources.MigrationJobs';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/workspaces/${id}/migration-jobs`;
    }

    protected convertToResource(data: any): MigrationJob {
        return new MigrationJob(data);
    }

    public find(next?: (value: MigrationJob[]) => void) {
        return super.executeFind(next);
    }
}

export class WorkspacesRequestSupportLoginGetById extends BaseApi<StringInterface> {

    public topic = 'Resources.StringInterfaces';
    protected method = 'get';
    protected scope = '';
    protected summary = '';

    public constructor(id: number) {
        super();
        this.uri = `/workspaces/${id}/requestSupportLogin`;
    }

    protected convertToResource(data: any): StringInterface {
        return data;
    }

    public redirect(value: boolean): WorkspacesRequestSupportLoginGetById {
        this.addQueryParameter('redirect', value);
        return this;
    }

    public find(next?: (value: StringInterface[]) => void) {
        return super.executeFind(next);
    }
}

class Workspaces {

    public get(): WorkspacesGet {
        return new WorkspacesGet();
    }

    public getById(id: number): WorkspacesGetById {
        return new WorkspacesGetById(id);
    }

    public patchById(id: number): WorkspacesPatchById {
        return new WorkspacesPatchById(id);
    }

    public patch(): WorkspacesPatch {
        return new WorkspacesPatch();
    }

    public deleteById(id: number): WorkspacesDeleteById {
        return new WorkspacesDeleteById(id);
    }

    public createPost(): WorkspacesCreatePost {
        return new WorkspacesCreatePost();
    }

    public createDeploymentPostById(id: number): WorkspacesCreateDeploymentPostById {
        return new WorkspacesCreateDeploymentPostById(id);
    }

    public updateNamePutById(id: number): WorkspacesUpdateNamePutById {
        return new WorkspacesUpdateNamePutById(id);
    }

    public updateIngressPutById(id: number): WorkspacesUpdateIngressPutById {
        return new WorkspacesUpdateIngressPutById(id);
    }

    public updateEmailServiceIdPutById(id: number): WorkspacesUpdateEmailServiceIdPutById {
        return new WorkspacesUpdateEmailServiceIdPutById(id);
    }

    public updateDatabaseServiceIdPutById(id: number): WorkspacesUpdateDatabaseServiceIdPutById {
        return new WorkspacesUpdateDatabaseServiceIdPutById(id);
    }

    public deployPutById(id: number): WorkspacesDeployPutById {
        return new WorkspacesDeployPutById(id);
    }

    public terminatePutById(id: number): WorkspacesTerminatePutById {
        return new WorkspacesTerminatePutById(id);
    }

    public getStatusGetById(id: number): WorkspacesGetStatusGetById {
        return new WorkspacesGetStatusGetById(id);
    }

    public getMigrationJobsGetById(id: number): WorkspacesGetMigrationJobsGetById {
        return new WorkspacesGetMigrationJobsGetById(id);
    }

    public requestSupportLoginGetById(id: number): WorkspacesRequestSupportLoginGetById {
        return new WorkspacesRequestSupportLoginGetById(id);
    }

}

export class Api {

    public static containerImages(): ContainerImages {
        return new ContainerImages();
    }

    public static databaseServices(): DatabaseServices {
        return new DatabaseServices();
    }

    public static deploymentPackages(): DeploymentPackages {
        return new DeploymentPackages();
    }

    public static deploymentSpecifications(): DeploymentSpecifications {
        return new DeploymentSpecifications();
    }

    public static deploymentSteps(): DeploymentSteps {
        return new DeploymentSteps();
    }

    public static deployments(): Deployments {
        return new Deployments();
    }

    public static domains(): Domains {
        return new Domains();
    }

    public static emailServices(): EmailServices {
        return new EmailServices();
    }

    public static environments(): Environments {
        return new Environments();
    }

    public static keelHookQueueItems(): KeelHookQueueItems {
        return new KeelHookQueueItems();
    }

    public static keelPolicies(): KeelPolicies {
        return new KeelPolicies();
    }

    public static kubernetes(): Kubernetes {
        return new Kubernetes();
    }

    public static migrationJobs(): MigrationJobs {
        return new MigrationJobs();
    }

    public static oAuthClients(): OAuthClients {
        return new OAuthClients();
    }

    public static users(): Users {
        return new Users();
    }

    public static webhooks(): Webhooks {
        return new Webhooks();
    }

    public static workspaces(): Workspaces {
        return new Workspaces();
    }

}
