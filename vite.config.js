import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            // Шрифт Instrument Sans вшит локально (resources/fonts/ + resources/css/fonts.css),
            // НЕ через сетевой fonts-плагин: сервер в РФ не достучивается до bunny.net и
            // сборка падает (read ETIMEDOUT). Кириллицы у шрифта нет — она и так фолбэк.
        }),
        vue(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
