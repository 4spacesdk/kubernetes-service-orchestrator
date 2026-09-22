<?php namespace App\Entities;

use App\Core\Entity;

/**
 * Class CronJob
 * @package App\Entities
 * @property string $name
 * @property string $schedule
 * @property string $command
 * @property string|double $last_run
 * @property string $last_log
 * @property int $duplicates
 */
class CronJob extends Entity {

    /** Written by every run - the trail records a changed schedule, not that it ran. */
    public const array AuditIgnoredFields = ['last_run', 'last_log'];

    /**
     * @return \ArrayIterator|Entity[]|\Traversable|CronJob[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
