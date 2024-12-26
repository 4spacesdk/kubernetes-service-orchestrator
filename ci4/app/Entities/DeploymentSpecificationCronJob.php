<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class DeploymentSpecificationCronJob
 * @package App\Entities
 * @property int $deployment_specification_id
 * @property DeploymentSpecification $deployment_specification
 * @property int $k8s_cron_job_id
 * @property K8sCronJob $k8s_cron_job
 * @property int $position
 */
class DeploymentSpecificationCronJob extends Entity {

    public static function Create(int $cronJobId, int $pos): DeploymentSpecificationCronJob {
        $item = new DeploymentSpecificationCronJob();
        $item->k8s_cron_job_id = $cronJobId;
        $item->position = $pos;
        $item->save();
        return $item;
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|DeploymentSpecificationCronJob[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
