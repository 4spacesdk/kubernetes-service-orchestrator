<?php namespace App\Libraries\Kubernetes;

use App\Entities\Deployment;
use App\Entities\Gateway;
use App\Entities\System;
use App\Libraries\Kubernetes\CustomResourceDefinitions\K8sGateway;

/**
 * `${network.trustedProxies}`: the proxies in front of a deployment, for an app that reads the
 * client's address from `X-Forwarded-For` - from the right, past every proxy it trusts, and the
 * first address that is not one is the client. A comma-separated list of CIDRs and addresses.
 *
 * Who the proxies are depends on the way in, each checked against its own documentation
 * (2026-10-02):
 *
 * * **A proxy in the cluster** - ingress-nginx, Istio's gateway, Contour's Envoy, or a Gateway API
 *   class that runs in the cluster: the app sees the proxy pod's address, which is on the pod
 *   network - the nodes' `spec.podCIDRs` together. Istio's gateway and Contour (it sets Envoy's
 *   `use_remote_address`) append the address they saw; ingress-nginx, by default, replaces the
 *   header with it - either way the client is the first address not on the pod network.
 * * **GKE's own Gateway classes** (`gke-l7-*`, container-native load balancing): Google's proxies
 *   send straight to the pod, and write `<client>,<the load balancer's address>`. A global one
 *   sends from Google's front ends - `130.211.0.0/22`, `35.191.0.0/16` and `2600:2d00:1:1::/64`; a
 *   regional or internal one from the VPC's proxy-only subnet, which Kubernetes cannot tell, so the
 *   Gateway says it (Proxy source ranges). The Gateway's own addresses are trusted too.
 * * **Knative**: the queue-proxy in the pod forwards to the app on `127.0.0.1` and appends the
 *   address it saw - the activator or the ingress, both on the pod network.
 *
 * What cannot be told refuses the deploy rather than guess: an empty list makes every user one,
 * and one too wide lets a client choose its own address.
 */
class TrustedProxies {

    public const string Placeholder = '${network.trustedProxies}';

    /** Where Google's front ends send from, to a global external Application Load Balancer's backends. */
    public const array GoogleFrontEnds = ['130.211.0.0/22', '35.191.0.0/16', '2600:2d00:1:1::/64'];

    /** The GKE Gateway classes of a global load balancer; every other `gke-l7-*` class is regional or internal. */
    public const array GkeGlobalClasses = [
        'gke-l7-global-external-managed',
        'gke-l7-global-external-managed-mc',
        'gke-l7-gxlb',
        'gke-l7-gxlb-mc',
    ];

    private const string GkeClassPrefix = 'gke-l7-';

    /**
     * The cluster's nodes, as arrays. A test's own nodes in place of the cluster's.
     *
     * @var (\Closure(): list<array>)|null
     */
    public static ?\Closure $nodes = null;

    /**
     * A Gateway as the cluster has it, as an array, or null. A test's own in place of the cluster's.
     *
     * @var (\Closure(Gateway): ?array)|null
     */
    public static ?\Closure $gateway = null;

    /** @var array<int, array{0: int, 1: string}> deployment id => [when, the list] */
    private static array $worked = [];

    /**
     * The list for the deployment - worked out once a minute at most, as a deploy fills in every
     * variable and every container on its own.
     *
     * @throws \RuntimeException with what is missing, when it cannot be told
     */
    public static function For(Deployment $deployment): string {
        $id = (int) $deployment->id;
        if (isset(self::$worked[$id]) && self::$worked[$id][0] > time() - 60) {
            return self::$worked[$id][1];
        }
        $list = implode(',', self::Ranges($deployment));
        self::$worked[$id] = [time(), $list];

        return $list;
    }

    /**
     * The way in, in words - shown beside the list.
     */
    public static function Describe(Deployment $deployment): string {
        $spec = $deployment->findDeploymentSpecification();
        $gateway = self::GatewayOf($deployment);
        $way = match (true) {
            $gateway !== null && self::IsGke($gateway) && self::IsGlobal($gateway) => "a global GKE load balancer (Gateway {$gateway->name}, {$gateway->gateway_class_name})",
            $gateway !== null && self::IsGke($gateway) => "a regional or internal GKE load balancer (Gateway {$gateway->name}, {$gateway->gateway_class_name})",
            $gateway !== null => "a proxy in the cluster (Gateway {$gateway->name}, {$gateway->gateway_class_name})",
            default => "a proxy in the cluster ({$spec->network_type})",
        };

        return $spec->workload_type === \WorkloadTypes::KNativeService ? "{$way}, then Knative's queue-proxy" : $way;
    }

    public static function ForgetWorkedOut(): void {
        self::$worked = [];
    }

