<?php namespace Config;

use App\Jobs\HandleEvent;
use App\Libraries\Push\EventHandlers;
use CodeIgniter\Queue\Config\Queue as BaseQueue;

/**
 * The job queue, in kso's own database. See `Libraries\Push\EventHandlers`.
 */
class Queue extends BaseQueue {

    public string $defaultHandler = 'database';

    public array $jobHandlers = [
        EventHandlers::Job => HandleEvent::class,
    ];

}
