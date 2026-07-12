import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import Components from 'unplugin-vue-components/vite';
import { PrimeVueResolver } from '@primevue/auto-import-resolver';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            ssr: 'resources/js/ssr.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
            script: {
                defineModel: true,
            },
        }),
        Components({
            resolvers: [
                PrimeVueResolver()
            ]
        })
    ],
    server: {
        port: 3000,
        host: '127.0.0.1',
        hmr: {
            host: '127.0.0.1',
        },
    },
    resolve: {
        alias: {
            '@/*': fileURLToPath(new URL('./resources/js', import.meta.url)),
        }
    },
});
