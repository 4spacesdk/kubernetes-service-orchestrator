<?php namespace App\Tests\Unit\Kubernetes;

use App\Libraries\Kubernetes\SecretRecipe;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What a secret kso makes is made of, written as a Helm chart writes it with Sprig's functions.
 */
class SecretRecipeTest extends CIUnitTestCase {

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function recipes(): array {
        return [
            'the default' => ['', '/^[0-9a-f]{64}$/'],
            'hex' => ['randHex 16', '/^[0-9a-f]{16}$/'],
            'letters and digits' => ['randAlphaNum 32', '/^[A-Za-z0-9]{32}$/'],
            'letters' => ['randAlpha 20', '/^[A-Za-z]{20}$/'],
            'digits' => ['randNumeric 6', '/^[0-9]{6}$/'],
            'printable ascii' => ['randAscii 40', '/^[\x21-\x7e]{40}$/'],
            'bytes as base64' => ['randBytes 32', '/^[A-Za-z0-9+\/]{43}=$/'],
            'a uuid' => ['uuidv4', '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/'],
            'spaces around' => ['  randAlphaNum 8 ', '/^[A-Za-z0-9]{8}$/'],
        ];
    }

    #[DataProvider('recipes')]
    public function testEachRecipeMakesWhatItSays(string $written, string $shape): void {
        $recipe = SecretRecipe::Parse($written);

        $this->assertInstanceOf(SecretRecipe::class, $recipe);
        $this->assertMatchesRegularExpression($shape, $recipe->make());
        $this->assertNotSame($recipe->make(), $recipe->make(), 'random each time');
    }

    public function testTheRecipeIsStoredAsItIsWritten(): void {
        $this->assertSame('randHex 64', (string) SecretRecipe::Parse(''));
        $this->assertSame('randAlphaNum 32', (string) SecretRecipe::Parse(' randAlphaNum   32 '));
        $this->assertSame('uuidv4', (string) SecretRecipe::Parse('uuidv4'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function notRecipes(): array {
        return [
            'an unknown function' => ['randomString 32', 'is not a recipe kso knows'],
            'no length' => ['randAlphaNum', 'is not a recipe kso knows'],
            'a length of nothing' => ['randHex 0', 'is not between 1 and 4096'],
            'too long' => ['randHex 5000', 'is not between 1 and 4096'],
            'a pipe of two' => ['randAlphaNum 32 | b64enc', 'is not a recipe kso knows'],
        ];
    }

    #[DataProvider('notRecipes')]
    public function testWhatIsNotARecipeSaysWhy(string $written, string $reason): void {
        $this->assertStringContainsString($reason, (string) SecretRecipe::Parse($written));
    }

}
