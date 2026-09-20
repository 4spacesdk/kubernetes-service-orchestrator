<?php namespace App\Tests\Fakes;

use App\Libraries\GoogleCloud\BasePubSub;

/**
 * Pub/Sub without Google.
 *
 * `$messages` is what a pull returns, keyed by project so a test can give two projects
 * different queues. Everything the code asked for is recorded, because with this
 * integration the request is half the behaviour: subscribing under the wrong name is how
 * auto updates stop without anything failing.
 */
class FakePubSub extends BasePubSub {

    /** @var array<string, string[]> project => raw message payloads */
    public array $messages = [];

    /** @var array<array{project: string, topic: string}> */
    public array $topicsEnsured = [];

    /** @var array<array{project: string, topic: string, subscription: string}> */
    public array $subscriptionsEnsured = [];

    /** @var array<array{project: string, topic: string, subscription: string}> */
    public array $pulls = [];

    /** Set to make `pull()` fail, the way an expired service account key does. */
    public ?\Throwable $failPullWith = null;

    public function ensureTopic(string $project, string $serviceAccountKey, string $topic): void {
        $this->topicsEnsured[] = ['project' => $project, 'topic' => $topic];
    }

    public function ensureSubscription(
        string $project,
        string $serviceAccountKey,
        string $topic,
        string $subscription
    ): void {
        $this->subscriptionsEnsured[] = [
            'project' => $project,
            'topic' => $topic,
            'subscription' => $subscription,
        ];
    }

    public function pull(
        string $project,
        string $serviceAccountKey,
        string $topic,
        string $subscription
    ): array {
        $this->pulls[] = [
            'project' => $project,
            'topic' => $topic,
            'subscription' => $subscription,
        ];

        if ($this->failPullWith !== null) {
            throw $this->failPullWith;
        }

        // A pull acknowledges what it read, so the queue is empty the next time. Handing
        // the same message back on every pull would make the five pulls in
        // `PullContainerRegistries::run()` look like five pushes.
        $messages = $this->messages[$project] ?? [];
        $this->messages[$project] = [];

        return $messages;
    }

}
