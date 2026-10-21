<?php namespace App\Libraries\Podio;

/**
 * The item an url points at.
 *
 * A Podio task link ends in `items/<id>`, and the url itself is pulled out of a commit
 * message - so it is regularly something else, or nothing at all when the message has no
 * link in it. Three places used to split it with `explode('items/', $url)` and read element
 * 1 straight away, which is `Undefined array key 1` for every one of those cases.
 */
class PodioItemUrl {

    /**
     * A Podio link, up to where the text around it starts: whitespace, a quote, a bracket or
     * a comma - so `(https://podio.com/...)` and a Markdown link end at their closing bracket.
     */
    private const string PodioLink = '#https://(?:www\.)?podio\.com/[^\s<>()\[\]"\'`,]+#i';

    /**
     * The first Podio link in a commit message, or null when it has none.
     *
     * Only podio.com: a message regularly links the pull request, an issue or a log as well,
     * and the first url of any kind used to be taken - so a GitHub link ahead of the task
     * meant no comment and no field update. The condition refused a message with more than
     * one url at all. A sentence's full stop or a colon after the link is not part of it.
     */
    public static function firstIn(?string $text): ?string {
        if (!preg_match(self::PodioLink, (string) $text, $match)) {
            return null;
        }

        return rtrim($match[0], '.:;!?');
    }

    public static function itemId(?string $url): ?string {
        $parts = explode('items/', (string) $url);
        if (count($parts) < 2) {
            return null;
        }

        $itemId = trim(end($parts), '/');

        return $itemId === '' ? null : $itemId;
    }

}
