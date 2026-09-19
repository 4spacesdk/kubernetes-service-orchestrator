<?php namespace App\Tests\Unit\Helpers;

use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The global helpers that the rest of kso reaches for without thinking about them.
 *
 * They are small, they have no owner, and they are called from controllers, from the
 * deployment steps and from a cron command alike - so a change to one of them lands in
 * every one of those places at once and none of them will notice. That is the reason
 * they are worth holding down here rather than through whatever happens to call them.
 *
 * One of them depends on where the request came from rather than on its arguments:
 * `getFrontendUrl()` is built on `base_url()`, which kso derives from the `Host` header of
 * the request being served. `arrive()` below stages a request the way the web server would,
 * and everything it touches is put back in `tearDown()`.
 */
class HelperFunctionsTest extends CIUnitTestCase {

    private string|false $devRemoteBaseUrl;
    private string|false $tagName;

    // <editor-fold desc="The file itself">

    /**
     * Every one of the five is wrapped in a `function_exists()` guard, and the guards are
     * load bearing: the file is pulled in by `Config\Autoload::$files`, but a helper file
     * can also be asked for by name at any point in a request, and PHP has no recovery
     * from declaring a function twice. Without the guards the second load is a fatal
     * error that takes the whole request with it, which is why loading it again here has
     * to be a thing that simply works.
     *
     * The names are asserted rather than counted because they are global - nothing imports
     * them, so a rename is invisible until the call site runs.
     */
    public function testTheHelpersSurviveTheFileBeingLoadedASecondTime(): void {
        include APPPATH . 'Helpers/HelperFunctions.php';

        foreach (['php', 'version', 'strtotime_', 'datetimezone', 'getFrontendUrl'] as $helper) {
            $this->assertTrue(function_exists($helper), "{$helper}() is gone");
        }
    }

    // </editor-fold>

    // <editor-fold desc="php()">

    /**
     * Every background job kso starts is a shell command built here, and the three `-d`
     * flags are not decoration. `memory_limit` is what lets a deployment step hold a
     * cluster's worth of manifests without being killed half way through, and the command
     * has to point at the front controller inside the image rather than at a relative
     * path, because the working directory of a cron entry is not the application's.
     */
    public function testTheBackgroundCommandRunsTheApplicationsOwnFrontController(): void {
        $command = php('jobby run 7');

        $this->assertStringStartsWith('php ', $command);
        $this->assertStringContainsString('-d memory_limit=3072M', $command);
        $this->assertStringContainsString('-d short_open_tag=on', $command);
        $this->assertStringContainsString('-d allow_url_fopen=on', $command);
        $this->assertStringEndsWith(FCPATH . 'index.php jobby run 7', $command);
    }

    // </editor-fold>

    // <editor-fold desc="version()">

    /**
     * The version is what an operator reads back when asked which build is running, and
     * it is the image tag the build was produced under. A development image is built
     * without one, and `latest` is the honest answer there rather than an empty string in
     * the interface.
     */
    public function testTheBuildTagIsTheVersion(): void {
        putenv('TAG_NAME=v1.4.2');

        $this->assertSame('v1.4.2', version());
    }

    public function testAnImageBuiltWithoutATagCallsItselfLatest(): void {
        putenv('TAG_NAME');

        $this->assertSame('latest', version());
    }

    /**
     * A deployment manifest that carries the variable but leaves it blank is the same as
     * not having one; without this the version reads as nothing at all.
     */
    public function testABlankTagIsTreatedAsNoTag(): void {
        putenv('TAG_NAME=');

        $this->assertSame('latest', version());
    }

    // </editor-fold>

    // <editor-fold desc="strtotime_()">

    /**
     * Kubernetes stamps every event and every certificate with an RFC 3339 time, and kso
     * turns those into the dates shown next to a deployment. Getting one wrong shows an
     * event as having happened at the wrong moment; getting `notAfter` wrong decides
     * whether a certificate expiry warning is sent at all.
     */
    public function testAClusterTimestampIsUnderstood(): void {
        $this->assertSame(1714557600, strtotime_('2024-05-01T10:00:00Z'));
    }

