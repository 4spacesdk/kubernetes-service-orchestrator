<?php namespace App\Libraries\Kubernetes;

/**
 * What kso asks the api server for when it reads a container's log.
 *
 * In one place because the pod view and the deployment view have to ask for the same thing: a
 * line that shows up in one and not the other is the kind of difference nobody reports and
 * everybody distrusts.
 */
class LogQuery {

    /** Lines per container when no window is given. */
    public const int TailLines = 100;

    /**
     * With a window, the cap is higher: the point of "the last hour" is to see all of it, and a
     * hundred lines of a busy container is a minute. It is still a cap - a log is not a database.
     */
    public const int TailLinesWithinAWindow = 2000;

    /** The windows the web app offers, in seconds. */
    public const array Windows = [900, 3600, 21600];

    /**
     * @param bool $previous The container that ran before this one - what a crash left behind.
     * @param int|null $sinceSeconds Only what was written in the last so many seconds.
     * @return array<string, mixed>
     */
    public static function For(bool $previous = false, ?int $sinceSeconds = null): array {
        $query = [
            'timestamps' => true,
            'tailLines' => $sinceSeconds === null ? self::TailLines : self::TailLinesWithinAWindow,
        ];

        if ($previous) {
            $query['previous'] = true;
        }
        if ($sinceSeconds !== null) {
            $query['sinceSeconds'] = $sinceSeconds;
        }

        return $query;
    }

    /**
     * A window from the web app, or null for the default. Anything else - a number of somebody's
     * own, a word - is null rather than an error: it is a view of a log, and the default view is
     * a fine answer to a query string that makes no sense.
     */
    public static function WindowFrom(mixed $seconds): ?int {
        $seconds = is_numeric($seconds) ? (int) $seconds : 0;
        return in_array($seconds, self::Windows, true) ? $seconds : null;
    }

}
