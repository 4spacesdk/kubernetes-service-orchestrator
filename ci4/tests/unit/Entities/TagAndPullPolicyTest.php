<?php namespace App\Tests\Unit\Entities;

use App\Entities\ContainerImage;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What the newest tag of an imported image says about how it is released - see
 * `ContainerImage::guessTagAndPullPolicy()`.
 */
class TagAndPullPolicyTest extends CIUnitTestCase {

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function tags(): array {
        return [
            'a version' => ['1.9.5', true],
            'with a v' => ['v3.10.0', true],
            'a build number' => ['20261004', true],
            'a pre-release' => ['2.0.0-rc.1', true],
            'latest' => ['latest', false],
            'latest-minor' => ['latest-minor', false],
            'a test tag' => ['tst', false],
            'a branch' => ['develop', false],
        ];
    }

    #[DataProvider('tags')]
    public function testAVersionIsPulledWhenNotPresentAndAMovingTagIsTheDefaultAlwaysPulled(string $tag, bool $isAVersion): void {
        $this->assertSame(
            $isAVersion ? ['', \ImagePullPolicies::IfNotPresent] : [$tag, \ImagePullPolicies::Always],
            ContainerImage::TagAndPullPolicyFor($tag)
        );
    }

}