    /**
     * A time that arrived through a URL has had its `+` turned into a space by the decode,
     * so `2024-05-01T10:00:00+02:00` reaches us as `...10:00:00 02:00` - which `strtotime`
     * refuses outright. Putting the `+` back is the whole reason this wrapper exists, and
     * without it the date shown for a European cluster is simply missing.
     */
    public function testAnOffsetWhoseSignWasLostInAUrlIsPutBack(): void {
        // The same instant as 08:00 UTC, which is what +02:00 means.
        $this->assertSame(strtotime('2024-05-01T08:00:00Z'), strtotime_('2024-05-01T10:00:00 02:00'));
    }

    /**
     * The `+` is only worth putting back when it helps. A value whose spaces are real -
     * a named zone after the time, an RFC 2822 date out of a header - is destroyed by the
     * substitution, so the untouched value is tried as well before giving up.
     */
    #[DataProvider('timesWhoseSpacesAreReal')]
    public function testAValueWhoseSpacesAreRealIsStillUnderstood(string $value, int $expected): void {
        $this->assertSame($expected, strtotime_($value));
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function timesWhoseSpacesAreReal(): array {
        return [
            'a named zone after the time' => ['2024-05-01 10:00:00 UTC', 1714557600],
            'an rfc 2822 date' => ['Wed, 01 May 2024 10:00:00 +0000', 1714557600],
            'a spelled out date' => ['1 May 2024 10:00:00 UTC', 1714557600],
        ];
    }

    /**
     * Already a timestamp, so leave it alone. Feeding a number to `strtotime` means
     * something else entirely - `1714557600` would be read as a year - and a caller that
     * passes a value straight out of the database would get a date centuries away.
     */
    public function testAValueThatIsAlreadyATimestampIsHandedBackUnchanged(): void {
        $this->assertSame('1714557600', strtotime_('1714557600'));
        $this->assertSame(1714557600, strtotime_(1714557600));
    }

    /**
     * A cluster that has not got round to filling a field in yet sends an empty string or
     * a placeholder, and callers wrap this in `date()`. Answering `false` is what makes
     * that show the epoch rather than throwing in the middle of rendering a page.
     */
    #[DataProvider('valuesThatAreNotTimesAtAll')]
    public function testSomethingThatIsNotATimeAtAllIsRefused(string $value): void {
        $this->assertFalse(strtotime_($value));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function valuesThatAreNotTimesAtAll(): array {
        return [
            'an empty string' => [''],
            'a word' => ['unknown'],
            'a sentence' => ['not a date'],
        ];
    }

    // </editor-fold>

    // <editor-fold desc="datetimezone()">

    /**
     * A time with no zone on it is one an operator typed, and they typed it in Danish
     * local time. It is stored and compared in UTC, so it has to be converted on the way
     * in - and the offset is not a constant: Copenhagen is two hours ahead in summer and
     * one in winter. A conversion that used a fixed offset would be an hour out for half
     * the year, which for a maintenance window means it starts at the wrong time.
     */
    #[DataProvider('localTimesOnBothSidesOfTheClockChange')]
    public function testATimeWithoutAZoneIsReadAsDanishLocalTime(string $local, string $utc): void {
        $this->assertSame($utc, datetimezone($local));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function localTimesOnBothSidesOfTheClockChange(): array {
        return [
            'summer time, two hours ahead' => ['2024-07-01 12:00:00', '2024-07-01T10:00:00+00:00'],
            'winter time, one hour ahead' => ['2024-01-01 12:00:00', '2024-01-01T11:00:00+00:00'],
        ];
    }

    /**
     * A value that carries its own zone already knows where it is, and the Danish zone is
     * only the assumption for values that do not. Overriding it would move every cluster
     * timestamp passed through here by an hour or two.
     */
    public function testAValueThatCarriesItsOwnZoneKeepsIt(): void {
        $this->assertSame('2024-07-01T12:00:00+00:00', datetimezone('2024-07-01T12:00:00Z'));
        $this->assertSame('2024-07-01T10:00:00+00:00', datetimezone('2024-07-01T12:00:00+02:00'));
    }

    /**
     * Unparseable input throws out of `DateTime`, and the empty string is what keeps that
     * from becoming a 500 on a page that is only trying to show a date it was given.
     */
    public function testAValueThatCannotBeReadBecomesAnEmptyString(): void {
        $this->assertSame('', datetimezone('nope'));
    }

    // </editor-fold>

    // <editor-fold desc="getFrontendUrl()">

    /**
     * kso's API and its interface are two applications on two addresses, and this is how
     * the API builds a link back to the interface - for the "click here" in an expiry
     * warning, and for the address a login is redirected to once the provider is done.
     * A wrong answer here sends a signed-in user to a page that does not exist.
     *
     * In production they are the same host and the API lives under `/api`, so dropping
     * that segment is the whole of it.
     */
    public function testTheInterfaceIsTheApiAddressWithoutTheApiSegment(): void {
        $this->arrive(host: 'kso.example.org', secure: true);

        $this->assertSame('https://kso.example.org', getFrontendUrl());
    }

    /**
     * No trailing slash, ever. Every caller appends a path to what comes back, and a
     * trailing slash left on would produce `//app/login` - which some providers reject
     * outright when it is registered as a redirect URI.
     */
    public function testTheAddressNeverEndsInASlash(): void {
        $this->arrive(host: 'kso.example.org', secure: true);

        $this->assertStringEndsNotWith('/', getFrontendUrl());
    }

    /**
     * Callers write the path both ways - `getFrontendUrl('app/login')` in one place and
     * `getFrontendUrl('/app/setup/system?github_success=1')` in another - and both have to
     * produce exactly one slash.
     */
    #[DataProvider('theWaysCallersWriteAPath')]
    public function testAPathIsJoinedOnWithExactlyOneSlash(?string $path, string $expected): void {
        $this->arrive(host: 'kso.example.org', secure: true);

        $this->assertSame($expected, getFrontendUrl($path));
    }

    /**
     * @return array<string, array{0: string|null, 1: string}>
     */
    public static function theWaysCallersWriteAPath(): array {
        return [
            'written with a leading slash' => ['/app/login', 'https://kso.example.org/app/login'],
            'written without one' => ['app/login', 'https://kso.example.org/app/login'],
            'carrying a query string' => [
                '/app/setup/system?github_success=1',
                'https://kso.example.org/app/setup/system?github_success=1',
            ],
            'no path at all' => [null, 'https://kso.example.org'],
            'an empty path' => ['', 'https://kso.example.org'],
        ];
    }

    /**
     * On a developer's machine the interface is not served by kso at all - it is a Vite
     * server on its own port - so the address has to be that one rather than the API's.
     * Without this every login on a developer machine lands back on the API.
     */
    public function testOnADeveloperMachineTheInterfaceIsTheSeparateDevServer(): void {
        $this->arrive(host: 'localhost:8950');

        $this->assertSame('http://localhost:8951', getFrontendUrl());
        $this->assertSame('http://localhost:8951/app/login', getFrontendUrl('app/login'));
    }

    /**
     * The other half of the same case: a developer testing a provider callback has to be
     * reachable from the internet, so kso is exposed through a tunnel and the API is no
     * longer on `localhost`. `DEV_REMOTE_BASE_URL` names that tunnel, and recognising it
     * is what keeps the interface pointing at the local dev server rather than at the
     * tunnel, where nothing is listening.
     */
    public function testATunnelledDeveloperMachineAlsoPointsAtTheLocalDevServer(): void {
        putenv('DEV_REMOTE_BASE_URL=https://tunnel.example.dev/api');
        $this->arrive(host: 'tunnel.example.dev', secure: true);

        $this->assertSame('http://localhost:8951', getFrontendUrl());
    }

    /**
     * An installation that leaves the variable blank is a normal production installation,
     * and an empty needle would otherwise be found in every address there is - sending
     * every production user to a dev server port.
     */
    public function testABlankTunnelVariableDoesNotMakeEveryInstallationADevelopmentOne(): void {
        putenv('DEV_REMOTE_BASE_URL=');
        $this->arrive(host: 'kso.example.org', secure: true);

        $this->assertSame('https://kso.example.org', getFrontendUrl());
    }

    /**
     * The tunnel is only the one that was configured. A different host reached while the
     * variable is set is a real installation and must be treated as one.
     */
    public function testAnUnrelatedHostIsNotMistakenForTheTunnel(): void {
        putenv('DEV_REMOTE_BASE_URL=https://tunnel.example.dev/api');
        $this->arrive(host: 'kso.example.org', secure: true);

        $this->assertSame('https://kso.example.org', getFrontendUrl());
    }

    // </editor-fold>

    /**
     * **Today's behaviour, and it is wrong.** `strtotime_()` swaps every space for a `+`
     * before parsing, to undo what a query string does to the `+` in an offset like
     * `10:00:00+02:00`. It does not distinguish that space from the one between a date and
     * a time, so an ordinary `2024-05-01 10:00:00` becomes `2024-05-01+10:00:00` - midnight
     * at UTC+10 - and comes back as the day before.
     *
     * The parse *succeeds*, so the `if (!$time)` fallback never gets a chance to try the
     * original string.
     *
     * It is latent only because every caller today is handed RFC 3339 by Kubernetes or
     * cert-manager. The moment a MySQL-shaped datetime reaches it - and one column away,
     * `CheckCertificateExpiry` decides whether to warn on the answer - it is silently a day
     * out.
     */
    public function testAnOrdinarySpacedDatetimeComesBackADayEarly(): void {
        $parsed = strtotime_('2024-05-01 10:00:00');

        $this->assertSame('2024-04-30T14:00:00+00:00', gmdate('c', $parsed));
    }

    /**
     * What the space-to-plus swap is actually for: an offset that lost its `+` on the way
     * through a query string still parses.
     */
    public function testAnOffsetThatLostItsPlusIsStillUnderstood(): void {
        $this->assertSame(
            strtotime_('2024-05-01T10:00:00+02:00'),
            strtotime_('2024-05-01T10:00:00 02:00')
        );
    }

    // <editor-fold desc="Fixtures">

    /**
     * Stage a request the way the web server hands one over. kso builds its own base URL
     * from the `Host` header of the request being served - see `Config\App::__construct()`
     * - so the host has to be in place before the configuration and the services that read
     * it are built, which is what the two resets are for.
     */
    private function arrive(string $host, bool $secure = false): void {
        $_SERVER['HTTP_HOST'] = $host;

        unset($_SERVER['HTTPS']);

        if ($secure) {
            $_SERVER['HTTPS'] = 'on';
        }

        Factories::reset('config');
        Services::reset(true);
    }

    protected function setUp(): void {
        parent::setUp();

        // Both of these are set for the whole process by the `.env` the test bootstrap
        // loads, so they are put back as they were rather than simply cleared.
        $this->devRemoteBaseUrl = getenv('DEV_REMOTE_BASE_URL');
        $this->tagName          = getenv('TAG_NAME');
    }

    protected function tearDown(): void {
        $this->restore('DEV_REMOTE_BASE_URL', $this->devRemoteBaseUrl);
        $this->restore('TAG_NAME', $this->tagName);

        unset($_SERVER['HTTP_HOST'], $_SERVER['HTTPS']);

        // Anything built from the staged request is thrown away, so the next test builds
        // its own from whatever it stages rather than inheriting this one's host.
        Factories::reset('config');
        Services::reset(true);

        parent::tearDown();
    }

    private function restore(string $name, string|false $value): void {
        if ($value === false) {
            putenv($name);

            return;
        }

        putenv("{$name}={$value}");
    }

    // </editor-fold>

}
