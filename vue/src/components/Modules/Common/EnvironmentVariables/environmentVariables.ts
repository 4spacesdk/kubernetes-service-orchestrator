/**
 * Environment variables as the dialogs edit them - on a specification, a workspace template, a
 * deployment and an init container - and what makes one secret.
 *
 * A secret one is write-only: the server never sends its value, only `has_value`. Saving the
 * list with its value empty keeps what is stored, so a row that was never shown its value can
 * be sent back as it is.
 */
export interface EnvironmentVariableRow {
    name: string;
    value: string;
    is_secret?: boolean;
    has_value?: boolean;
}

/**
 * What kso fills in with a password. A value that takes one is secret on the server whether
 * it is marked or not - the same list as `ContainerEnvironment::SecretPlaceholders`.
 */
const PasswordPlaceholders = ['${database.pass}', '${emailService.pass}'];

/**
 * A name that suggests a secret. Only ever a suggestion: nothing is marked without the user.
 */
const SecretLookingName = /PASS|SECRET|TOKEN|KEY/i;

export function toRow(variable: { name?: string; value?: string; is_secret?: boolean; has_value?: boolean }): EnvironmentVariableRow {
    return {
        name: variable.name ?? '',
        value: variable.value ?? '',
        is_secret: !!variable.is_secret,
        has_value: !!variable.has_value,
    };
}

/**
 * A secret kso makes and keeps - `${secret.<name>}` for the deployment, `${workspace.secret.<name>}`
 * for its workspace. Secret either way, as a password is - see `GeneratedSecrets`.
 */
const GeneratedSecretPrefixes = ['${secret.', '${workspace.secret.'];

export function takesAPassword(value: string | undefined): boolean {
    return [...PasswordPlaceholders, ...GeneratedSecretPrefixes].some(placeholder => (value ?? '').includes(placeholder));
}

export function looksSecret(name: string | undefined): boolean {
    return SecretLookingName.test(name ?? '');
}

/**
 * `NAME:value` a line. The secret ones are left out: there is no value to show, and a line
 * without one would read as an empty value.
 */
export function toBulk(rows: EnvironmentVariableRow[]): string {
    return rows
        .filter(row => !row.is_secret)
        .map(({name, value}) => `${name}:${value}`)
        .join('\n');
}

/**
 * The rows the bulk text describes, with the secret ones it left out kept as they were. A
 * line that names a secret one gives it a new value and leaves it secret.
 */
export function fromBulk(text: string, previous: EnvironmentVariableRow[]): EnvironmentVariableRow[] {
    const secrets = new Map(previous.filter(row => row.is_secret).map(row => [row.name, row]));

    const rows = text.split('\n').map(line => {
        const firstColonIndex = line.indexOf(':');
        const name = firstColonIndex === -1 ? line : line.substring(0, firstColonIndex);
        const value = firstColonIndex === -1 ? '' : line.substring(firstColonIndex + 1);
        const secret = secrets.get(name);
        secrets.delete(name);

        return secret ? {...secret, value} : {name, value, is_secret: false, has_value: false};
    });

    return [...rows, ...secrets.values()];
}

/**
 * A secret kso makes, as the dialog sets it up: a name, whose it is, and what it is made of. It is
 * stored as the placeholder the server reads - `${secret.name | randAlphaNum 32}` - so nobody has to
 * write one, and one written by hand still works. See `GeneratedSecrets` and `SecretRecipe`.
 */
export interface GeneratedSecretSettings {
    owner: 'deployment' | 'workspace';
    name: string;
    kind: string;
    length: number;
}

export const SecretKinds: { value: string; title: string; hasLength: boolean }[] = [
    {value: 'randHex', title: 'Hex (0-9, a-f)', hasLength: true},
    {value: 'randAlphaNum', title: 'Letters and digits', hasLength: true},
    {value: 'randAlpha', title: 'Letters', hasLength: true},
    {value: 'randNumeric', title: 'Digits', hasLength: true},
    {value: 'randAscii', title: 'Letters, digits and punctuation', hasLength: true},
    {value: 'randBytes', title: 'Random bytes, base64-encoded', hasLength: true},
    {value: 'uuidv4', title: 'UUID', hasLength: false},
];

/** What kso makes without a recipe: 32 random bytes as hex. */
export const DefaultSecret = {kind: 'randHex', length: 64};

const WholePlaceholder = /^\$\{(workspace\.)?secret\.([a-z0-9_]+)\s*(?:\|\s*(\w+)(?:\s+(\d+))?\s*)?\}$/;

/**
 * The settings of a value that is one generated secret and nothing else, or null - a value with
 * other text around it is edited as text.
 */
export function parseGeneratedSecret(value: string | undefined): GeneratedSecretSettings | null {
    const match = (value ?? '').trim().match(WholePlaceholder);
    if (!match || (match[3] && !SecretKinds.some(kind => kind.value === match[3]))) {
        return null;
    }
    return {
        owner: match[1] ? 'workspace' : 'deployment',
        name: match[2],
        kind: match[3] ?? DefaultSecret.kind,
        length: match[4] ? parseInt(match[4]) : DefaultSecret.length,
    };
}

export function writeGeneratedSecret(settings: GeneratedSecretSettings): string {
    const prefix = settings.owner === 'workspace' ? '${workspace.secret.' : '${secret.';
    const kind = SecretKinds.find(kind => kind.value === settings.kind) ?? SecretKinds[0];
    const isDefault = kind.value === DefaultSecret.kind && settings.length === DefaultSecret.length;
    const recipe = isDefault ? '' : (kind.hasLength ? ` | ${kind.value} ${settings.length}` : ` | ${kind.value}`);
    return `${prefix}${settings.name}${recipe}}`;
}

/** A secret's name from a variable's: `WEBHOOK_HASH` is `webhook_hash`. */
export function secretNameFor(variableName: string | undefined): string {
    return (variableName ?? '').toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '');
}

/** "Letters and digits, 32" - for a list, in place of the placeholder. */
export function describeGeneratedSecret(settings: GeneratedSecretSettings): string {
    const kind = SecretKinds.find(kind => kind.value === settings.kind);
    return kind?.hasLength ? `${kind.title}, ${settings.length}` : (kind?.title ?? settings.kind);
}
