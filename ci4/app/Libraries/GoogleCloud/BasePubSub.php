<?php namespace App\Libraries\GoogleCloud;

/**
 * The Pub/Sub topic a Google Artifact Registry publishes pushes to.
 *
 * Three operations: make sure the topic exists, make sure we are subscribed to it, and
 * read what has arrived. Google's own `Message` objects stop here - `pull()` hands back
 * the payloads as strings, because that is all kso has ever done with them.
 *
 * Stateless on purpose. The project and its service account key come from the container
 * image that is being asked about, and passing them per call keeps this replaceable
 * without threading construction through every caller.
 */
abstract class BasePubSub {

    abstract public function ensureTopic(string $project, string $serviceAccountKey, string $topic): void;

    abstract public function ensureSubscription(
        string $project,
        string $serviceAccountKey,
        string $topic,
        string $subscription
    ): void;

    /**
     * Read and acknowledge whatever is waiting.
     *
     * @return string[] the raw message payloads, in arrival order
     */
    abstract public function pull(
        string $project,
        string $serviceAccountKey,
        string $topic,
        string $subscription
    ): array;

}
