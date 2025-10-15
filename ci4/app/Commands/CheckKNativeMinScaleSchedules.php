<?php namespace App\Commands;

use App\Entities\CronJob;
use App\Entities\Deployment;
use App\Entities\KNativeMinScaleSchedule;
use App\Models\DeploymentModel;
use App\Models\KNativeMinScaleScheduleModel;
use CodeIgniter\CLI\BaseCommand;
use Cron\CronExpression;
use DateTime;
use DateTimeZone;
use DebugTool\Data;

class CheckKNativeMinScaleSchedules extends BaseCommand {

    public $group = 'app';
    public $name = 'app:check-knative-min-scale-schedules';
    public $description = '';
    protected $arguments = [

    ];
    protected $options = [

    ];

    public function run(array $params) {
        Data::debug(get_class($this), "CheckKNativeMinScaleSchedules");

        $job = new CronJob();
        $job->find(\CronJobIds::CheckKNativeMinScaleSchedules);
        $job->last_run = date('Y-m-d H:i:s');
        $job->save();

        $this->work();

        $job->last_log = json_encode(Data::getDebugger(), JSON_PRETTY_PRINT);
        $job->save();
    }

    private function work(): void {
        /** @var Deployment $deploymentsWithScheduleEnabled */
        $deploymentsWithScheduleEnabled = (new DeploymentModel())
            ->where('knative_scheduled_minscale_is_enabled', true)
            ->find();
        Data::debug('found', $deploymentsWithScheduleEnabled->count(), 'deployments with min scale schedule enabled');;
        foreach ($deploymentsWithScheduleEnabled as $deployment) {

            /** @var KNativeMinScaleSchedule $schedules */
            $schedules = (new KNativeMinScaleScheduleModel())
                ->whereRelated(DeploymentModel::class, 'id', $deployment->id)
                ->orderBy('priority', 'asc')
                ->find();
            Data::debug('found', $schedules->count(), 'schedules for deployment', $deployment->name);

            $isDue = $this->checkForDue($schedules);
            if ($isDue) {
                $this->applyMinScale($deployment, $isDue);
            }
        }
    }

    private function checkForDue(KNativeMinScaleSchedule $schedules): ?KNativeMinScaleSchedule {
        foreach ($schedules as $schedule) {
            $cron = new CronExpression($schedule->cron_expression);
            $timezone = new DateTimeZone($schedule->timezone);
            $nowInTimezone = new DateTime('now', $timezone);
            if ($cron->isDue($nowInTimezone)) {
                return $schedules;
            }
        }
        return null;
    }

    private function applyMinScale(Deployment $deployment, KNativeMinScaleSchedule $schedule): void {
        Data::debug('applying min scale', $schedule->min_scale, 'to deployment', $deployment->name);
        $deployment->updateKNativeMinScale($schedule->min_scale);
    }

}
