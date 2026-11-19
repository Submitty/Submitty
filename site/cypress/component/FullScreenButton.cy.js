import { h, ref, onMounted, defineComponent } from 'vue';
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

        it('renders the compress icon when mounted with initialFullScreen: true', () => {
            cy.mount(FullScreenButton, { props: { initialFullScreen: true } });
            cy.get('[data-testid="fullscreen-btn"] i')
                .should('have.class', 'fa-compress')
                .and('not.have.class', 'fa-expand');
        });

        it('renders the compress icon when mounted with controlled isFullScreen: true', () => {
            cy.mount(FullScreenButton, { props: { isFullScreen: true } });
            cy.get('[data-testid="fullscreen-btn"] i')
                .should('have.class', 'fa-compress')
                .and('not.have.class', 'fa-expand');
        });

        it('updates the icon when controlled isFullScreen prop changes', () => {
            cy.mount(FullScreenButton, { props: { isFullScreen: true } });
            cy.get('[data-testid="fullscreen-btn"] i').should('have.class', 'fa-compress');
            cy.then(() => {
                return Cypress.vueWrapper.setProps({ isFullScreen: false });
            });
            cy.get('[data-testid="fullscreen-btn"] i').should('have.class', 'fa-expand');
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
        it('emits false when Escape is pressed while focused on the button in full screen', () => {
            mountWithEmitSpy(FullScreenButton, 'toggle', { initialFullScreen: true });
            cy.get('[data-testid="fullscreen-btn"]').focus();
            cy.get('[data-testid="fullscreen-btn"]').type('{esc}');
            cy.get('@eventHandler').should('have.been.calledOnceWith', false);
        });

        it('does not emit when Escape is pressed while the button is not in full screen', () => {
            mountWithEmitSpy(FullScreenButton, 'toggle', {});
            cy.get('[data-testid="fullscreen-btn"]').focus();
            cy.get('[data-testid="fullscreen-btn"]').type('{esc}');
            cy.get('@eventHandler').should('not.have.been.called');
        });

        it('resets the icon when Escape exits full screen', () => {
            cy.mount(FullScreenButton, { props: { initialFullScreen: true } });
            cy.get('[data-testid="fullscreen-btn"]').focus();
            cy.get('[data-testid="fullscreen-btn"]').type('{esc}');
            cy.get('[data-testid="fullscreen-btn"] i').should('have.class', 'fa-expand');
        });

        it('emits only once when Escape is pressed twice', () => {
            mountWithEmitSpy(FullScreenButton, 'toggle', { initialFullScreen: true });
            cy.get('[data-testid="fullscreen-btn"]').focus();
            cy.get('[data-testid="fullscreen-btn"]').type('{esc}');
            cy.get('@eventHandler').should('have.been.calledOnceWith', false);
            cy.get('[data-testid="fullscreen-btn"]').type('{esc}');
            cy.get('@eventHandler').should('have.been.calledOnce');
        });
    });
});

describe('host-level fullscreen integration', () => {
    /**
     * Helper to mount the host-level layout representing:
     * - <main id="main" onkeydown="handleFullScreenKeydown(event)"> (from GlobalHeader.twig)
     * - <div class="fullscreen-btn-wrapper"> with .reRender() (from Vue.twig)
     * - <input data-testid="page-content-input"> (representing page content)
     */
    function mountHostPage({ initialFullScreen = true, preventEscape = false } = {}) {
        const HostPage = defineComponent({
            setup() {
                const isFullScreen = ref(initialFullScreen);
                const wrapperRef = ref(null);

                onMounted(() => {
                    // Simulate Vue.twig attaching .reRender to the mount element
                    if (wrapperRef.value) {
                        wrapperRef.value.reRender = (newArgs) => {
                            if (newArgs && typeof newArgs.isFullScreen === 'boolean') {
                                isFullScreen.value = newArgs.isFullScreen;
                            }
                        };
                    }
                });

                function onHostKeyDown(event) {
                    // Simulates onkeydown="handleFullScreenKeydown(event)" on main#main in GlobalHeader.twig
                    if (event.key !== 'Escape' || event.defaultPrevented) {
                        return;
                    }

                    const main = document.getElementById('main');
                    if (!main || !main.classList.contains('full-screen-mode')) {
                        return;
                    }

                    main.classList.remove('full-screen-mode');

                    const wrappers = main.querySelectorAll('.fullscreen-btn-wrapper');
                    wrappers.forEach((wrapper) => {
                        if (typeof wrapper.reRender === 'function') {
                            void wrapper.reRender({ isFullScreen: false });
                        }
                    });
                }

                function onContentKeyDown(event) {
                    if (preventEscape && event.key === 'Escape') {
                        event.preventDefault();
                    }
                }

                return () => h('main', {
                    id: 'main',
                    class: isFullScreen.value ? 'full-screen-mode' : '',
                    onKeydown: onHostKeyDown,
                }, [
                    h('div', {
                        ref: wrapperRef,
                        class: 'fullscreen-btn-wrapper',
                    }, [
                        h(FullScreenButton, {
                            isFullScreen: isFullScreen.value,
                            initialFullScreen: isFullScreen.value,
                        }),
                    ]),
                    h('input', {
                        'data-testid': 'page-content-input',
                        'placeholder': 'Page content input',
                        'onKeydown': onContentKeyDown,
                    }),
                ]);
            },
        });

        cy.mount(HostPage);
    }

    it('exits full screen and updates the button when Escape is pressed on page content', () => {
        mountHostPage({ initialFullScreen: true });
        cy.get('#main').should('have.class', 'full-screen-mode');
        cy.get('[data-testid="fullscreen-btn"] i').should('have.class', 'fa-compress');

        // Move focus away from fullscreen button to page content
        cy.get('[data-testid="page-content-input"]').focus();
        cy.get('[data-testid="fullscreen-btn"]').should('not.have.focus');
        cy.get('[data-testid="page-content-input"]').should('have.focus');

        // Press Escape while focused on page content
        cy.get('[data-testid="page-content-input"]').type('{esc}');

        // main loses full-screen-mode and button updates to expand icon
        cy.get('#main').should('not.have.class', 'full-screen-mode');
        cy.get('[data-testid="fullscreen-btn"] i').should('have.class', 'fa-expand');
    });

    it('does nothing when Escape is pressed on page content while not in full screen', () => {
        mountHostPage({ initialFullScreen: false });
        cy.get('#main').should('not.have.class', 'full-screen-mode');
        cy.get('[data-testid="fullscreen-btn"] i').should('have.class', 'fa-expand');

        cy.get('[data-testid="page-content-input"]').focus();
        cy.get('[data-testid="page-content-input"]').type('{esc}');

        cy.get('#main').should('not.have.class', 'full-screen-mode');
        cy.get('[data-testid="fullscreen-btn"] i').should('have.class', 'fa-expand');
    });

    it('does not exit full screen when Escape on page content is default-prevented', () => {
        mountHostPage({ initialFullScreen: true, preventEscape: true });
        cy.get('#main').should('have.class', 'full-screen-mode');
        cy.get('[data-testid="fullscreen-btn"] i').should('have.class', 'fa-compress');

        cy.get('[data-testid="page-content-input"]').focus();
        cy.get('[data-testid="page-content-input"]').type('{esc}');

        // Full screen remains active because the child element called preventDefault()
        cy.get('#main').should('have.class', 'full-screen-mode');
        cy.get('[data-testid="fullscreen-btn"] i').should('have.class', 'fa-compress');
    });
});
