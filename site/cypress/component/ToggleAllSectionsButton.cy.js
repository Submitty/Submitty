import ToggleAllSectionsButton from '../../vue/src/components/ta_grading/ToggleAllSectionsButton.vue';
import { mountWithEmitSpy } from '../support/component_test_utils';

describe('ToggleAllSectionsButton', () => {
    it('shows "Collapse All Sections" when no sections are collapsed', () => {
        cy.mount(ToggleAllSectionsButton, { props: { collapsed: false } });
        cy.get('[data-testid="toggle-all-sections"]').should('have.text', 'Collapse All Sections');
    });

    it('shows "Expand All Sections" when sections are collapsed', () => {
        cy.mount(ToggleAllSectionsButton, { props: { collapsed: true } });
        cy.get('[data-testid="toggle-all-sections"]').should('have.text', 'Expand All Sections');
    });

    it('emits toggle-all when clicked', () => {
        mountWithEmitSpy(ToggleAllSectionsButton, 'toggleAll', { collapsed: false });

        cy.get('[data-testid="toggle-all-sections"]').click();
        cy.get('@eventHandler').should('have.been.calledOnce');
    });
});
