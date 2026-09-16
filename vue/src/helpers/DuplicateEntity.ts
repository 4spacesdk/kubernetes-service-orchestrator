import { BaseModel } from "@/core/services/Deploy/models/BaseModel";

/**
 * Fields that identify a specific row rather than describe it. They must not be carried
 * into a copy: keeping `id` would turn the following save into an update of the original.
 *
 * The server ignores these on write anyway (see Entity::getPopulateIgnore in ci4), but
 * clearing them here keeps the dialog honest - it decides between create and update by
 * asking the entity whether it exists().
 */
const IDENTITY_FIELDS = [
    "id",
    "created",
    "updated",
    "created_by_id",
    "created_by",
    "updated_by_id",
    "updated_by",
    "deletion_id",
    "deletion",
];

/**
 * How to name the copy. Which one to use depends on what the name field actually is,
 * not on taste:
 *
 * - `Label` for a free text name a human reads, such as a container image called
 *   "Klartboard Backend". A suffix is helpful here.
 * - `Identifier` for a name with a format the cluster enforces, such as a gateway name,
 *   which has to be a DNS-1123 label. A suffix with spaces or parentheses would produce
 *   a value that cannot be used, so the suffix is one that stays valid.
 * - `Clear` for a name that cannot be derived at all, such as a domain name. There is no
 *   sensible copy of "minor.tst.klartboard.org", so the field is left empty for the user
 *   to fill in.
 */
export enum CopyNameStrategy {
    Label,
    Identifier,
    Clear,
}

function copyName(name: string | undefined, strategy: CopyNameStrategy): string | undefined {
    if (!name?.length) {
        return name;
    }
    switch (strategy) {
        case CopyNameStrategy.Label:
            return `${name} (copy)`;
        case CopyNameStrategy.Identifier:
            return `${name}-copy`;
        case CopyNameStrategy.Clear:
            return "";
    }
}

/**
 * Strip identity fields everywhere, not only on the entity itself.
 *
 * Related rows have to lose their ids too. The api creates a posted relation only when it
 * carries no id: given one, it looks the existing row up and attaches that row to the new
 * parent instead (see ResourceEntityTrait::post in ci4). For a relation held by a foreign
 * key that does not copy the children, it moves them - duplicating a gateway would take
 * the addresses off the gateway it was copied from.
 */
function stripIdentity(value: any): any {
    if (Array.isArray(value)) {
        return value.map(stripIdentity);
    }
    if (value === null || typeof value !== "object") {
        return value;
    }
    for (const field of IDENTITY_FIELDS) {
        delete value[field];
    }
    for (const key of Object.keys(value)) {
        value[key] = stripIdentity(value[key]);
    }
    return value;
}

/**
 * Build an unsaved copy of an entity, ready to hand to its edit dialog.
 *
 * The copy is a new instance of the same class, so the dialog and the api layer treat it
 * exactly like anything else they are given.
 *
 * Relations that should not be part of a copy at all, such as the workspaces sitting on a
 * domain, are the caller's to clear - this only makes sure that whatever is copied is
 * created rather than moved.
 */
export function duplicateEntity<T extends BaseModel & { name?: string }>(
    source: T,
    construct: new (json?: any) => T,
    strategy: CopyNameStrategy = CopyNameStrategy.Label
): T {
    const json = stripIdentity(JSON.parse(JSON.stringify(source)));

    const copy = new construct(json);
    copy.name = copyName(copy.name, strategy);
    return copy;
}
