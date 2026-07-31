import MarkSelector from '../../vue/src/components/ta_grading/MarkSelector.vue';
import { mountWithEmitSpy } from '../support/component_test_utils.js';

// Note: keyboard activation (Enter/Space) is intentionally NOT tested here - the
// span is not focusable. It is handled by the legacy parent #mark-{id} div's
// tabindex="0" + key_to_click (host element lives in Twig per migration rules).
describe('MarkSelector', () => {
    const defaultProps = () => ({
        markId: 5,
        componentId: 1,
        order: 2,
        isChecked: false,
        editMarksEnabled: false,
        markDisabled: false,
    });

    it('renders the mark order number', () => {
        cy.mount(MarkSelector, { props: defaultProps() });
        cy.get('[data-testid="mark-selector"]').should('contain', '2');
    });

    it('shows mark-selected class when isChecked is true', () => {
        cy.mount(MarkSelector, { props: { ...defaultProps(), isChecked: true } });
        cy.get('[data-testid="mark-selector"] .mark-selector').should('have.class', 'mark-selected');
    });

    it('hides mark-selected class when isChecked is false', () => {
        cy.mount(MarkSelector, { props: defaultProps() });
        cy.get('[data-testid="mark-selector"] .mark-selector').should('not.have.class', 'mark-selected');
    });

    it('sets data-mark_id for DOM-based id lookup by legacy code', () => {
        cy.mount(MarkSelector, { props: { ...defaultProps(), markId: 42 } });
        cy.get('[data-testid="mark-selector"]').should('have.attr', 'data-mark_id', '42');
    });

    it('emits toggle-mark with componentId and markId on click', () => {
        mountWithEmitSpy(MarkSelector, 'toggleMark', defaultProps(), 'toggleMark');
        cy.get('[data-testid="mark-selector"]').click();
        cy.get('@toggleMark').should('have.been.calledWith', { componentId: 1, markId: 5 });
    });

    it('does not emit toggle-mark when editMarksEnabled is true', () => {
        mountWithEmitSpy(MarkSelector, 'toggleMark', { ...defaultProps(), editMarksEnabled: true }, 'toggleMark');
        cy.get('[data-testid="mark-selector"]').click();
        cy.get('@toggleMark').should('not.have.been.called');
    });

    it('does not emit toggle-mark when markDisabled is true', () => {
        mountWithEmitSpy(MarkSelector, 'toggleMark', { ...defaultProps(), markDisabled: true }, 'toggleMark');
        cy.get('[data-testid="mark-selector"]').click();
        cy.get('@toggleMark').should('not.have.been.called');
    });

    it('handles markId 0 used for custom marks', () => {
        cy.mount(MarkSelector, { props: { ...defaultProps(), markId: 0, isChecked: true } });
        cy.get('[data-testid="mark-selector"]').should('have.attr', 'data-mark_id', '0');
        cy.get('[data-testid="mark-selector"] .mark-selector').should('have.class', 'mark-selected');
    });
});
