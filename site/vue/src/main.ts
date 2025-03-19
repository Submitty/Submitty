import { createApp } from 'vue';
import Unknown from './components/Unknown.vue';

const exports = {
    async render(target: string | Element, component: string, args: Record<string, unknown> = {}) {
        const root_component = await (async () => {
            try {
                return (await import(`./components/${component}.vue`) as { default: Parameters<typeof createApp>[0] }).default;
            }
            catch (e) {
                console.error(`Could not find vue component ${component}:`, e);
                return Unknown;
            }
        })();

        const app = createApp(root_component);
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
