import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // The Instrument Sans / Bunny Fonts entry was removed: body copy now
            // uses the system sans stack (zero bytes, matches the app), and the
            // one webfont we do want — Lora — is subsetted into public/fonts and
            // declared in app.css. Nothing on the site fetches a third-party host.
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
