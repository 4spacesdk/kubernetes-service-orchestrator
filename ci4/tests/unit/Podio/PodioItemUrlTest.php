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

    #[DataProvider('messages')]
    public function testTheFirstPodioLinkInACommitMessageIsTheOne(?string $message, ?string $expected): void {
        $this->assertSame($expected, PodioItemUrl::firstIn($message));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function messages(): array {
        return [
            'one link' => ['Fixes https://podio.com/acme/app/1/items/4217', 'https://podio.com/acme/app/1/items/4217'],
            'after a GitHub link' => ["Merge https://github.com/acme/app/pull/12\n\nhttps://podio.com/acme/app/1/items/4217", 'https://podio.com/acme/app/1/items/4217'],
            'two tasks: the first' => ['Fixes https://podio.com/x/items/42 and https://podio.com/x/items/43', 'https://podio.com/x/items/42'],
            'at the end of a sentence' => ['See https://podio.com/x/items/42.', 'https://podio.com/x/items/42'],
            'in brackets' => ['Done (https://podio.com/x/items/42)', 'https://podio.com/x/items/42'],
            'a Markdown link' => ['[the task](https://podio.com/x/items/42)', 'https://podio.com/x/items/42'],
            'followed by a comma' => ['https://podio.com/x/items/42, and more', 'https://podio.com/x/items/42'],
            'with www' => ['https://www.podio.com/x/items/42', 'https://www.podio.com/x/items/42'],
            'another host only' => ['See https://github.com/acme/app/issues/1', null],
            'a host that only starts like it' => ['https://podio.com.evil.example/x/items/42', null],
            'no link' => ['Tidy up the logging', null],
            'empty' => ['', null],
            'null' => [null, null],
        ];
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
