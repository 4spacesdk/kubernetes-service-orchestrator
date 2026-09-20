<?php namespace App\Tests\Unit\Podio;

use App\Libraries\Podio\PodioItemUrl;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The item id in a Podio task link.
 *
 * The url is pulled out of a commit message, so most of what reaches this is not a task
 * link at all. Every one of those used to be `Undefined array key 1` at the call site.
 */
class PodioItemUrlTest extends CIUnitTestCase {

    #[DataProvider('urls')]
    public function testTheItemIdIsWhatFollowsItems(?string $url, ?string $expected): void {
        $this->assertSame($expected, PodioItemUrl::itemId($url));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function urls(): array {
        return [
            'a task link' => ['https://podio.com/acme/app/1/items/4217', '4217'],
            'with a trailing slash' => ['https://podio.com/acme/app/1/items/4217/', '4217'],
            'no item in it' => ['https://podio.com/acme/app/1/apps', null],
            'nothing after items' => ['https://podio.com/acme/app/1/items/', null],
            'empty' => ['', null],
            'null' => [null, null],
        ];
    }

}
