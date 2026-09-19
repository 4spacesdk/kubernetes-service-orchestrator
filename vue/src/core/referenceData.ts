import { Api } from "@/core/services/Deploy/Api";
import bus from "@/plugins/bus";
import type { Events } from "@/constants";
import type {
    ContainerImage,
    ContainerRegistry,
    DatabaseService,
    DeploymentSpecification,
    Domain,
    EmailService,
    Gateway,
    GithubIntegration,
} from "@/core/services/Deploy/models";

/**
 * The lists the dialogs pick from - images, domains, gateways and the like - fetched once
 * and shared, rather than again on every dialog that opens.
 *
 * A list is dropped when its `…Saved` event says it changed, deletes included, and after a
 * minute anyway: changes made by someone else reach no event here.
 */
const MaxAge = 60 * 1000;

interface Entry {
    at: number;
    items: Promise<any[]>;
}

const cache = new Map<string, Entry>();

interface FindingApi<T> {
    find(next: (items: T[]) => void): unknown;
    setErrorHandler(handler: (response: any) => boolean): unknown;
}

function cached<T>(key: string, api: () => FindingApi<T>): Promise<T[]> {
    const entry = cache.get(key);
    if (!entry || Date.now() - entry.at > MaxAge) {
        const request = api();
        // A failed fetch answers an empty list; that must not be kept as the truth.
        request.setErrorHandler(() => {
            cache.delete(key);
            return true;
        });
        cache.set(key, { at: Date.now(), items: new Promise<T[]>(resolve => request.find(resolve)) });
    }
    // Each caller gets its own array, so one dialog adding to it does not change another's.
    return cache.get(key)!.items.then(items => [...items]);
}

export const ReferenceData = {
    containerImages: () => cached<ContainerImage>("containerImages", () => Api.containerImages().get().orderAsc("name")),
    containerRegistries: () => cached<ContainerRegistry>("containerRegistries", () => Api.containerRegistries().get().orderAsc("name")),
    databaseServices: () => cached<DatabaseService>("databaseServices", () => Api.databaseServices().get().orderAsc("name")),
    deploymentSpecifications: () =>
        cached<DeploymentSpecification>("deploymentSpecifications", () => Api.deploymentSpecifications().get().orderAsc("name")),
    deploymentSpecificationsWithImage: () =>
        cached<DeploymentSpecification>("deploymentSpecificationsWithImage", () =>
            Api.deploymentSpecifications().get().include("container_image").orderAsc("name")
        ),
    domains: () => cached<Domain>("domains", () => Api.domains().get().orderAsc("name")),
    emailServices: () => cached<EmailService>("emailServices", () => Api.emailServices().get().orderAsc("name")),
    gateways: () => cached<Gateway>("gateways", () => Api.gateways().get().orderAsc("name")),
    githubIntegrations: () => cached<GithubIntegration>("githubIntegrations", () => Api.githubIntegrations().get().orderAsc("name")),
};

const invalidatedBy: [keyof Events, string[]][] = [
    ["containerImageSaved", ["containerImages", "deploymentSpecificationsWithImage"]],
    ["containerRegistrySaved", ["containerRegistries"]],
    ["databaseServiceSaved", ["databaseServices"]],
    ["deploymentSpecificationSaved", ["deploymentSpecifications", "deploymentSpecificationsWithImage"]],
    ["domainSaved", ["domains"]],
    ["emailServiceSaved", ["emailServices"]],
    ["gatewaySaved", ["gateways"]],
    ["githubIntegrationSaved", ["githubIntegrations"]],
];
for (const [event, keys] of invalidatedBy) {
    bus.on(event, () => keys.forEach(key => cache.delete(key)));
}
