<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class DeploymentCronJob
 * @package App\Entities
 * @property int $deployment_id
 * @property Deployment $deployment
 * @property int $k8s_cron_job_id
 * @property K8sCronJob $k8s_cron_job
 * @property int $position
 */
class DeploymentCronJob extends Entity {

    public static function Create(int $cronJobId, int $pos): DeploymentCronJob {
        $item = new DeploymentCronJob();
        $item->k8s_cron_job_id = $cronJobId;
        $item->position = $pos;
        $item->save();
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DeploymentCronJob[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
