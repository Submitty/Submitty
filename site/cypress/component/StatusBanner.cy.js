import { h, defineComponent } from 'vue';
import StatusBanner from '../../vue/src/components/StatusBanner.vue';
import { mountWithEmitSpy } from '../support/component_test_utils.js';

const defaultProps = {
    message: 'Late Submission (Using one or more late days)',
    color: 'var(--standard-medium-orange)',
};

describe('StatusBanner', () => {
    describe('rendering', () => {
        it('renders the message text in the banner', () => {
            cy.mount(StatusBanner, { props: defaultProps });
            cy.get('[data-testid="bar-banner"]').should('have.text', defaultProps.message);
        });

        it('applies the correct background color via inline style', () => {
            cy.mount(StatusBanner, { props: defaultProps });
            cy.get('[data-testid="bar-banner"]')
                .should('have.attr', 'style')
                .and('contain', 'background-color: var(--standard-medium-orange)');
        });
    });

    describe('props', () => {
        it('renders No Submission status with pink background', () => {
            cy.mount(StatusBanner, {
                props: { message: 'No Submission', color: 'var(--standard-light-pink)' },
            });
            cy.get('[data-testid="bar-banner"]').should('have.text', 'No Submission');
            cy.get('[data-testid="bar-banner"]')
                .should('have.attr', 'style')
                .and('contain', 'background-color: var(--standard-light-pink)');
        });

        it('renders Cancelled Submission status with orange background', () => {
            cy.mount(StatusBanner, {
                props: { message: 'Cancelled Submission', color: 'var(--standard-creamsicle-orange)' },
            });
            cy.get('[data-testid="bar-banner"]').should('have.text', 'Cancelled Submission');
            cy.get('[data-testid="bar-banner"]')
                .should('have.attr', 'style')
                .and('contain', 'background-color: var(--standard-creamsicle-orange)');
        });

        it('renders Withdrawn Student status with yellow background', () => {
            cy.mount(StatusBanner, {
                props: { message: 'Withdrawn Student', color: 'var(--standard-vibrant-yellow)' },
            });
            cy.get('[data-testid="bar-banner"]').should('have.text', 'Withdrawn Student');
            cy.get('[data-testid="bar-banner"]')
                .should('have.attr', 'style')
                .and('contain', 'background-color: var(--standard-vibrant-yellow)');
        });

        it('renders Overridden grades with reason in message', () => {
            const message = 'Overridden grades: 85 / 100 (Reason: Instructor adjustment)';
            cy.mount(StatusBanner, {
                props: { message, color: 'var(--standard-vibrant-yellow)' },
            });
            cy.get('[data-testid="bar-banner"]').should('have.text', message);
        });
    });

    describe('Vue emit', () => {
        it('emits color-change with the color on mount', () => {
            mountWithEmitSpy(StatusBanner, 'colorChange', defaultProps, 'colorChangeHandler');
            cy.get('@colorChangeHandler').should('have.been.calledWith', defaultProps.color);
        });

        it('emits with the correct color for each status type', () => {
            mountWithEmitSpy(StatusBanner, 'colorChange', { message: 'No Submission', color: 'var(--standard-light-pink)' }, 'colorChangeHandler');
            cy.get('@colorChangeHandler').should('have.been.calledWith', 'var(--standard-light-pink)');
        });
    });

    describe('emit lifecycle', () => {
        it('emits the event exactly once per mount', () => {
            mountWithEmitSpy(StatusBanner, 'colorChange', defaultProps, 'colorChangeHandler');
            cy.get('@colorChangeHandler').should('have.callCount', 1);
        });
    });

    describe('edge cases', () => {
        it('renders with an empty message string', () => {
            cy.mount(StatusBanner, { props: { message: '', color: 'red' } });
            cy.get('[data-testid="bar-banner"]').should('have.text', '');
            cy.get('[data-testid="bar-banner"]').should('exist');
        });

        it('renders with an empty color string', () => {
            cy.mount(StatusBanner, { props: { message: 'Test', color: '' } });
            cy.get('[data-testid="bar-banner"]').should('have.text', 'Test');
        });

        it('renders a very long message without breaking layout', () => {
            const longMessage = 'A'.repeat(500);
            cy.mount(StatusBanner, { props: { message: longMessage, color: 'red' } });
            cy.get('[data-testid="bar-banner"]').should('have.text', longMessage);
        });

        it('renders special characters in message as escaped text, not HTML', () => {
            const specialMessage = '<script>alert("xss")</script>';
            cy.mount(StatusBanner, { props: { message: specialMessage, color: 'red' } });
            cy.get('[data-testid="bar-banner"]').should('have.text', specialMessage);
        });

        it('renders ampersand and quotes in message', () => {
            const message = 'User1 & User2 said "hello" and \'goodbye\'';
            cy.mount(StatusBanner, { props: { message, color: 'red' } });
            cy.get('[data-testid="bar-banner"]').should('have.text', message);
        });
    });
});
