import { mountWithEmitSpy } from '../support/component_test_utils.js';
import DetailsFiltersControls from '../../vue/src/components/ta_grading/DetailsFiltersControls.vue';

describe('DetailsFiltersControls', () => {
    const defaultProps = () => ({
        showAllSections: false,
        toggleAnon: false,
        gradeInquiryOnly: false,
        canFilterWithdrawn: false,
        canGroupByClusters: false,
        anonMode: false,
        gradeableId: 'test',
    });

    const allEnabledProps = () => ({
        ...defaultProps(),
        showAllSections: true,
        toggleAnon: true,
        gradeInquiryOnly: true,
        canFilterWithdrawn: true,
        canGroupByClusters: true,
    });

    const checkboxTestCases = [
        ['view-sections', 'view-sections-change'],
        ['random-order-checkbox', 'sort-order-change'],
        ['anon-students-checkbox', 'anon-change'],
        ['inquiry-only-checkbox', 'inquiry-change'],
        ['filter-withdrawn-checkbox', 'withdrawn-change'],
        ['group-by-clusters-checkbox', 'group-by-clusters-change'],
    ];

    it('renders only the always-visible filter when all feature props are false', () => {
        cy.mount(DetailsFiltersControls, { props: defaultProps() });
        cy.get('[data-testid="random-order-label"]').should('exist');
        cy.get('[data-testid="view-sections-label"]').should('not.exist');
        cy.get('[data-testid="anon-students-label"]').should('not.exist');
        cy.get('[data-testid="inquiry-only-label"]').should('not.exist');
        cy.get('[data-testid="filter-withdrawn-label"]').should('not.exist');
        cy.get('[data-testid="group-by-clusters-label"]').should('not.exist');
    });

    it('renders all conditional filters when their props are true', () => {
        cy.mount(DetailsFiltersControls, { props: allEnabledProps() });
        cy.get('[data-testid="random-order-label"]').should('exist');
        cy.get('[data-testid="view-sections-label"]').should('exist');
        cy.get('[data-testid="anon-students-label"]').should('exist');
        cy.get('[data-testid="inquiry-only-label"]').should('exist');
        cy.get('[data-testid="filter-withdrawn-label"]').should('exist');
        cy.get('[data-testid="group-by-clusters-label"]').should('exist');
    });

    it('respects the initial* props as checkbox checked state', () => {
        cy.mount(DetailsFiltersControls, {
            props: {
                ...allEnabledProps(),
                initialViewSections: true,
                initialRandomOrder: true,
                initialInquiryOnly: true,
                initialHideWithdrawn: true,
                initialGroupByClusters: true,
            },
        });
        cy.get('[data-testid="view-sections"]').should('be.checked');
        cy.get('[data-testid="random-order-checkbox"]').should('be.checked');
        cy.get('[data-testid="inquiry-only-checkbox"]').should('be.checked');
        cy.get('[data-testid="filter-withdrawn-checkbox"]').should('be.checked');
        cy.get('[data-testid="group-by-clusters-checkbox"]').should('be.checked');
    });

    it('respects anonMode prop directly as the anon checkbox state and passes gradeableId to its label', () => {
        cy.mount(DetailsFiltersControls, {
            props: { ...defaultProps(), toggleAnon: true, anonMode: true, gradeableId: 'g1' },
        });
        cy.get('[data-testid="anon-students-checkbox"]').should('be.checked');
        cy.get('[data-testid="anon-students-label"]').should('have.attr', 'data-gradeable-id', 'g1');
    });

    it('defaults all initial* props to unchecked when not provided', () => {
        cy.mount(DetailsFiltersControls, { props: allEnabledProps() });
        cy.get('[data-testid="view-sections"]').should('not.be.checked');
        cy.get('[data-testid="random-order-checkbox"]').should('not.be.checked');
        cy.get('[data-testid="inquiry-only-checkbox"]').should('not.be.checked');
        cy.get('[data-testid="filter-withdrawn-checkbox"]').should('not.be.checked');
        cy.get('[data-testid="group-by-clusters-checkbox"]').should('not.be.checked');
    });

    it('emits mounted with the initial inquiry-only state', () => {
        mountWithEmitSpy(DetailsFiltersControls, 'mounted', {
            ...defaultProps(),
            gradeInquiryOnly: true,
            initialInquiryOnly: true,
        });
        cy.get('@eventHandler').should('have.been.calledWith', { inquiryOnly: true });
    });

    checkboxTestCases.forEach(([testId, eventName]) => {
        it(`emits ${eventName} with the checked state when toggling its checkbox`, () => {
            mountWithEmitSpy(DetailsFiltersControls, eventName, {
                ...allEnabledProps(),
                anonMode: false,
            });
            cy.get(`[data-testid="${testId}"]`).as('cb');
            cy.get('@cb').check({ force: true });
            cy.get('@eventHandler').should('have.been.calledWith', true);
            cy.get('@cb').should('be.checked');
            cy.get('@cb').uncheck({ force: true });
            cy.get('@eventHandler').should('have.been.calledWith', false);
            cy.get('@cb').should('not.be.checked');
        });
    });
});