    /**
     * @return list<string>
     */
    private static function Ranges(Deployment $deployment): array {
        $spec = $deployment->findDeploymentSpecification();
        $gateway = self::GatewayOf($deployment);
        $ranges = [];

        if ($gateway !== null && self::IsGke($gateway)) {
            if (self::IsGlobal($gateway)) {
                $ranges = self::GoogleFrontEnds;
            } else {
                $ranges = self::Split((string) $gateway->proxy_source_ranges);
                if ($ranges === []) {
                    throw new \RuntimeException("The Gateway {$gateway->name} is a regional or internal GKE load balancer ({$gateway->gateway_class_name}), whose proxies send from the VPC's proxy-only subnet - kso cannot read it from Kubernetes. Set Proxy source ranges on the Gateway");
                }
            }
            $ranges = [...$ranges, ...self::AddressesOf($gateway)];
        } else {
            $ranges = self::PodNetwork();
        }

        if ($spec->workload_type === \WorkloadTypes::KNativeService) {
            // The queue-proxy forwards to the app on localhost; the activator and the ingress in
            // front of it are on the pod network.
            $ranges = [...$ranges, '127.0.0.1', '::1', ...($gateway !== null && self::IsGke($gateway) ? self::PodNetwork() : [])];
        }

        return array_values(array_unique($ranges));
    }

    /**
     * The pod network: the System's setting when it has one, the nodes' `podCIDRs` otherwise.
     *
     * @return list<string>
     */
    private static function PodNetwork(): array {
        $setting = self::Split((string) (System::Get()->pod_network ?? ''));
        if ($setting !== []) {
            return $setting;
        }

        $nodes = self::$nodes !== null ? (self::$nodes)() : self::NodesInTheCluster();
        $ranges = [];
        foreach ($nodes as $node) {
            $cidrs = $node['spec']['podCIDRs'] ?? (isset($node['spec']['podCIDR']) ? [$node['spec']['podCIDR']] : []);
            if ($cidrs === []) {
                $name = $node['metadata']['name'] ?? 'a node';
                throw new \RuntimeException("The node {$name} says nothing of its pod network (spec.podCIDRs) - its network plugin keeps it elsewhere. Set Pod network under System");
            }
            $ranges = [...$ranges, ...$cidrs];
        }
        if ($ranges === []) {
            throw new \RuntimeException('The cluster has no nodes to read the pod network from. Set Pod network under System');
        }

        return array_values(array_unique($ranges));
    }

    /**
     * @return list<string>
     */
    private static function AddressesOf(Gateway $gateway): array {
        $resource = self::$gateway !== null ? (self::$gateway)($gateway) : self::GatewayInTheCluster($gateway);
        $addresses = array_values(array_filter(array_map(
            fn(array $address) => (string) ($address['value'] ?? ''),
            $resource['status']['addresses'] ?? []
        )));
        if ($addresses === []) {
            throw new \RuntimeException("The Gateway {$gateway->name} has no address yet - its load balancer writes its own address into X-Forwarded-For, and it must be trusted. Deploy the Gateway, and wait for GKE to give it one");
        }

        return $addresses;
    }

    private static function GatewayOf(Deployment $deployment): ?Gateway {
        $spec = $deployment->findDeploymentSpecification();
        if ($spec->network_type !== \NetworkTypes::GatewayApi || !$deployment->workspace_id) {
            return null;
        }
        if (!$deployment->workspace->exists()) {
            $deployment->workspace->find();
        }
        $domain = $deployment->workspace->domain;
        if (!$domain->exists() && $deployment->workspace->domain_id) {
            $domain->find();
        }
        if (!$domain->exists()) {
            return null;
        }
        if (!$domain->gateway->exists() && $domain->gateway_id) {
            $domain->gateway->find();
        }

        return $domain->gateway->exists() ? $domain->gateway : null;
    }

    private static function IsGke(Gateway $gateway): bool {
        return str_starts_with((string) $gateway->gateway_class_name, self::GkeClassPrefix);
    }

    private static function IsGlobal(Gateway $gateway): bool {
        return in_array((string) $gateway->gateway_class_name, self::GkeGlobalClasses, true);
    }

    /**
     * @return list<string>
     */
    private static function Split(string $list): array {
        return array_values(array_filter(array_map('trim', preg_split('/[,\s]+/', $list))));
    }

    /**
     * @return list<array>
     */
    private static function NodesInTheCluster(): array {
        $nodes = [];
        foreach ((new KubeAuth())->authenticate()->node()->all() as $node) {
            $nodes[] = $node->toArray();
        }
        return $nodes;
    }

    private static function GatewayInTheCluster(Gateway $gateway): ?array {
        try {
            return (new K8sGateway((new KubeAuth())->authenticate()))
                ->setNamespace($gateway->namespace)
                ->setName($gateway->name)
                ->get()
                ->toArray();
        } catch (\Throwable) {
            return null;
        }
    }

}
