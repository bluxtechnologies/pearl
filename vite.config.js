import { defineConfig, loadEnv } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig(({ mode, command }) => {
    const env = loadEnv(mode, process.cwd(), '');

    return {
        base: command === 'serve' ? '/' : (env.ASSET_URL ? `${env.ASSET_URL.replace(/\/$/, '')}/build/` : '/build/'),
        plugins: [
            tailwindcss(),
        ],

        root: 'ink',

        build: {
            outDir: path.resolve(__dirname, 'public/build'),
            emptyOutDir: true,
            manifest: true,
            rollupOptions: {
                input: path.resolve(__dirname, 'ink/main.js'),
            },
        },

        server: {
            host: env.VITE_HOST || 'localhost',
            port: Number(env.VITE_PORT) || 5173,
            strictPort: true,
        },
    };
});