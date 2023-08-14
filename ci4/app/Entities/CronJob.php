<?php namespace App\Entities;

use RestExtension\Core\Entity;

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

    public function run() {

    }

    /**
     * @return \ArrayIterator|Entity[]|\Traversable|CronJob[]
     */
    public function getIterator() {
        return parent::getIterator();
    }

}
