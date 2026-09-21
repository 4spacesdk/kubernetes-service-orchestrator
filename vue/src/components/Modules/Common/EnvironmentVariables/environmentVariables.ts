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

export function takesAPassword(value: string | undefined): boolean {
    return PasswordPlaceholders.some(placeholder => (value ?? '').includes(placeholder));
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
