/**
 * Re-render a Vue component mounted via Vue.twig with new props.
 *
 * Mirrors `{% include 'Vue.twig' with { args: { ... } } %}` — the Twig include
 * handles the initial mount and sets up `.reRender` on the element, and this
 * function handles every update afterward without repeating the component identity.
 *
 * @param target CSS selector or Element that was the mount target for render()
 * @param props  New props to pass to the Vue component
 */
export function updateVueComponent(target: string | Element, props: Record<string, unknown>): void {
    const el = typeof target === 'string' ? document.querySelector(target) : target;
    if (el?.reRender) {
        void el.reRender(props);
    }
}

/**
 * Fully unmount and destroy a Vue component mounted via Vue.twig.
 * Removes the app from the tracking map so a future updateVueComponent() starts fresh.
 *
 * @param target CSS selector or Element that was the mount target for render()
 */
export function unmountVueComponent(target: string | Element): void {
    const el = typeof target === 'string' ? document.querySelector(target) : target;
    el?.unmount?.();
}
