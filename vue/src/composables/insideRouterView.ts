import { inject } from "vue";
import { matchedRouteKey } from "vue-router";

/**
 * Whether the calling component is rendered by a `<router-view>`. Only there can it guard
 * leaving the route: the same section inside a dialog - the create wizard - would have Vue
 * Router warn that there is no active route record, and it has its own way to be left.
 */
export function insideRouterView(): boolean {
    return inject(matchedRouteKey, null) !== null;
}
