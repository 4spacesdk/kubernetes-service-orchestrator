<?php namespace App\Libraries\GatewaySteps;

use App\Entities\Domain;
use App\Entities\Gateway;
use App\Entities\GatewayAddress;
use App\Entities\GatewayAnnotation;
use App\Libraries\Kubernetes\CustomResourceDefinitions\K8sGateway;
use App\Models\DomainModel;
use App\Models\GatewayModel;
use Config\Database;
use RenokiCo\PhpK8s\KubernetesCluster;

/**
 * The cluster's Gateways held up against kso's: which kso has, which it does not, and what the
 * next Deploy would change - and taking over one kso does not have.
 *
 * **An import is a takeover.** kso owns what it imports and may terminate it, and its next Deploy
 * replaces the Gateway's listeners with the ones kso builds from its domains. So the plan says what
 * that Deploy would remove and add before anything is written, and the import writes only kso's
 * rows: nothing is applied to the cluster until somebody presses Deploy.
 *
 * `Compare()` and `Plan()` are pure - arrays in, arrays out - so they are tested in the unit suite.
 */
class ClusterGateways {

    public const string Known = 'known';
    public const string Unknown = 'unknown';
    /** kso's mark is on it, and kso has no row: deleted in kso and left in the cluster. */
    public const string Orphan = 'orphan';

    private const string ManagedBy = 'app.kubernetes.io/managed-by';

    /** Annotations that are bookkeeping, not settings - not taken over on import. */
    private const array IgnoredAnnotations = [self::ManagedBy, 'kubectl.kubernetes.io/last-applied-configuration'];

    public function __construct(
        private readonly KubernetesCluster $cluster,
    ) {
    }

    /**
     * @return list<array>
     * @throws \Throwable when the cluster cannot be read
     */
    public function list(): array {
        return self::Compare($this->inCluster(), self::KnownGateways(), self::Domains());
    }

    /**
     * Take over one Gateway kso does not have. Writes kso's rows - the gateway, its addresses and
     * annotations, and the domains its listeners match - and applies nothing.
     *
     * @throws \InvalidArgumentException when it is not there, or kso has it already
     */
    public function import(string $namespace, string $name): Gateway {
        $row = null;
        foreach (self::Compare($this->inCluster(), self::KnownGateways(), self::Domains()) as $candidate) {
            if ($candidate['namespace'] === $namespace && $candidate['name'] === $name) {
                $row = $candidate;
            }
        }
        if ($row === null) {
            throw new \InvalidArgumentException("There is no Gateway {$namespace}/{$name} in the cluster");
        }
        if ($row['status'] === self::Known) {
            throw new \InvalidArgumentException("kso already has the Gateway {$namespace}/{$name}");
        }

        $db = Database::connect();
        $db->transException(true)->transStart();

        $gateway = new Gateway();
        $gateway->name = $name;
        $gateway->namespace = $namespace;
        $gateway->gateway_class_name = $row['gateway_class_name'];
        $gateway->save();

        foreach ($row['addresses'] as $address) {
            $entity = new GatewayAddress();
            $entity->gateway_id = $gateway->id;
            $entity->type = $address['type'];
            $entity->value = $address['value'];
            $entity->save();
        }
        foreach ($row['annotations'] as $annotationName => $value) {
            $entity = new GatewayAnnotation();
            $entity->gateway_id = $gateway->id;
            $entity->name = $annotationName;
            $entity->value = $value;
            $entity->save();
        }
        foreach ($row['plan']['domains'] as $match) {
            $domain = new Domain();
            $domain->find($match['id']);
            $domain->gateway_id = $gateway->id;
            $domain->save();
        }

        $db->transComplete();

        return $gateway;
    }

