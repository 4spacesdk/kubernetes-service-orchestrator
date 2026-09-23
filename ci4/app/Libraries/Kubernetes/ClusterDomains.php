<?php namespace App\Libraries\Kubernetes;

use App\Entities\Domain;
use App\Libraries\GatewaySteps\ClusterGateways;
use App\Libraries\Kubernetes\CustomResourceDefinitions\K8sCertificate;
use App\Models\DomainModel;
use RenokiCo\PhpK8s\KubernetesCluster;

/**
 * The cluster's cert-manager Certificates held up against kso's domains - a kso domain is, in the
 * cluster, the Certificate for it and its subdomains - and taking over one kso does not have.
 *
 * As with gateways (`ClusterGateways`), **an import is a takeover**: kso's next apply of the
 * certificate replaces its spec with kso's, and the plan says what that would change before
 * anything is written. The one change that matters most is the secret: kso names it after the
 * certificate, and a Certificate whose secret is called something else would move it - everything
 * that mounts the old one loses its TLS.
 *
 * The domain is linked to the kso gateway whose Gateway in the cluster has a listener for it.
 *
 * `Compare()` is pure - arrays in, arrays out - and tested in the unit suite.
 */
class ClusterDomains {

    public const string Known = 'known';
    public const string Unknown = 'unknown';
    /**
     * Every name on it is inside the cluster - `….svc`, `….cluster.local` - such as an operator's
     * webhook certificate. Not a domain, and not offered as one.
     */
    public const string Internal = 'internal';
    /** Another kso installation's, sharing the cluster - not this one's to take over. */
    public const string Theirs = 'theirs';

    public function __construct(
        private readonly KubernetesCluster $cluster,
    ) {
    }

    /**
     * @return list<array>
     * @throws \Throwable when the cluster cannot be read
     */
    public function list(): array {
        return self::Compare($this->certificates(), self::KnownDomains(), $this->gatewayHostnames());
    }

    /**
     * Take over one Certificate kso does not have, as a domain. Writes the row and applies nothing.
     *
     * @throws \InvalidArgumentException when it is not there, kso has it, or kso has its domain
     */
    public function import(string $namespace, string $name): Domain {
        $row = null;
        foreach ($this->list() as $candidate) {
            if ($candidate['namespace'] === $namespace && $candidate['name'] === $name) {
                $row = $candidate;
            }
        }
        if ($row === null) {
            throw new \InvalidArgumentException("There is no Certificate {$namespace}/{$name} in the cluster");
        }
        if ($row['status'] === self::Known) {
            throw new \InvalidArgumentException("kso already has the Certificate {$namespace}/{$name}");
        }
        if ($row['status'] === self::Theirs) {
            throw new \InvalidArgumentException("{$namespace}/{$name} belongs to another kso");
        }
        if ($row['status'] === self::Internal) {
            throw new \InvalidArgumentException("{$namespace}/{$name} is for names inside the cluster, not a domain");
        }
        if ($row['plan']['conflict'] !== null) {
            throw new \InvalidArgumentException($row['plan']['conflict']);
        }

        $domain = new Domain();
        $domain->name = $row['plan']['domain'];
        $domain->certificate_name = $name;
        $domain->certificate_namespace = $namespace;
        $domain->issuer_ref_name = $row['issuer'];
        $domain->gateway_id = $row['plan']['gateway']['id'] ?? null;
        $domain->save();

        return $domain;
    }

    /**
     * @param list<array> $certificates The Certificates as the cluster has them
     * @param list<array{id: int, name: string, certificate_name: string, certificate_namespace: string, issuer_ref_name: string}> $known
     * @param array<string, array{id: int, name: string}> $gatewayHostnames hostname => the kso gateway
     *   whose Gateway listens for it
     * @return list<array>
     */
    public static function Compare(array $certificates, array $known, array $gatewayHostnames): array {
        $rows = [];
        foreach ($certificates as $certificate) {
            $namespace = (string) ($certificate['metadata']['namespace'] ?? '');
            $name = (string) ($certificate['metadata']['name'] ?? '');
            $spec = $certificate['spec'] ?? [];
            $dnsNames = array_values(array_map('strval', $spec['dnsNames'] ?? []));
            $issuer = (string) ($spec['issuerRef']['name'] ?? '');

            $mine = null;
            foreach ($known as $domain) {
                if ($domain['certificate_name'] === $name && $domain['certificate_namespace'] === $namespace) {
                    $mine = $domain;
                }
            }

            $ready = null;
            foreach ($certificate['status']['conditions'] ?? [] as $condition) {
                if (($condition['type'] ?? null) === 'Ready') {
                    $ready = ($condition['status'] ?? null) === 'True';
                }
            }

            $row = [
                'namespace' => $namespace,
                'name' => $name,
                'dns_names' => $dnsNames,
                'issuer' => $issuer,
                'secret_name' => (string) ($spec['secretName'] ?? ''),
                'ready' => $ready,
                'not_after' => $certificate['status']['notAfter'] ?? null,
                'status' => match (true) {
                    $mine !== null => self::Known,
                    KubeHelper::OwnerOf($certificate['metadata']['annotations'] ?? []) === 'theirs' => self::Theirs,
                    self::IsInternal($dnsNames) => self::Internal,
                    default => self::Unknown,
                },
                'domain_id' => $mine['id'] ?? null,
                'differences' => [],
                'plan' => null,
            ];

            if ($mine !== null) {
                $row['differences'] = self::Changes($spec, KubeCertificate::Spec($mine['name'], $name, $mine['issuer_ref_name']), 'kso');
            } else if ($row['status'] === self::Unknown) {
                $domain = self::DomainOf($dnsNames);
                $conflict = null;
                foreach ($known as $other) {
                    if ($domain !== null && $other['name'] === $domain) {
                        $conflict = "kso already has the domain {$domain}, on the certificate {$other['certificate_namespace']}/{$other['certificate_name']}";
                    }
                }
                $row['plan'] = [
                    'domain' => $domain,
                    'gateway' => $domain !== null ? ($gatewayHostnames[$domain] ?? $gatewayHostnames["*.{$domain}"] ?? null) : null,
                    'conflict' => $domain === null ? 'It names no host kso can make a domain of' : $conflict,
                    'changes' => $domain === null ? [] : self::Changes($spec, KubeCertificate::Spec($domain, $name, $issuer), 'the next apply'),
                ];
            }

            $rows[] = $row;
        }

        // What can be taken over first, then kso's own, then the cluster's internal ones.
        $order = [self::Unknown => 0, self::Known => 1, self::Theirs => 2, self::Internal => 3];
        usort($rows, fn(array $a, array $b) => [$order[$a['status']], $a['namespace'], $a['name']] <=> [$order[$b['status']], $b['namespace'], $b['name']]);

        return $rows;
    }

