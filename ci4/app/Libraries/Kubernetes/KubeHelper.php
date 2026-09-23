<?php namespace App\Libraries\Kubernetes;

use DebugTool\Data;
use GuzzleHttp\Exception\BadResponseException;
use PodioBadRequestError;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;
use RenokiCo\PhpK8s\Kinds\K8sResource;

class KubeHelper {

    /**
     * What every write kso sends carries, and the reason it is not just `pretty`.
     *
     * Kubernetes **drops a field it does not recognise** and answers with a warning header
     * and a `201 Created` - not an error - unless the client asks for `fieldValidation`.
     * `kubectl apply` asks; php-k8s does not. So a field spelt wrong, renamed in a newer
     * api version, or put one level too deep was removed on the way in: the resource was
     * created without it, nothing failed, nothing was logged, and the missing behaviour
     * turned up later as an operational fault somewhere else.
     *
     * Verified against a live api server both ways: `kubectl apply` refuses
     * `spec.selectorX` with a strict decoding error, while the same manifest posted through
     * php-k8s came back `201 Created` with `selector` simply absent from the stored spec.
     *
     * `Strict` makes that a hard failure at deploy time. An api server older than 1.23 does
     * not know the parameter and ignores it, which is the behaviour kso had anyway.
     *
     * `pretty` is kept because it is php-k8s' own default for these calls.
     *
     * @var array<string, int|string>
     */
    public const StrictQuery = ['pretty' => 1, 'fieldValidation' => 'Strict'];

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
                $resource->createOrUpdate(self::StrictQuery);

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

        // `instanceof`, not `get_class()`. Guzzle answers a 4xx with a `ClientException` and
        // a 5xx with a `ServerException`, and both extend `BadResponseException` - so
        // matching the class name exactly caught the 5xx and let every 4xx fall through to
        // `getMessage()`. That is the wrong half to lose: the api server answers **422** for
        // a manifest it refuses, and its body says which field is wrong, while Guzzle's
        // message carries the same body cut to 120 characters. The most common mistake an
        // operator can make had its explanation truncated.
        if ($e instanceof BadResponseException) {
            return self::PrintBadResponseException($e);
        }
        if ($e instanceof KubernetesAPIException) {
            return self::PrintKubernetesAPIException($e);
        }
        if ($e instanceof PodioBadRequestError) {
            return $e->__toString();
        }

        return $e->getMessage();
    }

    private static function PrintBadResponseException(BadResponseException $e): string {
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

    /**
     * The dated rows a container's log becomes, from the text Kubernetes returned.
     *
     * Split on the first space rather than at a character position. With `timestamps` on,
     * Kubernetes prefixes each line with RFC3339Nano and a space - and that prefix is **not
     * a fixed width**: trailing zeroes in the fraction are dropped, so a whole second is
     * `2026-09-20T08:00:00Z`, twenty characters, while `2026-09-20T08:00:00.123456789Z` is
     * thirty. Cutting at thirty therefore ate the first characters of the line itself
     * whenever the clock was round, which it is whenever anything logs on a timer.
     *
     * The two callers disagreed on top of that - one took `substr($log, 30)`, the other
     * `substr($log, 31)` - so the log page and the live tail of the same container showed
     * lines that differed by one character.
     *
     * @return array<array{date: string, line: string}> one row per non-empty line
     */
    public static function LogLines(string $log): array {
        $lines = [];

        foreach (explode("\n", $log) as $line) {
            if (!strlen($line)) {
                continue;
            }

            $space = strpos($line, ' ');
            // A timestamp starts with its year; a kubelet's own words do not.
            if ($space === false || !ctype_digit($line[0])) {
                // No prefix to take off. Keeping the text is the right way to be wrong about
                // it - a line shown under no date is readable, a date with the line inside it
                // is not. It does happen with `timestamps` on: a kubelet that has lost the
                // previous container's log answers 200 with "unable to retrieve container logs
                // for ..." as the log, and its first word was taken for a date.
                $lines[] = ['date' => '', 'line' => $line];
                continue;
            }

            $lines[] = ['date' => substr($line, 0, $space), 'line' => substr($line, $space + 1)];
        }

        return $lines;
    }

    /**
     * A resource as the template inside another resource.
     *
     * php-k8s serialises a `K8sPod` or a `K8sJob` whole, `apiVersion` and `kind` included -
     * but a `PodTemplateSpec` and a `JobTemplateSpec` have neither. With
     * `fieldValidation=Strict` the api server refuses the manifest and says exactly that:
     *
     *     strict decoding error: unknown field "spec.template.apiVersion",
     *                            unknown field "spec.template.kind"
     *     strict decoding error: unknown field "spec.jobTemplate.apiVersion",
     *                            unknown field "spec.jobTemplate.kind"
     *
     * Without the flag - which is how it stood until now - the two fields were quietly
     * dropped on the way in, and every Deployment, Job and CronJob kso has ever applied
     * carried them.
     *
     * @return array<string, mixed>
     */
    public static function AsTemplate(K8sResource $resource): array {
        $template = $resource->toArray();

        unset($template['apiVersion'], $template['kind']);

        return $template;
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
