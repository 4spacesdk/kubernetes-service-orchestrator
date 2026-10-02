<?php namespace App\Tests\Database\Api;

use App\ControllerTestCase;
use App\Entities\ContainerImage;
use App\Fixtures;
use App\Libraries\ContainerRegistries\ImageConfig;
use App\Tests\Fakes\FakeIntegrations;

/**
 * A container image made secure when it is made: kso reads the `USER` its registry says it runs
 * as, and stamps Run as non-root from it - and again whenever somebody reads it anew, which is
 * how an image follows a version that changed its user. See `SecurityContext`.
 *
 * The registry is the test's own (`ImageConfig::$http`), answering with a config whose `User` is
 * what the test says. `ImageConfigTest` has how it is asked.
 */
class ContainerImageSecurityApiTest extends ControllerTestCase {

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function users(): array {
        return [
            'a number' => ['1000', true],
            'a number and a group' => ['1000:1000', true],
            'root by number' => ['0', false],
            'root, by setting none' => ['', false],
            'a name kubelet cannot check' => ['appuser', false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('users')]
    public function testRunAsNonRootIsStampedFromWhatTheImageRunsAs(string $user, bool $nonRoot): void {
        $this->aRegistryWhereTheImageRunsAs($user);
        $image = Fixtures::containerImage(['default_tag' => 'stable']);

        $body = $this->decode($this->signedIn()->put("container-images/{$image->id}/read-user"));

        $this->assertSame('OK', $body['status']);
        $image = $this->reread($image);
        $this->assertSame($nonRoot, (bool) $image->security_context_run_as_non_root);
        $this->assertSame($user, (string) $image->image_user);
        $this->assertSame('stable', $image->image_user_tag, 'the default tag, with none asked for');
    }

    /**
     * `USER appuser`, and a uid on the image: kubelet runs it as the uid, which it can check.
     */
    public function testAUserSetOnTheImageCounts(): void {
        $this->aRegistryWhereTheImageRunsAs('appuser');
        $image = Fixtures::containerImage(['security_context_run_as_user' => '100']);

        $image->readUser('develop');

        $this->assertTrue((bool) $this->reread($image)->security_context_run_as_non_root);
    }

    /**
     * Read anew, stamped anew - both ways. That is the way through a version that changed its user.
     */
    public function testReadingAnotherTagStampsAnew(): void {
        $image = Fixtures::containerImage();
        $this->aRegistryWhereTheImageRunsAs('');
        $image->readUser('1.0');
        $this->assertFalse((bool) $this->reread($image)->security_context_run_as_non_root);

        $this->aRegistryWhereTheImageRunsAs('1000');
        $this->signedIn()->put("container-images/{$image->id}/read-user?tag=2.0");

        $image = $this->reread($image);
        $this->assertTrue((bool) $image->security_context_run_as_non_root);
        $this->assertSame('2.0', $image->image_user_tag);
    }

    /**
     * A registry that cannot be read changes no setting - a refused login is not an image that
     * runs as root - and says why.
     */
    public function testARegistryThatCannotBeReadChangesNothingAndSaysWhy(): void {
        ImageConfig::$http = static fn (): array => [401, [], json_encode(['errors' => [['message' => 'unauthorized']]])];
        $image = Fixtures::containerImage(['security_context_run_as_non_root' => true]);

        $this->signedIn()->put("container-images/{$image->id}/read-user?tag=1.0");

        $image = $this->reread($image);
        $this->assertTrue((bool) $image->security_context_run_as_non_root);
        $this->assertStringContainsString('unauthorized', (string) $image->image_user_error);
    }

    public function testATagThatIsNoTagIsRefused(): void {
        $image = Fixtures::containerImage();

        $body = $this->decode($this->signedIn()->put("container-images/{$image->id}/read-user?tag=" . rawurlencode('../../v2/_catalog')));

        $this->assertSame('invalid tag', $body['error'] ?? null);
    }

    /**
     * Made from a registry, an image is made as secure as one made by hand.
     */
    public function testAnImportedImageIsStamped(): void {
        $fakes = FakeIntegrations::install();
        $fakes->tags = [];
        $fakes->repositories = ['team/api'];
        $this->aRegistryWhereTheImageRunsAs('1000');
        $registry = Fixtures::containerRegistry();

        $this->decode($this->withBodyFormat('json')->signedIn()->post("container-registries/{$registry->id}/import", ['repositories' => ['team/api']]));

        $image = $this->db->table('container_images')->where('url', 'registry.example.org/team/api')->get()->getRow();
        $this->assertSame(1, (int) $image->security_context_run_as_non_root);
        $this->assertSame(1, (int) $image->security_context_seccomp_runtime_default);
    }

    /**
     * Where it writes, from its label, read with its user. The label is the image's own word and
     * wins; without one, what was set by hand stays.
     */
    public function testTheWritablePathsAreReadFromTheImagesLabel(): void {
        $this->aRegistryWhereTheImageRunsAs('1000', ['dk.4spaces.kso.writable-paths' => '/tmp, /var/run/apache2,/tmp']);
        $labelled = Fixtures::containerImage(['writable_paths' => '/old']);
        $labelled->readUser('develop');
        $this->assertSame('/tmp,/var/run/apache2', $this->reread($labelled)->writable_paths);

        $this->aRegistryWhereTheImageRunsAs('1000');
        $unlabelled = Fixtures::containerImage(['writable_paths' => '/set/by/hand']);
        $unlabelled->readUser('develop');
        $this->assertSame('/set/by/hand', $this->reread($unlabelled)->writable_paths);
    }

    /**
     * What to do next is on the image: a version that runs as non-root, not yet turned on.
     */
    public function testTheImageSaysWhatCouldBeMoreSecure(): void {
        $image = Fixtures::containerImage(['security_context_seccomp_runtime_default' => true]);
        Fixtures::containerImageScan(['container_image_id' => $image->id, 'tag' => '2.1.0', 'image_user' => '1000']);

        $advice = json_decode($this->decode($this->signedIn()->get("container_images/{$image->id}"))['resource']['security_advice'], true);

        $this->assertSame([['key' => 'run_as_non_root', 'level' => 'suggestion', 'text' => '2.1.0 runs as 1000 - Run as non-root can be turned on']], $advice);
    }

    // <editor-fold desc="Helpers">

    /**
     * @param array<string, string> $labels
     */
    private function aRegistryWhereTheImageRunsAs(string $user, array $labels = []): void {
        ImageConfig::$http = static function (string $url) use ($user, $labels): array {
            if (str_contains($url, '/manifests/')) {
                return [200, [], json_encode(['config' => ['digest' => 'sha256:config']])];
            }
            return [200, [], json_encode(['config' => ['User' => $user, 'Labels' => $labels ?: null]])];
        };
    }

    private function reread(ContainerImage $image): ContainerImage {
        $fresh = new ContainerImage();
        $fresh->find($image->id);

        return $fresh;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\Test\TestResponse $response): array {
        return json_decode((string) $response->response()->getBody(), true);
    }

    // </editor-fold>

}