    /**
     * @param list<string> $dnsNames
     */
    public static function IsInternal(array $dnsNames): bool {
        foreach ($dnsNames as $dnsName) {
            if (!preg_match('/\.(svc|cluster\.local)$/', $dnsName)) {
                return false;
            }
        }
        return $dnsNames !== [];
    }

    /**
     * The domain a Certificate is for: its shortest name that is not a wildcard, or what a lone
     * wildcard is for. Null when it names no host at all.
     *
     * @param list<string> $dnsNames
     */
    public static function DomainOf(array $dnsNames): ?string {
        $plain = array_values(array_filter($dnsNames, fn(string $n) => !str_starts_with($n, '*.')));
        if ($plain) {
            usort($plain, fn(string $a, string $b) => strlen($a) <=> strlen($b));
            return $plain[0];
        }
        foreach ($dnsNames as $dnsName) {
            return substr($dnsName, 2);
        }
        return null;
    }

    /**
     * What differs between the Certificate in the cluster and the one kso would write, in words.
     *
     * @return list<string>
     */
    private static function Changes(array $inCluster, array $kso, string $who): array {
        $changes = [];

        $now = $inCluster['dnsNames'] ?? [];
        foreach (array_diff($now, $kso['dnsNames']) as $dnsName) {
            $changes[] = "{$dnsName} is on the certificate and not in {$who}'s";
        }
        foreach (array_diff($kso['dnsNames'], $now) as $dnsName) {
            $changes[] = "{$dnsName} is added by {$who}";
        }

        $secret = (string) ($inCluster['secretName'] ?? '');
        if ($secret !== $kso['secretName']) {
            $changes[] = "The secret is {$secret}, and {$who} names it {$kso['secretName']} - whatever mounts {$secret} loses its certificate";
        }

        $issuer = (string) ($inCluster['issuerRef']['name'] ?? '');
        if ($issuer !== $kso['issuerRef']['name']) {
            $changes[] = "The issuer is {$issuer}, and {$kso['issuerRef']['name']} in {$who}";
        }

        return $changes;
    }

    /**
     * @return list<array>
     */
    private function certificates(): array {
        $certificates = [];
        foreach ((new K8sCertificate($this->cluster))->allNamespaces([]) as $certificate) {
            $certificates[] = $certificate->toArray();
        }
        return $certificates;
    }

    /**
     * Which kso gateway's Gateway listens for which hostname - what a taken-over domain is linked to.
     *
     * @return array<string, array{id: int, name: string}>
     */
    private function gatewayHostnames(): array {
        try {
            $gateways = (new ClusterGateways($this->cluster))->list();
        } catch (\Throwable) {
            // A cluster without the Gateway API has no gateway to link to.
            return [];
        }

        $hostnames = [];
        foreach ($gateways as $gateway) {
            if ($gateway['status'] !== ClusterGateways::Known) {
                continue;
            }
            foreach ($gateway['listeners'] as $listener) {
                if ($listener['hostname']) {
                    $hostnames[$listener['hostname']] = ['id' => (int) $gateway['gateway_id'], 'name' => $gateway['name']];
                }
            }
        }
        return $hostnames;
    }

    /**
     * @return list<array>
     */
    private static function KnownDomains(): array {
        $known = [];
        /** @var Domain $domains */
        $domains = (new DomainModel())->find();
        foreach ($domains as $domain) {
            $known[] = [
                'id' => (int) $domain->id,
                'name' => (string) $domain->name,
                'certificate_name' => (string) $domain->certificate_name,
                'certificate_namespace' => (string) $domain->certificate_namespace,
                'issuer_ref_name' => (string) $domain->issuer_ref_name,
            ];
        }
        return $known;
    }

}
