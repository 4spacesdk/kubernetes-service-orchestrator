import {ref, watch} from "vue";
import {useTheme} from "vuetify";

export type ThemeMode = 'system' | 'light' | 'dark';

const ModeKey = 'kso.theme.mode';

/** Storage can be refused - a private window, blocked site data - and the choice is only a convenience. */
function read(key: string): string | null {
    try {
        return localStorage.getItem(key);
    } catch {
        return null;
    }
}

function write(key: string, value: string) {
    try {
        localStorage.setItem(key, value);
    } catch {
        // Kept for this visit only.
    }
}

const darkQuery = typeof window !== 'undefined' ? window.matchMedia('(prefers-color-scheme: dark)') : null;

function storedMode(): ThemeMode {
    const value = read(ModeKey);
    return value == 'light' || value == 'dark' ? value : 'system';
}

function themeName(mode: ThemeMode): string {
    const isDark = mode == 'dark' || (mode == 'system' && !!darkQuery?.matches);
    return isDark ? 'kso-dark' : 'kso-light';
}

/**
 * The theme to start in, before anything is drawn, so a dark page does not flash light first:
 * the mode chosen last time, or the system's.
 */
export function initialThemeName(): string {
    return themeName(storedMode());
}

const mode = ref<ThemeMode>(storedMode());

/**
 * Light, dark, or as the system is. Kept in this browser; the system's setting is followed as
 * it changes.
 */
export function useAppTheme() {
    const theme = useTheme();

    function apply() {
        theme.global.name.value = themeName(mode.value);
    }

    watch(mode, () => {
        write(ModeKey, mode.value);
        apply();
    });

    darkQuery?.addEventListener('change', apply);

    return {mode};
}
