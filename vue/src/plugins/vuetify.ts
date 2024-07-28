import { createVuetify } from 'vuetify'
import { aliases, fa } from 'vuetify/iconsets/fa'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'

const customLightTheme = {
    dark: false,
    colors: {
        background: '#e9f0f2',
        surface: '#e9f0f2',
        primary: '#1a3b46',
        'primary-darken-1': '#0a1a1f',
        secondary: '#2e92a3',
        'secondary-darken-1': '#03504b',
        error: '#B00020',
        info: '#2196F3',
        success: '#4CAF50',
        warning: '#FB8C00',
    },
}

export default createVuetify({
    components: {
        ...components,
    },
    directives,
    theme: {
        defaultTheme: 'customLightTheme',
        themes: {
            customLightTheme,
        }
    },
    icons: {
        defaultSet: 'fa',
        aliases: {
            ... aliases,
            next: 'fa fa-chevron-right',
            prev: 'fa fa-chevron-left',
            last: 'fa fa-chevrons-right',
            first: 'fa fa-chevrons-left',
            dropdown: 'fa fa-circle-chevron-down',
        },
        sets: {
            fa,
        }
    },
});
