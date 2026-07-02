import { h, defineComponent } from 'vue';

/**
 * Mounts a component with a stub for a named emit event, exposed as an alias
 * so it can be asserted on with `cy.get('@eventHandler')`.
 *
 * @param {object} component - The Vue component to mount.
 * @param {string} eventName - The emit event name to stub (e.g. 'colorChange').
 * @param {object} props - Props to pass to the component.
 * @param {string} [alias='eventHandler'] - Cypress alias for the stub.
 */
export function mountWithEmitSpy(component, eventName, props, alias = 'eventHandler') {
    const handler = cy.stub().as(alias);
    const Wrapper = defineComponent({
        setup() {
            return () => h(component, {
                ...props,
                [`on${eventName.charAt(0).toUpperCase()}${eventName.slice(1)}`]: handler,
            });
        },
    });
    cy.mount(Wrapper);
}
