import { createApp } from 'vue';
import Unknown from './pages/Unknown.vue';

const exports = {
    async render(target: string, page: string, args: Record<string, unknown> = {}) {
        const root_component = await (async () => {
            try {
                return (await import(`./pages/${page}.vue`) as { default: Parameters<typeof createApp>[0] }).default;
            }
            catch (e) {
                console.error(`Could not render vue page ${page}:`, e);
                return Unknown;
            }
        })();

        const app = createApp(root_component, { page });

        for (const [key, value] of Object.entries(args)) {
            app.provide(key, value);
        }

        app.mount(target);
    },
};

declare global {
    interface Window { submitty: typeof exports }
}

window.submitty = exports;
