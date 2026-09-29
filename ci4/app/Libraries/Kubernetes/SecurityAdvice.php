<?php namespace App\Libraries\Kubernetes;

use App\Entities\ContainerImage;

/**
 * What a container image's security settings could be, from what its registry and its scans say
 * it runs as - shown on the image, never switched on. See `SecurityContext`.
 *
 * Few and specific, on purpose: a catalogue of best practices is what made an advisor page noise,
 * and every line here is about this image and says what to do. What it runs as is taken from
 * whichever is newer, the read when the image was made or the last scan of a tag - so a version
 * that changed its user is what moves the advice, in either direction.
 */
class SecurityAdvice {

    public const string Warning = 'warning';
    public const string Suggestion = 'suggestion';
    public const string Info = 'info';

    /**
     * @param array{tag: string, image_user: string, scanned_at: string}|null $lastScan The newest
     *   scan of a tag that says what it runs as
     * @return list<array{key: string, level: string, text: string}> `key` says which setting the
     *   line is about - `run_as_non_root`, `seccomp`, `unreadable`
     */
    public static function For(ContainerImage $image, ?array $lastScan = null): array {
        $advice = [];

        [$user, $tag] = self::RunsAs($image, $lastScan);
        $fixedUser = SecurityContext::IsNonRootUser((string) $image->security_context_run_as_user);
        $nonRoot = (bool) $image->security_context_run_as_non_root;

        if ($user === null && (string) $image->image_user_error !== '') {
            $advice[] = self::Line('unreadable', self::Info, "What it runs as could not be read: {$image->image_user_error}");
        }

        if ($fixedUser) {
            // A uid on the image is what kubelet runs it as, whatever the registry says.
            if (!$nonRoot) {
                $advice[] = self::Line('run_as_non_root', self::Suggestion, "Runs as {$image->security_context_run_as_user}, set here - Run as non-root can be turned on");
            }
        } elseif ($user !== null && SecurityContext::IsNonRootUser($user)) {
            if (!$nonRoot) {
                $advice[] = self::Line('run_as_non_root', self::Suggestion, "{$tag} runs as {$user} - Run as non-root can be turned on");
            }
        } elseif ($user !== null) {
            $as = $user === '' ? 'root' : "{$user}, a name Run as non-root cannot check";
            $advice[] = $nonRoot
                ? self::Line('run_as_non_root', self::Warning, "{$tag} runs as {$as} - its containers will not start under Run as non-root. Give it a numeric USER, or a user here")
                : self::Line('run_as_non_root', self::Suggestion, "{$tag} runs as {$as} - with a numeric USER that is not root, it could run as non-root");
        }

        if (!$image->security_context_seccomp_runtime_default) {
            $advice[] = self::Line('seccomp', self::Suggestion, 'Seccomp profile RuntimeDefault is off - it seldom stops an image, and filters the system calls a container may make');
        }

        return $advice;
    }

    /**
     * @return array{0: ?string, 1: ?string} The user and the tag it was read from, or nulls
     */
    private static function RunsAs(ContainerImage $image, ?array $lastScan): array {
        $readAt = (string) $image->image_user_read_at;
        $read = $image->image_user_tag !== null && (string) $image->image_user_tag !== '' && (string) $image->image_user_error === ''
            ? [(string) $image->image_user, (string) $image->image_user_tag, $readAt]
            : null;
        $scanned = $lastScan !== null ? [(string) $lastScan['image_user'], (string) $lastScan['tag'], (string) $lastScan['scanned_at']] : null;

        $newest = $read;
        if ($scanned !== null && ($newest === null || strcmp($scanned[2], $newest[2]) > 0)) {
            $newest = $scanned;
        }

        return $newest === null ? [null, null] : [$newest[0], $newest[1]];
    }

    /**
     * @return array{key: string, level: string, text: string}
     */
    private static function Line(string $key, string $level, string $text): array {
        return ['key' => $key, 'level' => $level, 'text' => $text];
    }

}
