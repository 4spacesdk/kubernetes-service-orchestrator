import type {ThemeDefinition} from 'vuetify'

/**
 * Every colour the app uses, by what it is for. A component names one of these -
 * `color="toolbar"`, `rgb(var(--v-theme-secondary))` - and never a value, so a theme changes
 * the whole surface at once, dark included.
 *
 * * `background` the page, `surface` what sits on it: menus, cards, the side menus.
 * * `surface-muted` a bar on a surface: a dialog's actions.
 * * `appbar` the bar at the very top and a dialog's title, with `on-appbar` on it.
 * * `toolbar` the bar at the top of a page, with `on-toolbar` on it.
 * * `primary` text and icons of the ordinary actions; `secondary` the accent - what is chosen,
 *   links, the active menu item.
 * * `success`, `warning`, `error`, `info` what a status means - never a plain green or red.
 *
 * Lines between rows and around cards are Vuetify's own `--v-border-color` and
 * `--v-border-opacity`, which each theme sets.
 *
 * The palette is Grafit: a neutral dark grey, with blue as the accent. It comes light and
 * dark; text colours are checked against WCAG AA (4.5:1) on the page and on `surface`.
 */
export const ksoLight: ThemeDefinition = {
    dark: false,
    colors: {
        background: '#ffffff',
        surface: '#f1f3f5',
        'surface-muted': '#e5e8ec',
        appbar: '#26313c',
        'on-appbar': '#ffffff',
        primary: '#26313c',
        secondary: '#2563eb',
        toolbar: '#3e4c59',
        'on-toolbar': '#ffffff',
        success: '#2e7d32',
        warning: '#b45309',
        error: '#c62828',
        info: '#2563eb',
    },
};

export const ksoDark: ThemeDefinition = {
    dark: true,
    colors: {
        background: '#0f1318',
        surface: '#171c23',
        'surface-muted': '#202731',
        appbar: '#0b0e12',
        'on-appbar': '#e8edf2',
        primary: '#c9d3de',
        secondary: '#7aa2ff',
        toolbar: '#202731',
        'on-toolbar': '#e8edf2',
        success: '#66bb6a',
        warning: '#fbbf24',
        error: '#f87171',
        info: '#7aa2ff',
    },
};

/** The Vuetify theme names. */
export const themes = {
    'kso-light': ksoLight,
    'kso-dark': ksoDark,
};
