import Popup from '../../vue/src/components/Popup.vue';

describe('Popup', () => {
    it('does not render when not visible', () => {
        cy.mount(Popup, {
            props: {
                title: 'Popup title',
                visible: false,
            },
        });

        cy.get('[data-testid="popup-window"]').should('not.exist');
    });

    it('renders title and default slot when visible', () => {
        cy.mount(Popup, {
            props: {
                title: 'Popup title',
                visible: true,
            },
            slots: {
                default: 'Body content',
            },
        });

        cy.get('[data-testid="popup-window"]').should('be.visible');
        cy.contains('h1', 'Popup title').should('be.visible');
        cy.contains('Body content').should('be.visible');
    });

    it('emits toggle on overlay and close buttons', () => {
        const onToggle = cy.stub().as('onToggle');

        cy.mount(Popup, {
            props: {
                title: 'Popup title',
                visible: true,
                onToggle,
            },
        });

        cy.get('.popup-box').then(($el) => {
            $el[0].dispatchEvent(new MouseEvent('click', { bubbles: true }));
        });
        cy.get('@onToggle').should('have.callCount', 1);

        cy.get('[data-testid="close-button"]').click();
        cy.get('@onToggle').should('have.callCount', 2);

        cy.get('[data-testid="popup-close-button"]').click();
        cy.get('@onToggle').should('have.callCount', 3);
    });

    it('emits toggle on Escape key', () => {
        const onToggle = cy.stub().as('onToggle');

        cy.mount(Popup, {
            props: {
                title: 'Popup title',
                visible: true,
                onToggle,
            },
        });

        cy.document().trigger('keydown', { key: 'Escape' });

        cy.get('@onToggle').should('have.callCount', 1);
    });

    it('renders discard/save when savable', () => {
        cy.mount(Popup, {
            props: {
                title: 'Popup title',
                visible: true,
                savable: true,
            },
        });

        cy.get('[data-testid="popup-close-button"]').should('contain', 'Discard');
        cy.get('[data-testid="popup-save-button"]').should('contain', 'Save');
    });

    it('emits save when save button is clicked', () => {
        const onSave = cy.stub().as('onSave');

        cy.mount(Popup, {
            props: {
                title: 'Popup title',
                visible: true,
                savable: true,
                onSave,
            },
        });

        cy.get('[data-testid="popup-save-button"]').click();

        cy.get('@onSave').should('have.callCount', 1);
    });
});
