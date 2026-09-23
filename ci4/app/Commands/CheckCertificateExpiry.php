<?php namespace App\Commands;

use App\Entities\CronJob;
use App\Entities\Domain;
use App\Entities\User;
use App\Libraries\EmailLib;
use App\Libraries\Kubernetes\KubeAuth;
use App\Libraries\Kubernetes\KubeCertificate;
use App\Libraries\Kubernetes\KubeHelper;
use App\Models\DomainModel;
use App\Models\UserModel;
use CodeIgniter\CLI\BaseCommand;
use DebugTool\Data;

class CheckCertificateExpiry extends BaseCommand {

    public $group = 'app';
    public $name = 'app:check-certificate-expiry';
    public $description = '';
    protected $arguments = [

    ];
    protected $options = [

    ];

    public function run(array $params) {
        Data::debug(get_class($this), "CheckCertificateExpiry");

        $job = new CronJob();
        $job->find(\CronJobIds::CheckCertificateExpiry);
        $job->last_run = date('Y-m-d H:i:s');
        $job->save();

        /** @var Domain $domain */
        $domains = (new DomainModel())
            ->where('has_certificate_monitoring', true)
            ->find();
        foreach ($domains as $domain) {

            try {
                $auth = new KubeAuth();
                $cluster = $auth->authenticate();
                $certificate = new KubeCertificate($domain);
                $status = $certificate->getStatus($cluster);
                if (isset($status['notAfter'])) {
                    $expiry = strtotime_($status['notAfter']);
                    $dayDiff = floor(($expiry - time()) / DAY);
                    // Max 3 alerts
                    if ($dayDiff <= $domain->certificate_monitoring_days_before_expiry
                        && ($dayDiff + 3) > $domain->certificate_monitoring_days_before_expiry) {
                        Data::debug('alerting on', $domain->name, 'expires in', $dayDiff, 'days');
                        $this->alert(
                            $domain,
                            strtotime_($status['notAfter']),
                            strtotime_($status['renewalTime'])
                        );
                    } else {
                        Data::debug('not alerting on', $domain->name, 'expires in', $dayDiff, 'days');
                    }
                } else {
                    Data::debug('no expiry known for', $domain->name);
                }
            } catch (\Throwable $e) {
                // One domain must not end the run: a `\Throwable`, because an `\Error` is
                // not an `\Exception` and this loop used to die on the first domain whose
                // certificate cert-manager had not finished.
                Data::debug('skipped', $domain->name, KubeHelper::PrintException($e));
            }
        }

        $job->last_log = json_encode(Data::getDebugger(), JSON_PRETTY_PRINT);
        $job->save();
    }

    /**
     * Not measured: from here down it is an email conversation. Whether we *should* send
     * one is decided above and written into the job's own log, which is what a test can
     * read - see `CheckCertificateExpiryTest`.
     *
     * @throws \Exception
     * @codeCoverageIgnore
     */
    private function alert(Domain $domain, string $expirationDte, string $renewalDate): void {
        $dayDiff = floor(($expirationDte - time()) / DAY);
        $emailLib = new EmailLib();

        /** @var User $users */
        $users = (new UserModel())
            ->where('username !=', '')
            ->find();
        foreach ($users as $user) {
            $emailLib->send(
                EmailLib::Subject('ALERT | Certificate expiration notification'),
                implode('<br>', [
                    "Domain certificate {$domain->name} is going to expire in {$dayDiff} days.",
                    "Expiration Date: " . date('Y-m-d', $expirationDte),
                    "Renewal Date: " . date('Y-m-d', $renewalDate),
                    "Click <a href=\"" . getFrontendUrl() . "\">here</a> to see more.",
                ]),
                $user->first_name,
                $user->username
            );
        }
    }

}
