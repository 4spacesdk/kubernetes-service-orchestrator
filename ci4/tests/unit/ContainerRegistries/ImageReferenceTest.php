<?php namespace App\Tests\Unit\ContainerRegistries;

use App\Libraries\ContainerRegistries\ImageReference;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Where the tag starts in an image reference.
 *
 * The two ways a pushed tag reaches kso - a webhook and a Pub/Sub message - used to split
 * it differently, so a registry with a port in its host worked through one and not the
 * other.
 */
class ImageReferenceTest extends CIUnitTestCase {

    /**
     * @param array{0: string, 1: ?string} $expected
     */
    #[DataProvider('references')]
    public function testTheTagIsWhatFollowsTheLastColonAfterTheLastSlash(?string $reference, array $expected): void {
        $this->assertSame($expected, ImageReference::split($reference));
    }

    /**
     * @return array<string, array{0: ?string, 1: array{0: string, 1: ?string}}>
     */
    public static function references(): array {
        return [
            'plain' => ['registry.example.org/team/api:v2.0.0', ['registry.example.org/team/api', 'v2.0.0']],
            'a host with a port' => [
                'registry.example.org:5000/team/api:v2.0.0',
                ['registry.example.org:5000/team/api', 'v2.0.0'],
            ],
            'a host with a port and no tag' => [
                'registry.example.org:5000/team/api',
                ['registry.example.org:5000/team/api', null],
            ],
            'no tag' => ['registry.example.org/team/api', ['registry.example.org/team/api', null]],
            'a colon with nothing after it' => [
                'registry.example.org/team/api:',
                ['registry.example.org/team/api:', null],
            ],
            'no registry host' => ['api:v2.0.0', ['api', 'v2.0.0']],
            'empty' => ['', ['', null]],
            'null' => [null, ['', null]],
        ];
    }

}
