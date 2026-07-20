import { createApp, h, defineComponent } from 'vue';
import Unknown from './components/Unknown.vue';

const mountedApps = new Map<string, ReturnType<typeof createApp>>();

const exports = {
    async render(
        target: string | Element,
        type: 'component' | 'page',
        name: string,
        args: Record<string, unknown> = {},
        events: Record<string, string> = {},
    ) {
        const mountKey = typeof target === 'string' ? target.replace(/^#/, '') : target.id ?? 'unknown';

        // Unmount any existing app mounted on the same target to prevent leaks
        if (mountedApps.has(mountKey)) {
            mountedApps.get(mountKey)!.unmount();
            mountedApps.delete(mountKey);
        }

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
                        (acc, [eventName, jsExpression]) => {
                            acc[`on${capitalize(camelize(eventName))}`] = (detail: unknown) => {
                                // eslint-disable-next-line @typescript-eslint/no-implied-eval
                                const fn = new Function(`return ${jsExpression}`) as () => (detail: unknown) => void;
                                fn()(detail);
                            };
                            return acc;
                        },
                        {} as Record<string, (detail: unknown) => void>,
                    );
                    // eslint-disable-next-line vue/one-component-per-file
                    const wrapper = defineComponent({
                        setup() {
                            return () => h(mod as Parameters<typeof createApp>[0], { ...args, ...eventListeners });
                        },
                    });
                    return createApp(wrapper);
                }

                return createApp(mod as Parameters<typeof createApp>[0], args);
            }
            catch {
                // eslint-disable-next-line vue/one-component-per-file
                return createApp(Unknown, { type, name });
            }
        })();

        mountedApps.set(mountKey, app);
        app.mount(target);
    },
};

declare global {
    interface Window { submitty: typeof exports }
}

window.submitty = exports;