    /**
     * @param list<array> $inCluster The Gateways as the cluster has them
     * @param list<array{id: int, name: string, namespace: string, gateway_class_name: string, addresses: list<array>}> $known
     * @param list<array{id: int, name: string, gateway_id: ?int, certificate_name: string, certificate_namespace: string}> $domains
     * @return list<array>
     */
    public static function Compare(array $inCluster, array $known, array $domains): array {
        $knownByKey = [];
        foreach ($known as $gateway) {
            $knownByKey["{$gateway['namespace']}/{$gateway['name']}"] = $gateway;
        }
        $gatewayNames = array_column($known, 'name', 'id');

        $rows = [];
        foreach ($inCluster as $resource) {
            $namespace = (string) ($resource['metadata']['namespace'] ?? '');
            $name = (string) ($resource['metadata']['name'] ?? '');
            $spec = $resource['spec'] ?? [];
            $mine = $knownByKey["{$namespace}/{$name}"] ?? null;
            $annotations = $resource['metadata']['annotations'] ?? [];

            $row = [
                'namespace' => $namespace,
                'name' => $name,
                'gateway_class_name' => (string) ($spec['gatewayClassName'] ?? ''),
                'addresses' => array_map(fn(array $a) => ['type' => (string) ($a['type'] ?? 'IPAddress'), 'value' => (string) ($a['value'] ?? '')], $spec['addresses'] ?? []),
                'annotations' => array_diff_key($annotations, array_flip(self::IgnoredAnnotations)),
                'listeners' => array_map(fn(array $l) => self::Listener($l, $namespace), $spec['listeners'] ?? []),
                'status' => $mine !== null ? self::Known : ((($annotations[self::ManagedBy] ?? null) === '4spaces.kso') ? self::Orphan : self::Unknown),
                'gateway_id' => $mine['id'] ?? null,
                'differences' => [],
                'plan' => null,
            ];

            if ($mine !== null) {
                $linked = array_values(array_filter($domains, fn(array $d) => (int) $d['gateway_id'] === (int) $mine['id']));
                $row['differences'] = self::Differences($row, $mine, K8sGateway::Listeners($linked, $namespace));
            } else {
                $row['plan'] = self::Plan($row, $domains, $gatewayNames);
            }

            $rows[] = $row;
        }

        usort($rows, fn(array $a, array $b) => [$a['status'] === self::Known, $a['namespace'], $a['name']] <=> [$b['status'] === self::Known, $b['namespace'], $b['name']]);

        return $rows;
    }

    /**
     * What taking a Gateway over would do: the kso domains its listeners match, which are linked to
     * it; those already on another kso gateway, which are left there; and what the next Deploy
     * would remove from the listeners and add to them.
     *
     * @param array $row A row as `Compare()` builds it
     * @param list<array> $domains Every kso domain
     * @param array<int, string> $gatewayNames kso gateway id => name
     */
    public static function Plan(array $row, array $domains, array $gatewayNames): array {
        $hostnames = array_filter(array_column($row['listeners'], 'hostname'));

        $matched = [];
        $elsewhere = [];
        foreach ($domains as $domain) {
            if (!in_array($domain['name'], $hostnames, true) && !in_array("*.{$domain['name']}", $hostnames, true)) {
                continue;
            }
            if ($domain['gateway_id'] && isset($gatewayNames[$domain['gateway_id']])) {
                $elsewhere[] = ['id' => (int) $domain['id'], 'name' => $domain['name'], 'gateway' => $gatewayNames[$domain['gateway_id']]];
            } else {
                $matched[] = $domain;
            }
        }

        $wouldBe = array_map(fn(array $l) => self::Listener($l, $row['namespace']), K8sGateway::Listeners($matched, $row['namespace']));

        return [
            'domains' => array_map(fn(array $d) => ['id' => (int) $d['id'], 'name' => $d['name']], $matched),
            'domains_elsewhere' => $elsewhere,
            ...self::ListenerChanges($row['listeners'], $wouldBe),
        ];
    }

