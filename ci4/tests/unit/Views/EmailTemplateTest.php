<?php namespace App\Tests\Unit\Views;

use App\Libraries\EmailLib;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The HTML every mail is sent in: the installation named at the top, its contact at the bottom,
 * and KSO's logo when one is attached.
 */
class EmailTemplateTest extends CIUnitTestCase {

    private const Project = ['PROJECT_NAME', 'PROJECT_CONTACT_URL', 'PROJECT_CONTACT_EMAIL'];

    /** @var array<string, string|false> */
    private array $original = [];

    protected function setUp(): void {
        parent::setUp();
        foreach (self::Project as $name) {
            $this->original[$name] = getenv($name);
        }
    }

    protected function tearDown(): void {
        // The environment outlives the test; it goes back as it was.
        foreach ($this->original as $name => $value) {
            $value === false ? putenv($name) : putenv($name . '=' . $value);
        }
        parent::tearDown();
    }

    public function testTheProjectAndItsContactAreInTheMail(): void {
        putenv('PROJECT_NAME=klartboard');
        putenv('PROJECT_CONTACT_URL=https://klart.dk');
        putenv('PROJECT_CONTACT_EMAIL=hello@klart.dk');

        $html = EmailLib::Render('Subject', '<p>Body</p>', 'Martin', 'logo@kso');

        $this->assertStringContainsString('klartboard', $html);
        $this->assertStringContainsString('href="https://klart.dk"', $html);
        $this->assertStringContainsString('href="mailto:hello@klart.dk"', $html);
        $this->assertStringContainsString('src="cid:logo@kso"', $html);
        $this->assertStringContainsString('<p>Body</p>', $html, 'the message is HTML, as the caller wrote it');
    }

    /**
     * An installation that sets none of them gets no empty footer and no stray separators.
     */
    public function testWhatIsNotSetIsLeftOut(): void {
        foreach (self::Project as $name) {
            putenv($name);
        }

        $html = EmailLib::Render('Subject', 'Body', 'Martin');

        $this->assertStringNotContainsString('mailto:', $html);
        $this->assertStringNotContainsString('border-top', $html);
        $this->assertStringNotContainsString('cid:', $html, 'no logo attached, no broken image');
    }

    /**
     * The inbox shows the installation, not "4 Spaces KSO", so mails from two installations
     * can be told apart.
     */
    public function testTheSenderAndSubjectNameTheProject(): void {
        putenv('PROJECT_NAME=klartboard');

        $this->assertSame('klartboard', EmailLib::SenderName());
        $this->assertSame('klartboard | Choose a new password', EmailLib::Subject('Choose a new password'));
    }

    public function testWithoutAProjectNameTheSenderIsKso(): void {
        putenv('PROJECT_NAME');

        $this->assertSame('KSO | Choose a new password', EmailLib::Subject('Choose a new password'));
    }

    public function testTheReceiversNameIsEscaped(): void {
        $html = EmailLib::Render('Subject', 'Body', '<b>Martin</b>');

        $this->assertStringContainsString('Hi &lt;b&gt;Martin&lt;/b&gt;', $html);
    }

    /**
     * A client in dark mode is asked not to turn the colours around - Apple Mail made the band
     * pale grey.
     */
    public function testTheMailAsksToStayLight(): void {
        $this->assertStringContainsString('<meta name="color-scheme" content="light only"/>', EmailLib::Render('S', 'B', 'M'));
    }

}
