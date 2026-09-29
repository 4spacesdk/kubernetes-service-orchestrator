<?php namespace App\Libraries\ContainerRegistries;

/**
 * One repository on one registry, logged in as the first answer asks: a bearer token from the
 * realm it names, or the credentials as they are.
 * See `ImageConfig`.
 */
class ImageConfigSession {

    private ?string $authorization = null;

    /**
     * @param array{0: string, 1: string}|null $credentials
     */
    public function __construct(
        private readonly string $host,
        private readonly string $repository,
        private readonly ?array $credentials,
    ) {
    }

    /**
     * @param list<string> $accept
     * @return array<string, mixed>
     */
    public function json(string $path, array $accept): array {
        $url = "https://{$this->host}/v2/{$this->repository}{$path}";
        [$status, $headers, $body] = ImageConfig::Get($url, $this->headers($accept));

        if ($status === 401 && $this->authorization === null && isset($headers['www-authenticate'])) {
            $this->authorization = $this->logIn($headers['www-authenticate']);
            [$status, $headers, $body] = ImageConfig::Get($url, $this->headers($accept));
        }
        if ($status !== 200) {
            throw new \RuntimeException("{$this->host} answered {$status} for {$this->repository}{$path}" . self::Reason($body));
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException("{$this->host} answered something that is not JSON for {$this->repository}{$path}");
        }
        return $decoded;
    }

    /**
     * @param list<string> $accept
     * @return array<string, string>
     */
    private function headers(array $accept): array {
        return array_filter([
            'Accept' => implode(', ', $accept),
            'Authorization' => $this->authorization,
        ]);
    }

    private function logIn(string $challenge): string {
        $basic = $this->credentials === null ? null : 'Basic ' . base64_encode("{$this->credentials[0]}:{$this->credentials[1]}");

        if (stripos($challenge, 'Basic') === 0) {
            if ($basic === null) {
                throw new \RuntimeException("{$this->host} wants a login, and the image's registry connection has no pull credentials");
            }
            return $basic;
        }
        if (stripos($challenge, 'Bearer') !== 0) {
            throw new \RuntimeException("{$this->host} refused without saying how to log in");
        }

        preg_match_all('/(\w+)="([^"]*)"/', $challenge, $matches, PREG_SET_ORDER);
        $parameters = [];
        foreach ($matches as [, $name, $value]) {
            $parameters[strtolower($name)] = $value;
        }
        if (!isset($parameters['realm'])) {
            throw new \RuntimeException("{$this->host} asked for a token and named no realm");
        }

        $query = array_filter([
            'service' => $parameters['service'] ?? null,
            'scope' => "repository:{$this->repository}:pull",
        ]);
        $realm = $parameters['realm'] . (str_contains($parameters['realm'], '?') ? '&' : '?') . http_build_query($query);
        [$status, , $body] = ImageConfig::Get($realm, array_filter(['Authorization' => $basic]));
        $token = json_decode($body, true);
        $token = $token['token'] ?? $token['access_token'] ?? null;
        if ($status !== 200 || !$token) {
            throw new \RuntimeException("{$this->host} gave no token for {$this->repository} ({$status})" . self::Reason($body));
        }

        return "Bearer {$token}";
    }

    private static function Reason(string $body): string {
        $decoded = json_decode($body, true);
        $message = $decoded['errors'][0]['message'] ?? $decoded['details'] ?? null;
        return is_string($message) && $message !== '' ? ": {$message}" : '';
    }

}
