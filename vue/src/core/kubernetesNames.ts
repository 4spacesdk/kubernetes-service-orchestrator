/**
 * What Kubernetes accepts as a name for a namespace, and most other things: a DNS label.
 * Lowercase letters, digits and hyphens, at most 63, starting and ending with a letter or
 * digit (RFC 1123).
 */
export const MaxDnsLabelLength = 63;

export function isDnsLabel(value: string | null | undefined): boolean {
    return !!value && value.length <= MaxDnsLabelLength && /^[a-z0-9]([-a-z0-9]*[a-z0-9])?$/.test(value);
}

/**
 * Why a value is not a DNS label, for a form rule; true when it is one.
 */
export function dnsLabelRule(value: string | null | undefined): true | string {
    if (!value) {
        return "Required";
    }
    if (value.length > MaxDnsLabelLength) {
        return `At most ${MaxDnsLabelLength} characters (${value.length} now)`;
    }
    if (/[A-Z]/.test(value)) {
        return "Lowercase only";
    }
    if (!/^[-a-z0-9]*$/.test(value)) {
        return "Only a-z, 0-9 and -";
    }
    if (!/^[a-z0-9]/.test(value) || !/[a-z0-9]$/.test(value)) {
        return "Must start and end with a letter or digit";
    }
    return true;
}

/** Letters that do not decompose into a base letter and an accent. */
const Spelled: Record<string, string> = { æ: "ae", ø: "oe", å: "aa", ß: "ss" };

/**
 * A readable name turned into a DNS label: "Øster Ås, Nord" -> "oester-aas-nord". Letters
 * with accents keep their base letter; anything else becomes a hyphen.
 */
export function toDnsLabel(text: string | null | undefined, maxLength = MaxDnsLabelLength): string {
    return (text ?? "")
        .toLocaleLowerCase()
        .replace(/[æøåß]/g, letter => Spelled[letter])
        .normalize("NFD")
        .replace(/[̀-ͯ]/g, "")
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "")
        .substring(0, maxLength)
        .replace(/-+$/, "");
}
