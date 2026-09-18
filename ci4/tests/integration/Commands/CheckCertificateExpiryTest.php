<?php namespace App\Tests\Integration\Commands;

use App\ClusterTestCase;
use App\Commands\CheckCertificateExpiry;
use App\Entities\Domain;
use App\Fixtures;
use App\Libraries\Kubernetes\KubeCertificate;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The nightly job that warns before a certificate runs out.
 *
 * Its one decision is a window: alert when the certificate expires within the configured
 * number of days, **and only for three days running**, so an operator gets three mails
 * rather than one a night for a month. `$dayDiff <= threshold && ($dayDiff + 3) > threshold`
 * is the whole of it, and an off-by-one there is either silence or a nightly nuisance.
 *
 * The expiry comes from cert-manager's status, which no controller writes in the test
 * cluster - so the test writes it, exactly as cert-manager would. The mail itself is out of
 * coverage; what the job *decided* goes into its own log, and that is what is read here.
 */
class CheckCertificateExpiryTest extends ClusterTestCase {

    private const Threshold = 30;

    #[DataProvider('theWindow')]
    public function testTheAlertWindowIsThreeDaysWideEndingAtTheThreshold(int $daysLeft, bool $alerts): void {
        $domain = $this->monitoredDomain();
        $this->certificateExpiringIn($domain, $daysLeft);

        $log = $this->runTheJob();

        $this->assertSame(
            $alerts,
            str_contains($log, "alerting on {$domain->name}") && !str_contains($log, 'not alerting'),
            "{$daysLeft} days left"
        );
    }

    /**
     * @return array<string, array{0: int, 1: bool}>
     */
    public static function theWindow(): array {
        return [
            'well inside the threshold, but the window has passed' => [26, false],
            'the last day of the window' => [28, true],
            'the day the threshold is crossed' => [30, true],
            'one day before the threshold' => [31, false],
            'nowhere near' => [90, false],
        ];
    }

    /**
     * **Today's behaviour, and the sharp end of FEAT-17.** A certificate cert-manager has
     * not got to yet has no status, `KubeCertificate::getStatus()` is declared to return an
     * array and returns null, and PHP raises a `TypeError`.
     *
     * A `TypeError` is an `Error`, not an `Exception`, so the `catch (\Exception)` inside
     * the loop does not catch it: **the whole nightly job dies on the first such domain**,
     * and every domain after it is never checked. That is the ordinary state of a
     * certificate requested an hour ago - and of every certificate in a cluster where
     * cert-manager is not installed.
     */
    public function testACertificateWithoutAnExpiryKillsTheWholeJob(): void {
        $domain = $this->monitoredDomain();
        (new KubeCertificate($domain))->apply($this->cluster());

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('must be of type array, null returned');

        $this->runTheJob();
    }

    /**
     * Monitoring is opt-in per domain, and a domain without it must not be looked up at
     * all - the lookup costs a cluster call per domain per night.
     */
    public function testADomainWithoutMonitoringIsNotLookedAtAtAll(): void {
        $domain = $this->monitoredDomain(['has_certificate_monitoring' => false]);
        $this->certificateExpiringIn($domain, 1);

        $this->assertStringNotContainsString($domain->name, $this->runTheJob());
    }

    /**
     * A domain whose certificate is not there at all throws inside the loop, and the loop
     * swallows it and carries on - otherwise one broken domain would stop the job for every
     * other domain that night.
     */
    public function testOneUnreachableCertificateDoesNotStopTheRest(): void {
        $broken = $this->monitoredDomain(['name' => 'broken.example.org']);
        $broken->certificate_namespace = $this->testNamespace . '-not-a-namespace';
        $broken->save();
        $healthy = $this->monitoredDomain(['name' => 'healthy.example.org', 'certificate_name' => 'healthy-cert']);
        $this->certificateExpiringIn($healthy, 90);

        $this->assertStringContainsString("not alerting on {$healthy->name}", $this->runTheJob());
    }

    public function testTheJobRecordsWhenItLastRan(): void {
        $before = $this->cronJob()['last_run'];

        $this->runTheJob();

        $this->assertNotSame($before, $this->cronJob()['last_run']);
    }

    // <editor-fold desc="Fixtures">

    /**
     * Runs the command and hands back what it wrote into its own log.
     *
     * The debugger's store is a static that lives for the whole process, so without
     * clearing it first each run's log would carry every earlier run's lines with it - and
     * an assertion about what *this* run decided would read the last one's answer.
     */
    private function runTheJob(): string {
        $store = (new \ReflectionClass(\DebugTool\Data::class))->getProperty('store');
        $store->setValue(null, ['status' => null]);

        (new CheckCertificateExpiry(service('logger'), service('commands')))->run([]);

        return (string) $this->cronJob()['last_log'];
    }

    /**
     * @return array<string, mixed>
     */
    private function cronJob(): array {
        return db_connect()->table('cron_jobs')
            ->where('id', \CronJobIds::CheckCertificateExpiry)
            ->get()->getRowArray();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function monitoredDomain(array $overrides = []): Domain {
        $this->cluster()->namespace()->setName($this->testNamespace)->createOrUpdate();

        return Fixtures::domain(array_merge([
            'name' => 'test.example.org',
            'certificate_name' => 'test-cert',
            'certificate_namespace' => $this->testNamespace,
            'issuer_ref_name' => 'test-issuer',
            'has_certificate_monitoring' => true,
            'certificate_monitoring_days_before_expiry' => self::Threshold,
        ], $overrides));
    }

    /**
     * Apply the certificate, then write the status cert-manager would have written. Nothing
     * else puts a `notAfter` on it - there is no cert-manager controller here - and without
     * one the job has nothing to decide about.
     */
    private function certificateExpiringIn(Domain $domain, int $days): void {
        (new KubeCertificate($domain))->apply($this->cluster());

        $path = "/apis/cert-manager.io/v1/namespaces/{$domain->certificate_namespace}"
            . "/certificates/{$domain->certificate_name}/status";
        $resource = json_decode($this->cluster()->call('GET', $path)->getBody()->getContents(), true);
        // Noon on the target day, not this instant on it.
        //
        // The job computes `floor((expiry - time()) / DAY)`, so an expiry written as exactly
        // `+31 days` reads back as 31 only while the clock is still in the second it was
        // written in - and as 30 from the next second onwards. Applying the certificate and
        // two calls to the api server take longer than that often enough that this test
        // failed perhaps one run in three, always on the data set either side of the
        // threshold. Half a day of slack puts the value out of reach of how long the setup
        // takes, without moving which whole day the certificate expires on.
        $noon = 12 * 3600;

        $resource['status'] = [
            'notAfter' => date('c', strtotime("+{$days} days") + $noon),
            'notBefore' => date('c', strtotime('-60 days')),
            'renewalTime' => date('c', strtotime("+" . max(0, $days - 30) . " days") + $noon),
        ];

        $this->cluster()->call('PUT', $path, json_encode($resource));
    }

    // </editor-fold>

}
