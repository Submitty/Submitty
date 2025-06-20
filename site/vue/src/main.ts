import { createApp } from 'vue';
import Unknown from './components/Unknown.vue';

const exports = {
    async render(target: string | Element, component: string, args: Record<string, unknown> = {}) {
        const app = await (async () => {
            try {
                // eslint-disable-next-line no-unsanitized/method
                return createApp((await import(`./components/${component}.vue`) as { default: Parameters<typeof createApp>[0] }).default, args);
            }
            catch (e) {
                console.error(`Could not find vue component ${component}:`, e);
                return createApp(Unknown, { component });
            }
        })();

        app.mount(target);
    },
};

declare global {
    interface Window { submitty: typeof exports }
}

window.submitty = exports;
