<?php namespace App\Helpers;

use DebugTool\Data;

/**
 * Whether the debug log goes out with a response: in development only.
 *
 * `Data::debug()` is written to from everywhere - queries, what a remote server answered,
 * exception messages - and every response used to carry all of it, to every caller, signed
 * in or not. The log is still kept outside development; the cron commands write it to their
 * job's `last_log`.
 */
class DebugLog {

    public static function IsSent(): bool {
        return ENVIRONMENT === 'development';
    }

    /**
     * The store as a response should carry it.
     *
     * @return array<string, mixed>
     */
    public static function ResponseBody(): array {
        $store = Data::getStore();
        if (!self::IsSent()) {
            unset($store['debug']);
        }

        return $store;
    }

    /**
     * An exception handler that drops the log and then hands over to `$inner`.
     *
     * RestExtension's handler builds the response to a refused request itself - a missing
     * or expired token - and puts the whole log in it. It is not ours to change, so it is
     * wrapped instead: by the time it runs, there is no log for it to send.
     */
    public static function Guard(?callable $inner): \Closure {
        return static function (\Throwable $exception) use ($inner): void {
            if (!self::IsSent()) {
                Data::del('debug');
            }
            if ($inner !== null) {
                $inner($exception);
            }
        };
    }

}
