import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    build: {
        sourcemap: true,
    },
    plugins: [
        laravel([
            'resources/assets/sass/gasdotto.scss',
            'resources/assets/js/gasdotto.js',
        ]),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                silenceDeprecations: [
                    'import',
                    'mixed-decls',
                    'color-functions',
                    'global-builtin',
                ],
            },
        },
    },
});
