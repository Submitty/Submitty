import MarkSelector from '../../vue/src/components/ta_grading/MarkSelector.vue';
import { mountWithEmitSpy } from '../support/component_test_utils.js';

describe('MarkSelector', () => {
    const base = { markId: 5, componentId: 1, order: 2 };

    it('renders the mark order number', () => {
        cy.mount(MarkSelector, { props: { ...base, isChecked: false } });
        cy.get('[data-testid="mark-selector"]').should('contain', '2');
    });

    it('shows mark-selected class when isChecked is true', () => {
        cy.mount(MarkSelector, { props: { ...base, isChecked: true } });
        cy.get('[data-testid="mark-selector"] .mark-selector').should('have.class', 'mark-selected');
    });

    it('hides mark-selected class when isChecked is false', () => {
        cy.mount(MarkSelector, { props: { ...base, isChecked: false } });
        cy.get('[data-testid="mark-selector"] .mark-selector').should('not.have.class', 'mark-selected');
    });

    it('sets data-mark_id for DOM-based id lookup by legacy code', () => {
        cy.mount(MarkSelector, { props: { ...base, markId: 42, isChecked: false } });
        cy.get('[data-testid="mark-selector"]').should('have.attr', 'data-mark_id', '42');
    });

    it('emits toggle-mark with componentId and markId on click', () => {
        mountWithEmitSpy(MarkSelector, 'toggleMark', { ...base, isChecked: false }, 'toggleMark');
        cy.get('[data-testid="mark-selector"]').click();
        cy.get('@toggleMark').should('have.been.calledWith', { componentId: 1, markId: 5 });
    });

    it('stops propagation so parent onclick does not double-fire', () => {
        const onToggleMark = cy.stub().as('onToggleMark');
        cy.mount(MarkSelector, { props: { ...base, isChecked: false, onToggleMark } });
        cy.get('[data-testid="mark-selector"]').click();
        cy.get('@onToggleMark').should('have.callCount', 1);
    });

    it('handles markId 0 used for custom marks', () => {
        cy.mount(MarkSelector, { props: { ...base, markId: 0, isChecked: true } });
        cy.get('[data-testid="mark-selector"]').should('have.attr', 'data-mark_id', '0');
        cy.get('[data-testid="mark-selector"] .mark-selector').should('have.class', 'mark-selected');
    });
});
