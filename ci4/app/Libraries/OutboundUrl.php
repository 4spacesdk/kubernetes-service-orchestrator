<?php namespace App\Libraries;

/**
 * A url an operator typed, made safe to call from inside kso's pod.
 *
 * A webhook's url went to curl as it was, and the answer was stored and shown in the delivery
 * log. `file:///proc/self/environ` returned kso's own environment - the database password and
 * the encryption key - and `http://169.254.169.254/` the cloud's metadata service, with the
 * node's credentials.
 *
 * So: http and https only, no redirects, and the host's addresses looked up and checked here -
 * not loopback (kso itself, and the Centrifugo API beside it), not link-local (the metadata
 * services), not unspecified or multicast. The checked addresses are then pinned on the handle
 * with `CURLOPT_RESOLVE`, so a name that resolves to something else by the time curl connects
 * cannot slip past the check.
 *
 * Private addresses are allowed: a webhook to a service in the same cluster, or on the same
 * network, is an ordinary thing to want.
 */
class OutboundUrl {

    private const array Schemes = ['http' => 80, 'https' => 443];

    /**
     * Sets the url and the restrictions on the handle.
     *
     * @throws \InvalidArgumentException with the reason, when the url may not be called
     */
    public static function Apply(\CurlHandle $ch, string $url): void {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!isset(self::Schemes[$scheme])) {
            throw new \InvalidArgumentException('Only http and https urls can be called');
        }
        $host = (string) ($parts['host'] ?? '');
        if ($host === '') {
            throw new \InvalidArgumentException('The url has no host');
        }
        $port = (int) ($parts['port'] ?? self::Schemes[$scheme]);

        $addresses = self::Resolve($host);
        if ($addresses === []) {
            throw new \InvalidArgumentException("{$host} cannot be resolved");
        }
        foreach ($addresses as $address) {
            if (!self::MayBeCalled($address)) {
                throw new \InvalidArgumentException("{$host} is {$address}, which kso does not call");
            }
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_PROTOCOLS_STR, 'http,https');
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS_STR, 'http,https');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        // A name, not an address in the url: pin it to what was checked.
        if (!filter_var(trim($host, '[]'), FILTER_VALIDATE_IP)) {
            curl_setopt($ch, CURLOPT_RESOLVE, [
                "{$host}:{$port}:" . implode(',', array_map(
                    fn (string $address) => str_contains($address, ':') ? "[{$address}]" : $address,
                    $addresses
                )),
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private static function Resolve(string $host): array {
        $literal = trim($host, '[]');
        if (filter_var($literal, FILTER_VALIDATE_IP)) {
            return [$literal];
        }

        $addresses = gethostbynamel($host) ?: [];
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }

    public static function MayBeCalled(string $address): bool {
        $binary = @inet_pton($address);
        if ($binary === false) {
            return false;
        }

        // An IPv4 address written as IPv6 (::ffff:a.b.c.d) is judged as the IPv4 one.
        if (strlen($binary) === 16 && str_starts_with($binary, str_repeat("\0", 10) . "\xff\xff")) {
            $binary = substr($binary, 12);
        }

        $refused = strlen($binary) === 4
            ? ['0.0.0.0/8', '127.0.0.0/8', '169.254.0.0/16', '224.0.0.0/4', '255.255.255.255/32']
            : ['::/128', '::1/128', 'fe80::/10', 'ff00::/8', 'fd00:ec2::254/128'];

        foreach ($refused as $range) {
            if (self::InRange($binary, $range)) {
                return false;
            }
        }
        return true;
    }

    private static function InRange(string $binary, string $range): bool {
        [$network, $bits] = explode('/', $range);
        $networkBinary = inet_pton($network);
        if (strlen($networkBinary) !== strlen($binary)) {
            return false;
        }
        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        if (substr($binary, 0, $bytes) !== substr($networkBinary, 0, $bytes)) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = 0xff << (8 - $rest) & 0xff;
        return (ord($binary[$bytes]) & $mask) === (ord($networkBinary[$bytes]) & $mask);
    }

}
