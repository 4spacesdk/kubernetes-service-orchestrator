<?php namespace App\Libraries\Push;

use DebugTool\Data;
use phpcent\Client;

/**
 * Where kso's events go: to Centrifugo, which pushes them to every browser that has the
 * channel open, and - for the few events kso acts on itself - onto the job queue.
 *
 * **The two have different promises.** A push is a notification: if Centrifugo is down, the
 * browser misses an update it would have redrawn anyway on the next load, so a failed publish
 * is logged and forgotten. A job is work - a webhook, a rollout - so it goes into a table in
 * the database, where it waits until a worker has done it, whether or not anything was
 * listening when it was raised. See `EventHandlers` for which events those are.
 *
 * Publishing is an HTTP call to Centrifugo's API on the pod's own internal port, with short
 * timeouts. The migration job runs `php spark migrate` without the sidecar, and cron runs
 * inside the pod but may run while Centrifugo restarts; neither may hang on a push.
 */
class Publisher {

    private static ?Publisher $instance = null;

    public static function getInstance(): Publisher {
        if (!self::$instance) {
            self::$instance = new Publisher(self::clientFromEnvironment(), true);
        }
        return self::$instance;
    }

    /**
     * Seconds. Centrifugo is on localhost; anything slower than this is not coming.
     */
    private const CONNECT_TIMEOUT = 1;
    private const TIMEOUT = 2;

    /**
     * No url, no push - the migration job and the test suite, which have no Centrifugo.
     */
    private static function clientFromEnvironment(): ?Client {
        $url = env('CENTRIFUGO_API_URL', '');
        if (!$url) {
            return null;
        }
        $client = new Client($url, env('CENTRIFUGO_HTTP_API_KEY', ''));
        $client->setConnectTimeoutOption(self::CONNECT_TIMEOUT);
        $client->setTimeoutOption(self::TIMEOUT);
        $client->setUseAssoc(true);
        return $client;
    }

    /**
     * @param Client|null $client Where browser pushes go. Null sends none.
     * @param bool $enqueue Whether events with a handler are put on the queue.
     */
    public function __construct(private readonly ?Client $client, private readonly bool $enqueue) {
    }

    public function send(string $event, array $data): void {
        $this->publish($event, $data);
        if ($this->enqueue) {
            $this->enqueue($event, $data);
        }
    }

    /**
     * The channel is the event name. The payload keeps the shape the browser has always
     * read - `{event, data}` - so the components did not have to change with the transport.
     */
    private function publish(string $event, array $data): void {
        if (!$this->client) {
            return;
        }
        try {
            // Centrifugo answers an error it understood with a 200 and an `error` field.
            $response = $this->client->publish($event, ['event' => $event, 'data' => $data]);
            if (is_array($response) && isset($response['error'])) {
                Data::debug('Push to', $event, 'refused:', $response['error']['message'] ?? json_encode($response['error']));
            }
        } catch (\Throwable $e) {
            Data::debug('Push to', $event, 'failed:', $e->getMessage());
        }
    }

    private function enqueue(string $event, array $data): void {
        $handler = EventHandlers::For($event);
        if (!$handler) {
            return;
        }
        try {
            $queue = service('queue');
            if ($handler['delay'] > 0) {
                $queue->setDelay($handler['delay']);
            }
            $result = $queue->push(EventHandlers::Queue, EventHandlers::Job, ['event' => $event, 'data' => $data]);
            if (!$result->getStatus()) {
                Data::debug('Could not queue', $event, ':', $result->getError());
            }
        } catch (\Throwable $e) {
            // The queue's table does not exist until its migration has run, and a migration
            // before that one can raise an event.
            Data::debug('Could not queue', $event, ':', $e->getMessage());
        }
    }

}
