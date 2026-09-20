/**
 * What to say under a field whose value the API will not hand back.
 *
 * Credentials are write-only: they are never returned, and a `has_<field>` flag comes back
 * in their place. A form therefore cannot show the value, and without a word under the field
 * there is no way to tell a service with a password from one without - so the only safe
 * thing to do would be to type it again on every save.
 *
 * Leaving the field empty keeps what is stored; the server drops an empty secret from a
 * write.
 */
export function secretHint(item: any, field: string): string {
    return item?.[`has_${field}`] ? "Stored. Leave empty to keep it." : "Not set.";
}
