import { createApp, h, defineComponent } from 'vue';
import Unknown from './components/Unknown.vue';

const handlerRegistry: Record<string, (detail: unknown) => void> = {};

const exports = {
    registerHandler(name: string, fn: (detail: unknown) => void) {
        handlerRegistry[name] = fn;
    },

    async render(
        target: string | Element,
        type: 'component' | 'page',
        name: string,
        args: Record<string, unknown> = {},
        events: Record<string, string> = {},
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

                if (Object.keys(events).length > 0) {
                    const camelize = (str: string) => str.replace(/-(\w)/g, (_, c: string) => (c ? c.toUpperCase() : ''));
                    const capitalize = (str: string) => str.charAt(0).toUpperCase() + str.slice(1);
                    const eventListeners = Object.entries(events).reduce(
                        (acc, [eventName, fnName]) => {
                            // Vue 3's emit() looks up handlers via toHandlerKey(event) and toHandlerKey(camelize(event)),
                            // so we must produce keys like onColorChange, not on-color-change.
                            acc[`on${capitalize(camelize(eventName))}`] = (detail: unknown) => {
                                const fn = handlerRegistry[fnName];
                                if (typeof fn === 'function') {
                                    fn(detail);
                                }
                            };
                            return acc;
                        },
                        {} as Record<string, (detail: unknown) => void>,
                    );
                    const wrapper = defineComponent({
                        setup() {
                            return () => h(mod as Parameters<typeof createApp>[0], { ...args, ...eventListeners });
                        },
                    });
                    return createApp(wrapper);
                }

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
