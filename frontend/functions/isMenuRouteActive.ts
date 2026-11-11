import type { RouteLocationNormalizedLoaded } from "vue-router";
import type { MenuRouteBasedUrl } from "~/functions/filterMenu.ts";

/**
 * Query keys that distinguish sibling sidebar links sharing one route name
 * (Playlists / Jingles / Commercials / Schedule → stations:playlists:index).
 */
const QUERY_SCOPE_KEYS = ["filter", "tab"] as const;

const normalize = (value: unknown): string => {
    if (value === undefined || value === null) {
        return "";
    }
    return String(Array.isArray(value) ? value[0] : value);
};

/**
 * Exact-enough active match for console sidebar links.
 * Matches route name + declared params/query, and keeps undeclared scope
 * query keys from lighting up sibling links en masse.
 */
export function isMenuRouteActive(
    url: MenuRouteBasedUrl,
    route: Pick<RouteLocationNormalizedLoaded, "name" | "params" | "query">,
): boolean {
    if (url.name !== route.name) {
        return false;
    }

    const itemParams = url.params ?? {};
    for (const [key, value] of Object.entries(itemParams)) {
        if (normalize(route.params[key]) !== normalize(value)) {
            return false;
        }
    }

    // Music Library (no path) must not stay active on special:duplicates etc.
    if (!("path" in itemParams)) {
        const routePath = normalize(route.params.path);
        if (routePath.startsWith("special:")) {
            return false;
        }
    }

    const itemQuery = url.query ?? {};
    for (const [key, value] of Object.entries(itemQuery)) {
        if (normalize(route.query[key]) !== normalize(value)) {
            return false;
        }
    }

    for (const key of QUERY_SCOPE_KEYS) {
        const itemHas = key in itemQuery;
        const routeVal = normalize(route.query[key]);
        if (!itemHas && routeVal !== "") {
            return false;
        }
    }

    return true;
}
