import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (! id.includes('node_modules')) return;

                    if (id.includes('laravel-echo') || id.includes('pusher-js')) {
                        return 'realtime';
                    }

                    if (
                        id.includes('@inertiajs')
                        || id.includes('@vue')
                        || id.includes('vue')
                        || id.includes('vue-i18n')
                        || id.includes('ziggy-js')
                    ) {
                        return 'vue-vendor';
                    }

                    if (id.includes('cropperjs')) {
                        return 'image-tools';
                    }

                    return 'vendor';
                },
            },
        },
    },
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
