<?php namespace App\Libraries\GoogleCloud;

use DebugTool\Data;
use Google\Cloud\PubSub\Message;
use Google\Cloud\PubSub\PubSubClient;

class GoogleCloudPubSub {

    private PubSubClient $client;

    public function __construct(string $project, string $serviceAccountKey) {
        $this->client = new PubSubClient([
            'project' => $project,
            'credentials' => json_decode($serviceAccountKey, true),
        ]);
    }

    public function ensureTopic(string $topic): void {
        $topic = $this->client->topic($topic);
        if (!$topic->exists()) {
            $topic->create();
        }
    }

    public function ensureSubscription(string $topic, string $subscription): void {
        $subscription = $this->client->subscription($subscription, $topic);
        if (!$subscription->exists()) {
            $subscription->create();
        }
    }

    /**
     * @param string $topic
     * @param string $subscription
     * @return Message[]
     */
    public function pull(string $topic, string $subscription): array {
        $subscription = $this->client->subscription($subscription, $topic);
        $messages = $subscription->pull([
            'returnImmediately' => true,
        ]);
        if (count($messages)) {
            try {
                $subscription->acknowledgeBatch($messages);
            } catch (\Exception $e) {
                Data::debug($e->getMessage());
            }
        }
        return $messages;
    }

}
