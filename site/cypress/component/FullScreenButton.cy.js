import FullScreenButton from '../../vue/src/components/FullScreenButton.vue';
import { mountWithEmitSpy } from '../support/component_test_utils.js';

describe('FullScreenButton', () => {
    describe('rendering', () => {
        it('renders the expand icon by default', () => {
            cy.mount(FullScreenButton);
            cy.get('[data-testid="fullscreen-btn"] i')
                .should('have.class', 'fa-expand')
                .and('not.have.class', 'fa-compress');
        });

        it('renders the compress icon when mounted in full screen', () => {
            cy.mount(FullScreenButton, { props: { initialFullScreen: true } });
            cy.get('[data-testid="fullscreen-btn"] i')
                .should('have.class', 'fa-compress')
                .and('not.have.class', 'fa-expand');
        });

        it('swaps the icon on each click', () => {
            cy.mount(FullScreenButton);
            cy.get('[data-testid="fullscreen-btn"]').click();
            cy.get('[data-testid="fullscreen-btn"] i').should('have.class', 'fa-compress');
            cy.get('[data-testid="fullscreen-btn"]').click();
            cy.get('[data-testid="fullscreen-btn"] i').should('have.class', 'fa-expand');
        });

        it('has a title attribute and hides the icon from screen readers', () => {
            cy.mount(FullScreenButton);
            cy.get('[data-testid="fullscreen-btn"]')
                .should('have.attr', 'title')
                .and('not.be.empty');
            cy.get('[data-testid="fullscreen-btn"] i').should('have.attr', 'aria-hidden', 'true');
        });
    });

    describe('toggle event', () => {
        it('emits true on the first click', () => {
            mountWithEmitSpy(FullScreenButton, 'toggle', {});
            cy.get('[data-testid="fullscreen-btn"]').click();
            cy.get('@eventHandler').should('have.been.calledOnceWith', true);
        });

        it('emits false on the second click', () => {
            mountWithEmitSpy(FullScreenButton, 'toggle', {});
            cy.get('[data-testid="fullscreen-btn"]').click();
            cy.get('[data-testid="fullscreen-btn"]').click();
            cy.get('@eventHandler').should('have.been.calledTwice');
            cy.get('@eventHandler').should('have.been.calledWith', false);
        });

        it('emits false first when mounted in full screen', () => {
            mountWithEmitSpy(FullScreenButton, 'toggle', { initialFullScreen: true });
            cy.get('[data-testid="fullscreen-btn"]').click();
            cy.get('@eventHandler').should('have.been.calledOnceWith', false);
        });

        it('does not emit on mount', () => {
            mountWithEmitSpy(FullScreenButton, 'toggle', {});
            cy.get('@eventHandler').should('not.have.been.called');
        });

        it('emits when activated by enter', () => {
            mountWithEmitSpy(FullScreenButton, 'toggle', {});
            cy.get('[data-testid="fullscreen-btn"]').focus();
            cy.get('[data-testid="fullscreen-btn"]').type('{enter}');
            cy.get('@eventHandler').should('have.been.calledOnceWith', true);
        });

        it('emits when activated by space', () => {
            mountWithEmitSpy(FullScreenButton, 'toggle', {});
            cy.get('[data-testid="fullscreen-btn"]').focus();
            cy.get('[data-testid="fullscreen-btn"]').press(Cypress.Keyboard.Keys.SPACE);
            cy.get('@eventHandler').should('have.been.calledOnceWith', true);
        });
    });

    describe('escape key', () => {
        it('emits false when Escape is pressed while full screen', () => {
            mountWithEmitSpy(FullScreenButton, 'toggle', { initialFullScreen: true });
            cy.get('[data-testid="fullscreen-btn"]').trigger('keydown', { key: 'Escape' });
            cy.get('@eventHandler').should('have.been.calledOnceWith', false);
        });

        it('emits false when Escape is dispatched on document while full screen', () => {
            mountWithEmitSpy(FullScreenButton, 'toggle', { initialFullScreen: true });
            cy.document().trigger('keydown', { key: 'Escape' });
            cy.get('@eventHandler').should('have.been.calledOnceWith', false);
        });

        it('emits false when Escape is pressed and button is not focused', () => {
            mountWithEmitSpy(FullScreenButton, 'toggle', { initialFullScreen: true });
            cy.get('[data-testid="fullscreen-btn"]').should('not.have.focus');
            cy.document().trigger('keydown', { key: 'Escape' });
            cy.get('@eventHandler').should('have.been.calledOnceWith', false);
        });

        it('does not emit when Escape is pressed while not full screen', () => {
            mountWithEmitSpy(FullScreenButton, 'toggle', {});
            cy.document().trigger('keydown', { key: 'Escape' });
            cy.get('@eventHandler').should('not.have.been.called');
        });

        it('does not emit when Escape event is default-prevented', () => {
            mountWithEmitSpy(FullScreenButton, 'toggle', { initialFullScreen: true });
            cy.document().then((doc) => {
                const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
                event.preventDefault();
                doc.dispatchEvent(event);
            });
            cy.get('@eventHandler').should('not.have.been.called');

            // Subsequent non-prevented Escape should still work
            cy.document().trigger('keydown', { key: 'Escape' });
            cy.get('@eventHandler').should('have.been.calledOnceWith', false);
        });

        it('resets the icon when Escape exits full screen', () => {
            cy.mount(FullScreenButton, { props: { initialFullScreen: true } });
            cy.document().trigger('keydown', { key: 'Escape' });
            cy.get('[data-testid="fullscreen-btn"] i').should('have.class', 'fa-expand');
        });

        it('emits only once when Escape is pressed twice', () => {
            mountWithEmitSpy(FullScreenButton, 'toggle', { initialFullScreen: true });
            cy.document().trigger('keydown', { key: 'Escape' });
            cy.document().trigger('keydown', { key: 'Escape' });
            cy.get('@eventHandler').should('have.been.calledOnce');
        });

        it('removes document keydown listener when exiting full screen via toggle', () => {
            mountWithEmitSpy(FullScreenButton, 'toggle', { initialFullScreen: true });
            cy.get('[data-testid="fullscreen-btn"]').click();
            cy.get('@eventHandler').should('have.been.calledOnceWith', false);
            cy.document().trigger('keydown', { key: 'Escape' });
            cy.get('@eventHandler').should('have.been.calledOnce');
        });

        it('removes document keydown listener when unmounted', () => {
            mountWithEmitSpy(FullScreenButton, 'toggle', { initialFullScreen: true });
            cy.then(() => {
                if (Cypress.vueWrapper) {
                    Cypress.vueWrapper.unmount();
                }
            });
            cy.document().trigger('keydown', { key: 'Escape' });
            cy.get('@eventHandler').should('not.have.been.called');
        });
    });
});
