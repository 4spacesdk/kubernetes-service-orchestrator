<?php namespace App\Jobs;

use App\Libraries\Push\ChangeEvent;
use App\Libraries\Push\EventHandlers;
use CodeIgniter\Queue\BaseJob;

/**
 * An event kso acts on, taken off the queue by `spark queue:work events`.
 *
 * One try. A webhook that answered is recorded as a delivery whatever it answered, and can be
 * retried by hand from there; a rollout that got halfway is not something to start again
 * unattended. A job that throws is kept in `queue_jobs_failed` instead.
 */
class HandleEvent extends BaseJob {

    protected int $tries = 1;

    public function process() {
        EventHandlers::Handle($this->data['event'], ChangeEvent::Parse($this->data['data']));
    }

}
