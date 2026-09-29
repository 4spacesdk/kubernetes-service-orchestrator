<?php namespace App\Tests\Database\Libraries;

use App\DatabaseTestCase;
use App\Entities\ContainerImage;
use App\Fixtures;
use App\Libraries\ContainerRegistries\ImageConfig;

/**
 * Reading what an image runs as from its registry - the manifest and the config blob, over the
 * registry API every provider speaks, without pulling a layer.
 *
 * Against a registry of the test's own that answers the way Harbor does: 401 with a Bearer
 * challenge, a token for the repository from the realm it names, an index of platforms, and the
 * blob served from somewhere else by a redirect. Checked against OVH's Harbor by hand, 2026-09-29.
 */
class ImageConfigTest extends DatabaseTestCase {

    /** @var list<array{url: string, authorization: ?string}> */
    private array $calls = [];

    /** @var array<string, string> what each blob holds */
    private array $configs = [];

    public function testTheUserIsReadWithTheRegistrysPullCredentials(): void {
        $this->aRegistry(user: '1000:1000');

        $this->assertSame('1000:1000', ImageConfig::User($this->anImage(withPullCredentials: true), 'develop'));

        $tokenRequest = $this->callTo('/service/token');
        $this->assertSame('Basic ' . base64_encode('robot$kso:secret'), $tokenRequest['authorization']);
        $this->assertStringContainsString('scope=repository%3Aproject%2Fapp%3Apull', $tokenRequest['url']);
        $this->assertSame('Bearer the-token', $this->callTo('/manifests/develop', last: true)['authorization']);
    }

    /**
     * An image of several platforms: the one a node in the cluster would run.
     */
    public function testOfAnIndexTheLinuxAmd64ImageIsRead(): void {
        $this->aRegistry(user: 'appuser', index: true);

        $this->assertSame('appuser', ImageConfig::User($this->anImage(withPullCredentials: true), 'develop'));
        $this->assertNotNull($this->callTo('/manifests/sha256:amd64'));
        $this->assertNull($this->callTo('/manifests/sha256:arm64'));
    }

    /**
     * The blob is served by storage behind a redirect, with a signed url. The login is the
     * registry's, and does not go to another host.
     */
    public function testTheLoginIsNotSentToWhereTheBlobRedirects(): void {
        $this->aRegistry(user: '101', redirectBlob: true);

        $this->assertSame('101', ImageConfig::User($this->anImage(withPullCredentials: true), 'develop'));
        $this->assertNull($this->callTo('storage.example.org')['authorization']);
    }

    public function testAnImageThatSetsNoUserRunsAsRoot(): void {
        $this->aRegistry(user: '');

        $this->assertSame('', ImageConfig::User($this->anImage(withPullCredentials: true), 'develop'));
    }

    public function testAPublicImageIsReadWithoutALogin(): void {
        $this->aRegistry(user: '65534');

        $this->assertSame('65534', ImageConfig::User($this->anImage(withPullCredentials: false), 'develop'));
        $this->assertNull($this->callTo('/service/token')['authorization']);
    }

    public function testARefusalCarriesTheRegistrysReason(): void {
        ImageConfig::$http = function (string $url, array $headers): array {
            return [404, [], json_encode(['errors' => [['message' => 'manifest unknown']]])];
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('manifest unknown');

        ImageConfig::User($this->anImage(withPullCredentials: false), 'nope');
    }

    public function testAnImageWithoutARegistryHostIsDockerHubs(): void {
        $this->assertSame(['registry-1.docker.io', 'library/nginx'], ImageConfig::Split('nginx'));
        $this->assertSame(['registry-1.docker.io', 'bitnami/redis'], ImageConfig::Split('bitnami/redis'));
        $this->assertSame(['registry-1.docker.io', 'library/nginx'], ImageConfig::Split('docker.io/library/nginx'));
        $this->assertSame(['eu.gcr.io', 'project/app'], ImageConfig::Split('eu.gcr.io/project/app'));
        $this->assertSame(['localhost:5000', 'app'], ImageConfig::Split('localhost:5000/app'));
    }

    // <editor-fold desc="The registry">

    private function anImage(bool $withPullCredentials): ContainerImage {
        $registry = Fixtures::containerRegistry($withPullCredentials ? ['pull_username' => 'robot$kso', 'pull_password' => 'secret'] : []);

        return Fixtures::containerImage(['url' => 'registry.example.org/project/app', 'container_registry_id' => $registry->id]);
    }

    private function aRegistry(string $user, bool $index = false, bool $redirectBlob = false): void {
        $config = json_encode(['config' => ['User' => $user]]);
        $manifest = json_encode(['config' => ['digest' => 'sha256:config']]);

        ImageConfig::$http = function (string $url, array $headers) use ($config, $manifest, $index, $redirectBlob): array {
            $this->calls[] = ['url' => $url, 'authorization' => $headers['Authorization'] ?? null];

            if (str_contains($url, '/service/token')) {
                return [200, [], json_encode(['token' => 'the-token'])];
            }
            if (str_starts_with($url, 'https://storage.example.org/')) {
                return [200, [], $config];
            }
            if (($headers['Authorization'] ?? null) !== 'Bearer the-token') {
                return [401, ['www-authenticate' => 'Bearer realm="https://registry.example.org/service/token",service="harbor-registry",scope="repository:project/app:pull"'], ''];
            }
            if (str_ends_with($url, '/manifests/develop') && $index) {
                return [200, [], json_encode(['manifests' => [
                    ['digest' => 'sha256:arm64', 'platform' => ['os' => 'linux', 'architecture' => 'arm64']],
                    ['digest' => 'sha256:amd64', 'platform' => ['os' => 'linux', 'architecture' => 'amd64']],
                ]])];
            }
            if (str_contains($url, '/manifests/')) {
                return [200, [], $manifest];
            }
            if (str_ends_with($url, '/blobs/sha256:config')) {
                return $redirectBlob ? [307, ['location' => 'https://storage.example.org/signed?x=1'], ''] : [200, [], $config];
            }
            return [404, [], ''];
        };
    }

    /**
     * @return array{url: string, authorization: ?string}|null
     */
    private function callTo(string $part, bool $last = false): ?array {
        $matching = array_values(array_filter($this->calls, fn (array $call) => str_contains($call['url'], $part)));

        return $matching === [] ? null : ($last ? end($matching) : $matching[0]);
    }

    // </editor-fold>

}
