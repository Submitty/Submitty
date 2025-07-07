import { createApp } from 'vue';
import Unknown from './components/Unknown.vue';

const exports = {
    async render(
        target: string | Element,
        type: 'component' | 'page',
        name: string,
        args: Record<string, unknown> = {},
    ) {
        const app = await (async () => {
            try {
                // https://vite.dev/guide/features.html#glob-import
                const modules = import.meta.glob(['./components/**/*.vue', './pages/*.vue'], { import: 'default' });
                const path = `./${type}s/${name}.vue`;
                if (!(path in modules)) {
                    throw new Error(`Module ${path} not found`);
                }
                const mod = await modules[path]();
                return createApp(mod as Parameters<typeof createApp>[0], args);
            }
            catch (e) {
                console.error(`Could not find vue ${type} ${name}:`, e);
                return createApp(Unknown, { type, name });
            }
        })();

        app.mount(target);
    },
};

declare global {
    interface Window { submitty: typeof exports }
}

window.submitty = exports;
