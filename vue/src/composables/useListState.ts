import { ref, watch, type Ref } from "vue";
import { useRoute, useRouter, type LocationQuery, type LocationQueryValue } from "vue-router";

export interface SortItem {
    key: string;
    order: "asc" | "desc";
}

/**
 * What a list shows - search, page, page size, sort and any filters of its own - kept in
 * the url, so a refresh keeps it and a link shares it (LIST-1). Sorting goes to the API
 * (LIST-5).
 *
 * Bind `page`, `itemsPerPage` and `sortBy` to `v-data-table-server` with v-model and read
 * them in `getItems()`. A list inside a dialog passes `syncWithUrl: false`: the url belongs
 * to the page behind it.
 */
export function useListState(options: {
    /**
     * Header key => the API field to order by. Only these are sent, so a sort in a pasted
     * url cannot reach a column the list did not offer.
     */
    sortable: Record<string, string>;
    defaultSort: SortItem;
    itemsPerPage?: number;
    /** Filters of the list's own, kept in the url under their names. Arrays only. */
    filters?: Record<string, Ref<string[]>>;
    syncWithUrl?: boolean;
}) {
    const syncWithUrl = options.syncWithUrl ?? true;
    const defaultItemsPerPage = options.itemsPerPage ?? 50;
    const filters = options.filters ?? {};

    const route = useRoute();
    const router = useRouter();

    const search = ref("");
    const page = ref(1);
    const itemsPerPage = ref(defaultItemsPerPage);
    const sortBy = ref<SortItem[]>([]);
    const filterDefaults = Object.fromEntries(Object.entries(filters).map(([name, value]) => [name, [...value.value]]));

    function first(value: LocationQueryValue | LocationQueryValue[] | undefined): string | undefined {
        return (Array.isArray(value) ? value[0] : value) ?? undefined;
    }

    /** Search and filters as last set from the url, so reading them back is not a new search. */
    let lastRead = "";
    const narrowing = () => JSON.stringify([search.value, ...Object.values(filters).map(value => value.value)]);

    function readQuery(query: LocationQuery) {
        search.value = first(query.q) ?? "";
        page.value = Math.max(1, parseInt(first(query.page) ?? "") || 1);
        itemsPerPage.value = parseInt(first(query.per) ?? "") || defaultItemsPerPage;

        const [key, order] = (first(query.sort) ?? "").split(":");
        sortBy.value = key && key in options.sortable ? [{ key, order: order == "desc" ? "desc" : "asc" }] : [];

        for (const [name, value] of Object.entries(filters)) {
            const fromUrl = query[name];
            if (fromUrl !== undefined) {
                value.value = (Array.isArray(fromUrl) ? fromUrl : [fromUrl]).filter((v): v is string => !!v);
            } else {
                value.value = [...filterDefaults[name]];
            }
        }
        lastRead = narrowing();
    }

    /**
     * Only what differs from the defaults, so an untouched list has a clean url. A filter
     * emptied on purpose is written as `name=`, which reads back as empty rather than as the
     * default.
     */
    function toQuery(): LocationQuery {
        const query: LocationQuery = {};
        if (search.value) {
            query.q = search.value;
        }
        if (page.value > 1) {
            query.page = String(page.value);
        }
        if (itemsPerPage.value != defaultItemsPerPage) {
            query.per = String(itemsPerPage.value);
        }
        const sort = sortBy.value[0];
        if (sort && (sort.key != options.defaultSort.key || sort.order != options.defaultSort.order)) {
            query.sort = `${sort.key}:${sort.order}`;
        }
        for (const [name, value] of Object.entries(filters)) {
            const current = [...value.value].sort();
            const defaults = [...filterDefaults[name]].sort();
            if (current.join(",") != defaults.join(",")) {
                query[name] = current.length ? current : "";
            }
        }
        return query;
    }

    if (syncWithUrl) {
        readQuery(route.query);

        watch(
            [search, page, itemsPerPage, sortBy, ...Object.values(filters)],
            () => {
                const query = toQuery();
                if (JSON.stringify(query) != JSON.stringify(route.query)) {
                    router.replace({ query });
                }
            },
            { deep: true }
        );

        // Back, forward, or a menu link to the same page without a query.
        watch(
            () => route.query,
            query => {
                if (JSON.stringify(query) != JSON.stringify(toQuery())) {
                    readQuery(query);
                }
            }
        );
    }

    // A new search or filter starts from the first page; staying on page 4 of the old
    // result could show nothing.
    watch(
        [search, ...Object.values(filters)],
        () => {
            if (narrowing() != lastRead) {
                lastRead = narrowing();
                page.value = 1;
            }
        },
        { deep: true }
    );

    return {
        search,
        page,
        itemsPerPage,
        sortBy,

        /**
         * Order the API call by what the table is sorted by, or the list's default.
         */
        applyOrdering(api: { orderBy(name: string, direction: string): unknown }) {
            const sort = sortBy.value[0];
            const field = sort ? options.sortable[sort.key] : undefined;
            if (sort && field) {
                api.orderBy(field, sort.order);
            } else {
                api.orderBy(options.sortable[options.defaultSort.key] ?? options.defaultSort.key, options.defaultSort.order);
            }
        },

        applyPaging(api: { limit(n: number): unknown; offset(n: number): unknown }) {
            if (itemsPerPage.value > 0) {
                api.limit(itemsPerPage.value);
                api.offset(itemsPerPage.value * (page.value - 1));
            }
        },
    };
}
