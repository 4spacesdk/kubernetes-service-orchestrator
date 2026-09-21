<?php namespace App\Commands;

use App\Entities\CronJob;
use App\Libraries\ImageScanning\ImageScanner;
use CodeIgniter\CLI\BaseCommand;
use DebugTool\Data;

/**
 * Scan the customers' running images for known vulnerabilities with Trivy.
 *
 *   app:scan-container-images          every tag a deployment runs - nightly
 *   app:scan-container-images queued   only what "Scan now" queued - every minute
 *
 * Nightly because vulnerabilities are published for images that have not changed; that is
 * most of what this finds. The queued run is how the button gets a scan without starting a
 * process from a web request.
 */
class ScanContainerImages extends BaseCommand {

    public $group = 'app';
    public $name = 'app:scan-container-images';
    public $description = 'Scan the running container images for known vulnerabilities';
    protected $usage = 'app:scan-container-images [queued]';
    protected $arguments = [
        'queued' => 'Only scan what has been queued',
    ];
    protected $options = [

    ];

    public function run(array $params) {
        $onlyQueued = ($params[0] ?? null) === 'queued';
        Data::debug(get_class($this), $onlyQueued ? 'queued' : 'all running');

        $job = new CronJob();
        $job->find($onlyQueued ? \CronJobIds::ScanQueuedContainerImages : \CronJobIds::ScanContainerImages);
        $job->last_run = date('Y-m-d H:i:s');
        $job->save();

        $scanner = new ImageScanner();
        $result = $onlyQueued ? $scanner->scanQueued() : $scanner->scanAllRunning();
        Data::debug('scanned', $result['scanned'], 'failed', $result['failed'], 'removed', $result['removed'] ?? 0);

        $job->last_log = json_encode(Data::getDebugger(), JSON_PRETTY_PRINT);
        $job->save();
    }

}
