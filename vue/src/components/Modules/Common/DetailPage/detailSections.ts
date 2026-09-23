import type {Component} from "vue";

/**
 * One section of a detail page and one entry in its side menu, at
 * `<page url>/<key>`. What a thing has, it has once: the side menu, and any shortcut menu in a
 * list, read the same list.
 */
export interface DetailSection<T> {
    /** The last part of the url. Empty for the section the page opens on. */
    key: string;
    title: string;
    icon: string;
    /** The side menu's heading above it. Empty for none. */
    group: string;
    isShown: (item: T) => boolean;
    /** Shown but greyed out when this says no, with `disabledHint` beside it. */
    isEnabled?: (item: T) => boolean;
    disabledHint?: string;
    /** A count beside the title in the side menu - what needs looking at in there. Nothing for none. */
    badge?: (item: T) => DetailSectionBadge | null;
    component: Component;
}

export interface DetailSectionBadge {
    count: number;
    color: string;
}

export interface DetailSectionGroup<T> {
    group: string;
    sections: DetailSection<T>[];
}

/** The shown sections, grouped, in order. */
export function groupSections<T>(sections: DetailSection<T>[], item: T): DetailSectionGroup<T>[] {
    const groups: DetailSectionGroup<T>[] = [];
    for (const section of sections.filter(section => section.isShown(item))) {
        const last = groups[groups.length - 1];
        last?.group == section.group
            ? last.sections.push(section)
            : groups.push({group: section.group, sections: [section]});
    }
    return groups;
}

export function badgeOf<T>(section: DetailSection<T>, item: T): DetailSectionBadge | null {
    const badge = section.badge?.(item) ?? null;
    return badge && badge.count > 0 ? badge : null;
}

export function isSectionEnabled<T>(section: DetailSection<T>, item: T): boolean {
    return !section.isEnabled || section.isEnabled(item);
}
