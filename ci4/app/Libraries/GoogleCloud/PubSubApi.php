<?php namespace App\Libraries\GoogleCloud;

use DebugTool\Data;
use Google\Cloud\PubSub\PubSubClient;

/**
 * Pub/Sub over the wire.
 *
 * Not measured: every method here is a call to Google's Pub/Sub and the mapping of its answer.
 * What kso decides is tested through the fake behind `BasePubSub`, which is the whole point
 * of that interface - see the strategy note in the test setup.
 *
 * @codeCoverageIgnore
 */
class PubSubApi extends BasePubSub {

    public function ensureTopic(string $project, string $serviceAccountKey, string $topic): void {
        $topic = $this->connect($project, $serviceAccountKey)->topic($topic);
        if (!$topic->exists()) {
            $topic->create();
        }
    }

    public function ensureSubscription(
        string $project,
        string $serviceAccountKey,
        string $topic,
        string $subscription
    ): void {
        $subscription = $this->connect($project, $serviceAccountKey)->subscription($subscription, $topic);
        if (!$subscription->exists()) {
            $subscription->create();
        }
    }

    public function pull(
        string $project,
        string $serviceAccountKey,
        string $topic,
        string $subscription
    ): array {
        Data::debug($topic, $subscription);

        $subscription = $this->connect($project, $serviceAccountKey)->subscription($subscription, $topic);
        $messages = $subscription->pull(['returnImmediately' => true]);

        if (count($messages)) {
            try {
                // Acknowledging is what stops the same push being handled twice. A failure
                // here is logged and swallowed on purpose: the messages have been read, and
                // failing the run would leave them unacknowledged anyway.
                $subscription->acknowledgeBatch($messages);
            } catch (\Exception $e) {
                Data::debug($e->getMessage());
            }
        }

        return array_map(static fn ($message) => $message->data(), $messages);
    }

    private function connect(string $project, string $serviceAccountKey): PubSubClient {
        return new PubSubClient([
            'project' => $project,
            'credentials' => json_decode($serviceAccountKey, true),
        ]);
    }

}
