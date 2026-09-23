import {fileURLToPath, URL} from 'node:url'

import {defineConfig, loadEnv} from 'vite'
import vue from '@vitejs/plugin-vue'
import vueJsx from '@vitejs/plugin-vue-jsx'
import {VitePWA} from "vite-plugin-pwa";

// https://vitejs.dev/config/
export default defineConfig(({ command, mode }) => {
    // Load env file based on `mode` in the current working directory.
    // Set the third parameter to '' to load all env regardless of the `VITE_` prefix.
    const env = loadEnv(mode, process.cwd(), '')

    return {
        plugins: [
            vue(),
            vueJsx(),
            VitePWA({
                injectRegister: 'auto',
                registerType: "prompt",
            }),
        ],
        optimizeDeps: {
            include: [
                'highlight.js',
                // Vuetify 4's upgrade guide: bundled apart, its entries each get their own overlay
                // stack in development, and a menu inside a dialog opens behind it.
                'vuetify',
                'vuetify/components',
                'vuetify/directives',
                'vuetify/iconsets/fa',
            ]
        },
        resolve: {
            alias: {
                '@': fileURLToPath(new URL('./src', import.meta.url))
            },
        },
        server: {
            port: parseInt(env.SERVER_PORT)
        },
        base: '/app/'
    };
});
