<?php namespace App\Tests\Unit\Config;

use App\Libraries\EmailLib;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Whether this installation can send mail at all.
 *
 * Five environment variables, all required. It is the guard in front of every notification
 * kso sends - the certificate expiry warning among them - and `send()` throws when it says
 * no. A missing variable therefore turns a warning into an exception in a cron job, which
 * is worth knowing is the intended behaviour rather than an oversight.
 */
class EmailIsConfiguredTest extends CIUnitTestCase {

    private const Required = [
        'EMAIL_SERVICE_HOST',
        'EMAIL_SERVICE_PORT',
        'EMAIL_SERVICE_USER',
        'EMAIL_SERVICE_PASS',
        'EMAIL_SERVICE_SENDER',
    ];

    protected function tearDown(): void {
        foreach (self::Required as $name) {
            putenv($name);
        }

        parent::tearDown();
    }

    public function testEveryVariableTogetherMeansConfigured(): void {
        $this->set(self::Required);

        $this->assertTrue(EmailLib::IsConfigured());
    }

    public function testNothingSetMeansNotConfigured(): void {
        $this->assertFalse(EmailLib::IsConfigured());
    }

    /**
     * Any one of them missing is enough. They are checked with `&&`, so the order does not
     * matter - but a future sixth variable added to the list and not to the check would go
     * unnoticed, which is what this holds.
     */
    #[DataProvider('theOneThatIsMissing')]
    public function testOneMissingVariableIsEnoughToSayNo(string $missing): void {
        $this->set(array_values(array_diff(self::Required, [$missing])));

        $this->assertFalse(EmailLib::IsConfigured(), "without {$missing}");
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function theOneThatIsMissing(): array {
        return array_combine(self::Required, array_map(fn ($name) => [$name], self::Required));
    }

    /**
     * An empty string is not a value. A half-filled deployment manifest sets the variable
     * to nothing rather than leaving it out, and `strlen()` is what catches that.
     */
    public function testAnEmptyValueDoesNotCount(): void {
        $this->set(self::Required);
        putenv('EMAIL_SERVICE_HOST=');

        $this->assertFalse(EmailLib::IsConfigured());
    }

    /**
     * @param string[] $names
     */
    private function set(array $names): void {
        foreach ($names as $name) {
            putenv("{$name}=something");
        }
    }

}
