<?php namespace App\Tests\Unit\Kubernetes;

use App\Libraries\Kubernetes\DnsLabel;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The namespace rule, which the workspace dialog got wrong twice: it allowed 15 characters
 * rather than 63, and generated a namespace that ended in a hyphen.
 */
class DnsLabelTest extends CIUnitTestCase {

    #[DataProvider('labels')]
    public function testWhatKubernetesAcceptsAsALabel(string $value, bool $valid): void {
        $this->assertSame($valid, DnsLabel::isValid($value), $value);
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function labels(): array {
        return [
            'short' => ['klartboard', true],
            'with hyphens' => ['kb-oester-aas', true],
            'digits only' => ['123', true],
            'sixteen characters' => ['klartboard-dev-1', true],
            '63 characters' => [str_repeat('a', 63), true],
            '64 characters' => [str_repeat('a', 64), false],
            'ends in a hyphen' => ['klartboard-', false],
            'starts with a hyphen' => ['-klartboard', false],
            'uppercase' => ['Klartboard', false],
            'a Danish letter' => ['øster', false],
            'underscore' => ['kb_dev', false],
            'empty' => ['', false],
        ];
    }

    #[DataProvider('names')]
    public function testANameBecomesALabel(string $name, string $label): void {
        $this->assertSame($label, DnsLabel::from($name));
        $this->assertTrue(DnsLabel::isValid($label), $label);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function names(): array {
        return [
            'Danish letters' => ['Øster Ås, Nord', 'oester-aas-nord'],
            'accents' => ['Café Crème', 'cafe-creme'],
            'punctuation at the ends' => ['  -Klartboard!- ', 'klartboard'],
            'cut at 63 without a trailing hyphen' => [str_repeat('a', 62) . ' b', str_repeat('a', 62)],
        ];
    }

}
