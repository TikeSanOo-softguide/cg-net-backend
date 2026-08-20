import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import inertia from '@inertiajs/vite';
import tailwindcss from '@tailwindcss/vite';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dirname = path.dirname(fileURLToPath(import.meta.url));
const vitePort = Number(process.env.VITE_PORT ?? 5173);
const viteOrigin = process.env.VITE_DEV_SERVER_URL ?? `http://localhost:${vitePort}`;

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        inertia({ ssr: false }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(dirname, 'resources/js'),
        },
    },
    server: {
        host: '0.0.0.0',
        port: vitePort,
        strictPort: true,
        cors: true,
        origin: viteOrigin,
        hmr: {
            host: 'localhost',
            port: vitePort,
        },
        watch: {
            usePolling: true,
            interval: 300,
            ignored: ['**/storage/framework/views/**', '**/vendor/**', '**/node_modules/**'],
        },
    },
});
