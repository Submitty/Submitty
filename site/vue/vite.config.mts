import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';
import path, { resolve } from 'path';
import { defineConfig } from 'vite';

// https://vitejs.dev/config/
export default defineConfig({
    plugins: [vue()],
    define: {
        'process.env.NODE_ENV': '"production"',
    },
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./src', import.meta.url)),
        },
    },
    build: {
        rollupOptions: {
            external: ['vue'],
            output: {
                globals: {
                    vue: 'Vue',
                },
            },
        },
        lib: {
            name: 'submitty-vue',
            entry: resolve(path.dirname(fileURLToPath(import.meta.url)), 'src/main.ts'),
            fileName: 'submitty-vue',

            formats: ['umd'],
        },
        outDir: '../public/mjs/vue/',
        emptyOutDir: true,
    },
});
