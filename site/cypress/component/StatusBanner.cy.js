import StatusBanner from '../../vue/src/components/StatusBanner.vue';

describe('StatusBanner', () => {
    const defaultProps = {
        message: 'Late Submission (Using one or more late days)',
        color: 'var(--standard-medium-orange)',
    };

    describe('rendering', () => {
        it('renders the message text in the banner', () => {
            cy.mount(StatusBanner, { props: defaultProps });
            cy.get('[data-testid="status-banner"]').should('have.text', defaultProps.message);
        });

        it('applies the correct background color via inline style', () => {
            cy.mount(StatusBanner, { props: defaultProps });
            cy.get('[data-testid="status-banner"]')
                .should('have.attr', 'style')
                .and('contain', 'background-color: var(--standard-medium-orange)');
        });

        it('sets text color to black', () => {
            cy.mount(StatusBanner, { props: defaultProps });
            cy.get('[data-testid="status-banner"]')
                .should('have.css', 'color', 'rgb(0, 0, 0)');
        });

        it('has the bar_banner id for legacy DOM compatibility', () => {
            cy.mount(StatusBanner, { props: defaultProps });
            cy.get('#bar_banner').should('exist');
        });
    });

    describe('props', () => {
        it('renders No Submission status with pink background', () => {
            cy.mount(StatusBanner, {
                props: { message: 'No Submission', color: 'var(--standard-light-pink)' },
            });
            cy.get('[data-testid="status-banner"]').should('have.text', 'No Submission');
            cy.get('[data-testid="status-banner"]')
                .should('have.attr', 'style')
                .and('contain', 'background-color: var(--standard-light-pink)');
        });

        it('renders Cancelled Submission status with orange background', () => {
            cy.mount(StatusBanner, {
                props: { message: 'Cancelled Submission', color: 'var(--standard-creamsicle-orange)' },
            });
            cy.get('[data-testid="status-banner"]').should('have.text', 'Cancelled Submission');
            cy.get('[data-testid="status-banner"]')
                .should('have.attr', 'style')
                .and('contain', 'background-color: var(--standard-creamsicle-orange)');
        });

        it('renders Withdrawn Student status with yellow background', () => {
            cy.mount(StatusBanner, {
                props: { message: 'Withdrawn Student', color: 'var(--standard-vibrant-yellow)' },
            });
            cy.get('[data-testid="status-banner"]').should('have.text', 'Withdrawn Student');
            cy.get('[data-testid="status-banner"]')
                .should('have.attr', 'style')
                .and('contain', 'background-color: var(--standard-vibrant-yellow)');
        });

        it('renders Overridden grades with reason in message', () => {
            const message = 'Overridden grades: 85 / 100 (Reason: Instructor adjustment)';
            cy.mount(StatusBanner, {
                props: { message, color: 'var(--standard-vibrant-yellow)' },
            });
            cy.get('[data-testid="status-banner"]').should('have.text', message);
        });
    });

    describe('emits', () => {
        it('emits color-change exactly once on mount with the color value', () => {
            const onColorChange = cy.stub().as('onColorChange');
            cy.mount(StatusBanner, { props: { ...defaultProps, onColorChange } });
            cy.get('@onColorChange')
                .should('have.callCount', 1)
                .and('be.calledWith', defaultProps.color);
        });
    });

    describe('CustomEvent dispatch', () => {
        it('dispatches status-banner-color-change with the color on mount', () => {
            cy.window().then((win) => {
                cy.stub(win, 'dispatchEvent').as('dispatch');
            });

            cy.mount(StatusBanner, { props: defaultProps });
            cy.get('@dispatch').should('be.calledWith',
                Cypress.sinon.match.instanceOf(CustomEvent)
                    .and(Cypress.sinon.match.has('type', 'status-banner-color-change'))
                    .and(Cypress.sinon.match((ev) => ev.detail === defaultProps.color)));
        });

        it('dispatches with the correct color for each status type', () => {
            cy.window().then((win) => {
                cy.stub(win, 'dispatchEvent').as('dispatch');
            });

            cy.mount(StatusBanner, {
                props: { message: 'No Submission', color: 'var(--standard-light-pink)' },
            });
            cy.get('@dispatch').should('be.calledWith',
                Cypress.sinon.match.instanceOf(CustomEvent)
                    .and(Cypress.sinon.match.has('type', 'status-banner-color-change'))
                    .and(Cypress.sinon.match((ev) => ev.detail === 'var(--standard-light-pink)')));
        });
    });

    describe('edge cases', () => {
        it('renders with an empty message string', () => {
            cy.mount(StatusBanner, { props: { message: '', color: 'red' } });
            cy.get('[data-testid="status-banner"]').should('have.text', '');
            cy.get('[data-testid="status-banner"]').should('exist');
        });

        it('renders with an empty color string', () => {
            cy.mount(StatusBanner, { props: { message: 'Test', color: '' } });
            cy.get('[data-testid="status-banner"]').should('have.text', 'Test');
        });

        it('renders a very long message without breaking layout', () => {
            const longMessage = 'A'.repeat(500);
            cy.mount(StatusBanner, { props: { message: longMessage, color: 'red' } });
            cy.get('[data-testid="status-banner"]').should('have.text', longMessage);
        });

        it('renders special characters in message as escaped text, not HTML', () => {
            const specialMessage = '<script>alert("xss")</script>';
            cy.mount(StatusBanner, { props: { message: specialMessage, color: 'red' } });
            cy.get('[data-testid="status-banner"]').should('have.text', specialMessage);
        });

        it('renders ampersand and quotes in message', () => {
            const message = 'User1 & User2 said "hello" and \'goodbye\'';
            cy.mount(StatusBanner, { props: { message, color: 'red' } });
            cy.get('[data-testid="status-banner"]').should('have.text', message);
        });
    });
});
