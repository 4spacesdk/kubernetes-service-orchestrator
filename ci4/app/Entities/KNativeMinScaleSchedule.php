<?php namespace App\Entities;

use App\Core\Entity;
use App\Models\DeploymentModel;
use App\Models\KNativeMinScaleScheduleModel;
use Cron\CronExpression;
use DateTime;
use DateTimeZone;
use DebugTool\Data;

/**
 * Class KNativeMinScaleSchedule
 * @package App\Entities
 * @property int $min_scale
 * @property string $cron_expression
 * @property string $timezone
 * @property string $description
 * @property int $priority # the lowest first
 *
 * Many
 * @property DeploymentPackageDeploymentSpecification $deployment_package_deployment_specifications
 * @property Deployment $deployments
 */
class KNativeMinScaleSchedule extends Entity {

    public static function Create(int $minScale, string $cronExpression, string $timezone,
                                  string $description, int $priority): KNativeMinScaleSchedule {
        $item = new KNativeMinScaleSchedule();
        $item->min_scale = $minScale;
        $item->cron_expression = $cronExpression;
        $item->timezone = $timezone;
        $item->description = $description;
        $item->priority = $priority;
        $item->save();
        return $item;
    }

    public static function GetCurrentValueForDeployment(Deployment $deployment): string {
        if (!$deployment->knative_scheduled_minscale_is_enabled) {
            return 0;
        }
        
        /** @var KNativeMinScaleSchedule $schedules */
        $schedules = (new KNativeMinScaleScheduleModel())
            ->whereRelated(DeploymentModel::class, 'id', $deployment->id)
            ->orderBy('priority', 'asc')
            ->find();
        
        $mostRecentSchedule = null;
        $mostRecentRunTime = null;
        
        foreach ($schedules as $schedule) {
            $cron = new CronExpression($schedule->cron_expression);
            $timezone = new DateTimeZone($schedule->timezone);
            
            $nowInTimezone = new DateTime('now', $timezone);
            $previousRunTime = $cron->getPreviousRunDate($nowInTimezone, 0, true);
            
            if ($mostRecentRunTime === null ||
                $previousRunTime > $mostRecentRunTime ||
                ($previousRunTime == $mostRecentRunTime && ($mostRecentSchedule === null || $schedule->priority < $mostRecentSchedule->priority))) {
                $mostRecentSchedule = $schedule;
                $mostRecentRunTime = $previousRunTime;
            }
        }
        
        $minScale = (string)($mostRecentSchedule ? $mostRecentSchedule->min_scale : 0);
        Data::debug('min scale for deployment', $deployment->name, 'is', $minScale, 'based on schedule', $mostRecentSchedule ? $mostRecentSchedule->id : 'none');
        return $minScale;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|KNativeMinScaleSchedule[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
