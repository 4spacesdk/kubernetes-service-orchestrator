import type {RouteLocationRaw, Router} from "vue-router";

/**
 * Back to where the page was opened from - a workspace, a list with its search - or, when it
 * was opened on its own from a link, to `fallback`.
 */
export function goBack(router: Router, fallback: RouteLocationRaw) {
    if (window.history.state?.back) {
        router.back();
    } else {
        router.push(fallback);
    }
}
