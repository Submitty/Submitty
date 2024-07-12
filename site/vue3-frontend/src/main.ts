import { createApp } from 'vue';
import Unknown from './pages/Unknown.vue';

const apps: Record<string, Parameters<typeof createApp>[0]> = {
    // "name": component import

};

const exports = {
    render(target: string, page: string, args: Record<string, unknown> = {}) {
        const app = createApp(apps[page] ?? Unknown, { page });

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
