import { mountWithEmitSpy } from '../support/component_test_utils.js';
import DetailsFiltersControls from '../../vue/src/components/ta_grading/DetailsFiltersControls.vue';

describe('DetailsFiltersControls', () => {
    const defaultProps = () => ({
        showAllSections: false,
        toggleAnon: false,
        gradeInquiryOnly: false,
        canFilterWithdrawn: false,
        anonMode: false,
        isTeamAssignment: false,
        gradeableId: 'test',
    });

    it('renders only the always-visible filter when all feature props are false', () => {
        cy.mount(DetailsFiltersControls, { props: defaultProps() });
        cy.get('[data-testid="random-order-label"]').should('exist');
        cy.get('[data-testid="view-sections-label"]').should('not.exist');
        cy.get('[data-testid="anon-students-label"]').should('not.exist');
        cy.get('[data-testid="inquiry-only-label"]').should('not.exist');
        cy.get('[data-testid="filter-withdrawn-label"]').should('not.exist');
    });

    it('renders all conditional filters when their props are true', () => {
        cy.mount(DetailsFiltersControls, {
            props: {
                ...defaultProps(),
                showAllSections: true,
                toggleAnon: true,
                gradeInquiryOnly: true,
                canFilterWithdrawn: true,
            },
        });
        cy.get('[data-testid="random-order-label"]').should('exist');
        cy.get('[data-testid="view-sections-label"]').should('exist');
        cy.get('[data-testid="anon-students-label"]').should('exist');
        cy.get('[data-testid="inquiry-only-label"]').should('exist');
        cy.get('[data-testid="filter-withdrawn-label"]').should('exist');
    });

    describe('initial state from props', () => {
        it('respects initial* boolean props as checkbox checked state', () => {
            cy.mount(DetailsFiltersControls, {
                props: {
                    ...defaultProps(),
                    showAllSections: true,
                    gradeInquiryOnly: true,
                    canFilterWithdrawn: true,
                    initialViewSections: true,
                    initialRandomOrder: true,
                    initialInquiryOnly: true,
                    initialHideWithdrawn: true,
                },
            });
            cy.get('[data-testid="view-sections"]').should('be.checked');
            cy.get('[data-testid="random-order-checkbox"]').should('be.checked');
            cy.get('[data-testid="inquiry-only-checkbox"]').should('be.checked');
            cy.get('[data-testid="filter-withdrawn-checkbox"]').should('be.checked');
        });

        it('respects anonMode prop directly as anon checkbox state', () => {
            cy.mount(DetailsFiltersControls, {
                props: { ...defaultProps(), toggleAnon: true, anonMode: true },
            });
            cy.get('[data-testid="anon-students-checkbox"]').should('be.checked');
        });

        it('defaults all initial* props to false when undefined', () => {
            cy.mount(DetailsFiltersControls, {
                props: { ...defaultProps(), showAllSections: true, gradeInquiryOnly: true, canFilterWithdrawn: true },
            });
            cy.get('[data-testid="view-sections"]').should('not.be.checked');
            cy.get('[data-testid="random-order-checkbox"]').should('not.be.checked');
            cy.get('[data-testid="inquiry-only-checkbox"]').should('not.be.checked');
            cy.get('[data-testid="filter-withdrawn-checkbox"]').should('not.be.checked');
        });
    });

    describe('emits events on user interaction', () => {
        it('emits mounted on mount with current inquiry state', () => {
            mountWithEmitSpy(DetailsFiltersControls, 'mounted', {
                ...defaultProps(),
                gradeInquiryOnly: true,
                initialInquiryOnly: true,
            });
            cy.get('@eventHandler').should('have.been.calledWith', { inquiryOnly: true });
        });

        it('emits *-change events with the checked state when toggling checkboxes', () => {
            mountWithEmitSpy(DetailsFiltersControls, 'sort-order-change', {
                ...defaultProps(),
            });
            cy.get('[data-testid="random-order-checkbox"]').as('cb');
            cy.get('@cb').check({ force: true });
            cy.get('@eventHandler').should('have.been.calledWith', true);
            cy.get('@cb').uncheck({ force: true });
            cy.get('@eventHandler').should('have.been.calledWith', false);
        });

        it('emits anon-change without tracking local state', () => {
            mountWithEmitSpy(DetailsFiltersControls, 'anon-change', {
                ...defaultProps(),
                toggleAnon: true,
                anonMode: false,
            });
            cy.get('[data-testid="anon-students-checkbox"]').as('cb');
            cy.get('@cb').check({ force: true });
            cy.get('@eventHandler').should('have.been.calledWith', true);
            cy.get('@cb').uncheck({ force: true });
            cy.get('@eventHandler').should('have.been.calledWith', false);
        });
    });
});