    /**
     * @return list<string>
     */
    private static function Differences(array $row, array $mine, array $expectedListeners): array {
        $differences = [];
        if ($row['gateway_class_name'] !== $mine['gateway_class_name']) {
            $differences[] = "The class is {$row['gateway_class_name']} in the cluster and {$mine['gateway_class_name']} in kso";
        }

        $addresses = fn(array $list) => array_map(fn(array $a) => "{$a['type']}:{$a['value']}", $list);
        $inCluster = $addresses($row['addresses']);
        $inKso = $addresses($mine['addresses']);
        sort($inCluster);
        sort($inKso);
        if ($inCluster !== $inKso) {
            $differences[] = 'The addresses are ' . (implode(', ', $inCluster) ?: 'none') . ' in the cluster and ' . (implode(', ', $inKso) ?: 'none') . ' in kso';
        }

        $changes = self::ListenerChanges($row['listeners'], array_map(fn(array $l) => self::Listener($l, $row['namespace']), $expectedListeners));
        foreach ($changes['listeners_removed'] as $listener) {
            $differences[] = "The listener {$listener} is in the cluster and not in kso";
        }
        foreach ($changes['listeners_added'] as $listener) {
            $differences[] = "The listener {$listener} is in kso and not in the cluster";
        }

        return $differences;
    }

    /**
     * @param list<array> $now
     * @param list<array> $wouldBe
     * @return array{listeners_removed: list<string>, listeners_added: list<string>}
     */
    private static function ListenerChanges(array $now, array $wouldBe): array {
        $key = fn(array $l) => "{$l['name']} ({$l['protocol']} {$l['port']}" . ($l['hostname'] ? " {$l['hostname']}" : '') . ($l['certificate'] ? ", {$l['certificate']}" : '') . ')';
        $nowKeys = array_map($key, $now);
        $wouldBeKeys = array_map($key, $wouldBe);

        return [
            'listeners_removed' => array_values(array_diff($nowKeys, $wouldBeKeys)),
            'listeners_added' => array_values(array_diff($wouldBeKeys, $nowKeys)),
        ];
    }

    /**
     * A certificate named without a namespace is in the Gateway's own - kso writes it out, the
     * cluster may not, and the two are the same certificate.
     *
     * @return array{name: string, protocol: string, port: int, hostname: ?string, certificate: ?string}
     */
    private static function Listener(array $listener, string $gatewayNamespace): array {
        $ref = $listener['tls']['certificateRefs'][0] ?? null;
        return [
            'name' => (string) ($listener['name'] ?? ''),
            'protocol' => (string) ($listener['protocol'] ?? ''),
            'port' => (int) ($listener['port'] ?? 0),
            'hostname' => $listener['hostname'] ?? null,
            'certificate' => $ref ? (($ref['namespace'] ?? '') ?: $gatewayNamespace) . '/' . ($ref['name'] ?? '') : null,
        ];
    }

    /**
     * @return list<array>
     */
    private function inCluster(): array {
        $gateways = [];
        foreach ((new K8sGateway($this->cluster))->allNamespaces([]) as $gateway) {
            $gateways[] = $gateway->toArray();
        }
        return $gateways;
    }

    /**
     * @return list<array>
     */
    private static function KnownGateways(): array {
        $known = [];
        /** @var Gateway $gateways */
        $gateways = (new GatewayModel())->find();
        foreach ($gateways as $gateway) {
            $gateway->gateway_addresses->find();
            $addresses = [];
            foreach ($gateway->gateway_addresses as $address) {
                $addresses[] = ['type' => (string) $address->type, 'value' => (string) $address->value];
            }
            $known[] = [
                'id' => (int) $gateway->id,
                'name' => (string) $gateway->name,
                'namespace' => (string) $gateway->namespace,
                'gateway_class_name' => (string) $gateway->gateway_class_name,
                'addresses' => $addresses,
            ];
        }
        return $known;
    }

    /**
     * @return list<array>
     */
    private static function Domains(): array {
        $domains = [];
        /** @var Domain $all */
        $all = (new DomainModel())->find();
        foreach ($all as $domain) {
            $domains[] = [
                'id' => (int) $domain->id,
                'name' => (string) $domain->name,
                'gateway_id' => $domain->gateway_id ? (int) $domain->gateway_id : null,
                'certificate_name' => (string) $domain->certificate_name,
                'certificate_namespace' => (string) $domain->certificate_namespace,
            ];
        }
        return $domains;
    }

}
