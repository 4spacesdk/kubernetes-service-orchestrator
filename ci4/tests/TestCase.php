<?php namespace App;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\RestExtension;

class TestCase extends CIUnitTestCase {

    // CIDatabaseTestCase was removed in CodeIgniter 4.7. It was this trait on top of
    // CIUnitTestCase all along, so the behaviour is unchanged.
    use DatabaseTestTrait;
    use RestoresTheExceptionHandler;

    protected $refresh = true;
//    protected $seed = 'DevSeeder';
    protected $basePath = APPPATH . 'Database';
    protected $namespace = '';

    public function setUp(): void {
        /** @var RestExtension $restExtensionConfig */
        $restExtensionConfig = config('RestExtension');
        $restExtensionConfig->databaseGroupName = $this->DBGroup;

        $this->rememberTheExceptionHandler();
        Events::trigger('pre_system');
        parent::setUp();
    }

    /**
     * A test that provoked one on purpose says so, and says what it expected to see in it.
     */
    protected function expectUnhandledRejection(string $message): void {
        $this->expectedRejection = $message;
    }

    private ?string $expectedRejection = null;

    public function tearDown(): void {
        // A failed expectation throws, and the handler is put back regardless - otherwise
        // the failure also reports the test as risky, and the real cause is harder to see.
        try {
            $this->failOnAnUnhandledRejection();
        } finally {
            try {
                parent::tearDown();
            } finally {
                $this->restoreTheExceptionHandler();
            }
        }
    }

    /**
     * Nothing in PHPUnit sees a rejected promise: it is not an exception, not a warning,
     * and react/promise reports it with `error_log()` - which on the command line is
     * stderr, not the output buffer PHPUnit watches. A test could print a five-hundred
     * error and still be green. One did.
     */
    private function failOnAnUnhandledRejection(): void {
        $rejections = UnhandledRejections::takeAll();

        if ($this->expectedRejection !== null) {
            $this->assertNotSame([], $rejections, 'expected a rejected promise, and nothing rejected');
            $this->assertStringContainsString($this->expectedRejection, (string) $rejections[0]);

            return;
        }

        if ($rejections !== []) {
            $this->fail('A promise was rejected and nobody handled it: ' . $rejections[0]);
        }
    }

    private function echo($msg) {
        CLI::write($msg);
    }

}
