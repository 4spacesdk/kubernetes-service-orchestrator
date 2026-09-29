<?php namespace App\Tests\Unit\Kubernetes;

use App\Entities\ContainerImage;
use App\Libraries\Kubernetes\SecurityAdvice;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * What a container image's security could be, from what it runs as - see `SecurityAdvice`. Few
 * lines, each about this image and saying what to do; a catalogue of best practices is noise.
 */
class SecurityAdviceTest extends CIUnitTestCase {

    public function testAnImageThatIsAsSecureAsItCanTellHasNothingToSay(): void {
        $this->assertSame([], SecurityAdvice::For($this->image(['image_user' => '1000', 'security_context_run_as_non_root' => true])));
    }

    public function testANonRootUserNotYetTurnedOnIsSuggested(): void {
        $this->assertSame(
            [['key' => 'run_as_non_root', 'level' => 'suggestion', 'text' => 'develop runs as 1000 - Run as non-root can be turned on']],
            SecurityAdvice::For($this->image(['image_user' => '1000']))
        );
    }

    /**
     * Turned on, and a newer tag that runs as root: its containers will be refused. The scan of
     * that tag is newer than the read when the image was made, so it is the one that counts.
     */
    public function testAVersionThatTurnedToRootIsAWarning(): void {
        $advice = SecurityAdvice::For(
            $this->image(['image_user' => '1000', 'security_context_run_as_non_root' => true]),
            ['tag' => '3.0.0', 'image_user' => '', 'scanned_at' => '2026-10-01 10:00:00'],
        );

        $this->assertSame('warning', $advice[0]['level']);
        $this->assertStringStartsWith('3.0.0 runs as root - its containers will not start under Run as non-root', $advice[0]['text']);
    }

    public function testANamedUserSaysANumberIsNeeded(): void {
        $advice = SecurityAdvice::For($this->image(['image_user' => 'appuser']));

        $this->assertSame('develop runs as appuser, a name Run as non-root cannot check - with a numeric USER that is not root, it could run as non-root', $advice[0]['text']);
    }

    /**
     * A uid on the image is one kubelet can check, whatever the Dockerfile names.
     */
    public function testAUserSetOnTheImageCanRunAsNonRoot(): void {
        $advice = SecurityAdvice::For($this->image(['image_user' => 'appuser', 'security_context_run_as_user' => '100']));

        $this->assertSame([['key' => 'run_as_non_root', 'level' => 'suggestion', 'text' => 'Runs as 100, set here - Run as non-root can be turned on']], $advice);
    }

    /**
     * Made before kso read images, with a uid: nothing to read, and still something to say.
     */
    public function testAUserSetOnTheImageCountsWithoutARead(): void {
        $advice = SecurityAdvice::For($this->image(['image_user' => null, 'image_user_tag' => null, 'security_context_run_as_user' => '100']));

        $this->assertSame('Runs as 100, set here - Run as non-root can be turned on', $advice[0]['text']);
    }

    public function testAUserThatCouldNotBeReadSaysWhy(): void {
        $advice = SecurityAdvice::For($this->image(['image_user' => null, 'image_user_tag' => null, 'image_user_error' => 'unauthorized']));

        $this->assertSame([['key' => 'unreadable', 'level' => 'info', 'text' => 'What it runs as could not be read: unauthorized']], $advice);
    }

    public function testSeccompOffIsSuggested(): void {
        $advice = SecurityAdvice::For($this->image(['image_user' => '1000', 'security_context_run_as_non_root' => true, 'security_context_seccomp_runtime_default' => false]));

        $this->assertCount(1, $advice);
        $this->assertStringStartsWith('Seccomp profile RuntimeDefault is off', $advice[0]['text']);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function image(array $fields): ContainerImage {
        $image = new ContainerImage();
        foreach (array_merge([
            'image_user_tag' => 'develop',
            'image_user_read_at' => '2026-09-29 10:00:00',
            'image_user_error' => null,
            'security_context_run_as_user' => '',
            'security_context_run_as_non_root' => false,
            'security_context_seccomp_runtime_default' => true,
        ], $fields) as $field => $value) {
            $image->{$field} = $value;
        }
        return $image;
    }

}
