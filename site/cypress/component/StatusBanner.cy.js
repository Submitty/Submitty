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

    // these are edge cases too

    describe('negative emit (Vue events)', () => {
        it('does not emit a Vue color-change event', () => {
            cy.mount(StatusBanner, {
                props: { ...defaultProps, colorChange: cy.stub().as('colorChange') },
            });
            cy.get('@colorChange').should('not.have.been.called');
        });
    });

    describe('CustomEvent lifecycle', () => {
        it('dispatches the event exactly once per mount', () => {
            cy.window().then((win) => {
                cy.stub(win, 'dispatchEvent').as('dispatch');
            });
            cy.mount(StatusBanner, { props: defaultProps });
            cy.get('@dispatch').should('have.callCount', 1);
        });

        it('dispatches a CustomEvent with correct type and detail structure', () => {
            const events = [];
            cy.window().then((win) => {
                cy.stub(win, 'dispatchEvent').callsFake((ev) => events.push(ev));
            });
            cy.mount(StatusBanner, { props: defaultProps });
            cy.then(() => {
                expect(events).to.have.length(1);
                expect(events[0]).to.be.instanceOf(CustomEvent);
                expect(events[0].type).to.equal('status-banner-color-change');
                expect(events[0].detail).to.equal(defaultProps.color);
            });
        });

        it('dispatches a separate event per mount instance', () => {
            cy.window().then((win) => {
                cy.stub(win, 'dispatchEvent').as('dispatch');
            });
            cy.mount(StatusBanner, { props: defaultProps });
            cy.get('@dispatch').should('have.callCount', 1);
            cy.mount(StatusBanner, { props: defaultProps });
            cy.get('@dispatch').should('have.callCount', 2);
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
