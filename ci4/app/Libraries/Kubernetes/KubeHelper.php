<?php namespace App\Libraries\Kubernetes;

use DebugTool\Data;
use GuzzleHttp\Exception\ServerException;
use PodioBadRequestError;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sResource;

class KubeHelper {

    /**
     * How Kubernetes says "somebody else wrote to this while you were reading it".
     */
    public const ConflictCode = 409;

    /**
     * How Kubernetes says "that is not here".
     */
    public const NotFoundCode = 404;

    /**
     * Send a resource to the cluster, and try again if it was written to underneath us.
     *
     * `createOrUpdate()` on an existing resource is two calls: a GET for the current
     * `resourceVersion`, then a PUT carrying it. If anything writes to the resource in
     * between, the api server rejects the PUT with a 409 - that is the whole point of the
     * resource version, and it is how Kubernetes stops two writers losing each other's
     * changes.
     *
     * Something does write in between. A controller updates its resource's `status` field
     * constantly while a rollout is in progress, so the deploy most likely to hit this is
     * a redeploy of a workspace that is still coming up. Left alone, the step throws and
     * the deployment is half applied.
     *
     * The fix is the one Kubernetes prescribes for its own clients: read again, write
     * again. `update()` re-reads the resource version on every attempt, so a plain retry
     * is all it takes. A conflict means the other writer got there first, not that our
     * change was wrong.
     *
     * Only 409 is retried. Everything else - a rejected manifest, a missing namespace, no
     * permission - would fail the same way however many times it is sent.
     *
     * @throws KubernetesAPIException
     */
    public static function Apply(K8sResource $resource, int $attempts = 4): void {
        for ($attempt = 1; ; $attempt++) {
            try {
                $resource->createOrUpdate();

                return;
            } catch (KubernetesAPIException $e) {
                if ($e->getCode() !== self::ConflictCode || $attempt >= $attempts) {
                    throw $e;
                }

                Data::debug('KubeHelper Apply conflict, attempt', $attempt);

                // The writer we lost to is a controller reacting to our own last change,
                // so it is done in milliseconds. Backing off further and further keeps a
                // busy resource from being hammered.
                usleep(100000 * (2 ** ($attempt - 1)));
            }
        }
    }

    /**
     * Takes a `\Throwable`, not an `\Exception`: a caller that catches broadly - a status
     * endpoint, say - would otherwise fail here on the `\Error` it just caught.
     */
    public static function PrintException(\Throwable $e): string {
        Data::debug('KubeHelper PrintException', get_class($e));
        switch (get_class($e)) {
            case ServerException::class:
                return self::PrintServerException($e);
            case KubernetesAPIException::class:
                return self::PrintKubernetesAPIException($e);
            case PodioBadRequestError::class:
                return $e->__toString();
            default:
                return $e->getMessage();
        }
    }

    private static function PrintServerException(ServerException $e): string {
        $content = $e->getResponse()->getBody()->getContents();
        Data::debug(json_decode($content));
        return $content;
    }

    private static function PrintKubernetesAPIException(KubernetesAPIException $e): string {
        $content = $e->getPayload();
        Data::debug($e->getMessage());
        Data::debug($content);
        return $content ? json_encode($content) : $e->getMessage();
    }

    /**
     * Run something that talks over a websocket, and hand back what it returned together
     * with the rejection nobody handled, if there was one.
     *
     * php-k8s does `exec` and log watching on a ReactPHP loop. When the api server refuses -
     * the container is not running, say - the failure arrives as a rejected promise, and
     * react/promise writes a line with `error_log()` and moves on. The caller is handed an
     * empty result and no exception, so the page showed an empty box and the reason was in
     * the server's log.
     *
     * @template T
     * @param callable(): T $work
     * @return array{0: T, 1: ?\Throwable}
     */
    public static function RunWatchingForRejections(callable $work): array {
        $rejection = null;
        $previous = \React\Promise\set_rejection_handler(static function (\Throwable $reason) use (&$rejection): void {
            $rejection ??= $reason;
        });

        try {
            $result = $work();
        } finally {
            \React\Promise\set_rejection_handler($previous);
        }

        return [$result, $rejection];
    }

    /**
     * The lines a command printed, from the frames php-k8s collected during an exec.
     *
     * The stdout frames are joined before anything is split. How the output is cut into
     * frames is up to the network, so a line - or a CRLF - can end up across two of them.
     * The exec runs with a tty, which is why the line endings are CRLF.
     *
     * @param array<array{channel: string, output: string}> $messages
     * @return string[] Always at least one element, '' when nothing was printed.
     */
    public static function ExecOutputLines(array $messages): array {
        $output = '';
        foreach ($messages as $message) {
            if (($message['channel'] ?? null) === 'stdout') {
                $output .= $message['output'];
            }
        }
        return preg_split('/\r?\n/', rtrim($output, "\r\n"));
    }

    public static function GetMyNamespace(): string {
        if (file_exists('/var/run/secrets/kubernetes.io/serviceaccount/namespace')) {
            return file_get_contents('/var/run/secrets/kubernetes.io/serviceaccount/namespace');
        } else if (getenv('KUBERNETES_MY_NAMESPACE')) {
            return getenv('KUBERNETES_MY_NAMESPACE');
        } else {
            return 'default';
        }
    }

    public static function GetMyHostname(): string {
        $hostname = gethostname();
        $hostname = explode('-', $hostname);
        return $hostname[0];
    }

}
