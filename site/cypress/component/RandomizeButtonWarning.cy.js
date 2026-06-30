import RandomizeButtonWarning from '../../vue/src/components/RandomizeButtonWarning.vue';

describe('RandomizeButtonWarning', () => {
    const randomizeUrl = '/courses/s26/sample/gradeable/test/grading/teams/randomize_rotating';

    beforeEach(() => {
        cy.mount(RandomizeButtonWarning, {
            props: { randomizeUrl },
        }).then(() => {
            // Component renders with display:none (jQuery controls visibility in production)
            // Override for testing so Cypress can interact with elements
            cy.get('#randomize-button-warning').invoke('css', 'display', 'block');
        });
    });

    it('renders the warning title and body text', () => {
        cy.contains('h1', 'WARNING: Grading may be in progress!').should('be.visible');
        cy.contains('Are you sure you want to continue?').should('be.visible');
    });

    it('renders all three action buttons', () => {
        cy.get('[data-testid="randomize-cancel"]').should('be.visible');
        cy.get('[data-testid="randomize-confirm"]').should('be.visible');
        cy.contains('.close-button', 'Close').should('be.visible');
    });

    it('renders the confirm button with the correct label', () => {
        cy.get('[data-testid="randomize-confirm"]')
            .should('contain', 'Randomly Re-Assign Teams to Rotating Sections');
    });

    describe('cancel behavior', () => {
        it('emits cancel when Close button is clicked', () => {
            const onCancel = cy.stub().as('onCancel');

            cy.mount(RandomizeButtonWarning, {
                props: { randomizeUrl, onCancel },
            });
            cy.get('#randomize-button-warning').invoke('css', 'display', 'block');

            cy.contains('.close-button', 'Close').click();
            cy.get('@onCancel').should('have.callCount', 1);
        });

        it('emits cancel when Cancel button is clicked', () => {
            const onCancel = cy.stub().as('onCancel');

            cy.mount(RandomizeButtonWarning, {
                props: { randomizeUrl, onCancel },
            });
            cy.get('#randomize-button-warning').invoke('css', 'display', 'block');

            cy.get('[data-testid="randomize-cancel"]').click();
            cy.get('@onCancel').should('have.callCount', 1);
        });

        it('emits cancel when overlay is clicked', () => {
            const onCancel = cy.stub().as('onCancel');

            cy.mount(RandomizeButtonWarning, {
                props: { randomizeUrl, onCancel },
            });
            cy.get('#randomize-button-warning').invoke('css', 'display', 'block');

            cy.get('.popup-box').then(($el) => {
                $el[0].dispatchEvent(new MouseEvent('click', { bubbles: true }));
            });
            cy.get('@onCancel').should('have.callCount', 1);
        });

        it('does not emit cancel when popup window is clicked', () => {
            const onCancel = cy.stub().as('onCancel');

            cy.mount(RandomizeButtonWarning, {
                props: { randomizeUrl, onCancel },
            });
            cy.get('#randomize-button-warning').invoke('css', 'display', 'block');

            cy.get('.popup-window').click();
            cy.get('@onCancel').should('not.have.been.called');
        });
    });

    describe('confirm behavior', () => {
        it('emits confirm with the randomize URL when confirm button is clicked', () => {
            const onConfirm = cy.stub().as('onConfirm');

            cy.mount(RandomizeButtonWarning, {
                props: { randomizeUrl, onConfirm },
            });
            cy.get('#randomize-button-warning').invoke('css', 'display', 'block');

            cy.get('[data-testid="randomize-confirm"]').click();
            cy.get('@onConfirm').should('have.callCount', 1);
            cy.get('@onConfirm').should('have.been.calledWith', randomizeUrl);
        });
    });
});
