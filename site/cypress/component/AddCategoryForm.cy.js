import AddCategoryForm from '@/components/forum/AddCategoryForm.vue';
import { mountWithEmitSpy } from '../support/component_test_utils';

const defaultProps = { csrfToken: 'test-csrf-token' };

describe('AddCategoryForm', () => {
    it('renders the name input, date input, and add button', () => {
        cy.mount(AddCategoryForm, { props: defaultProps });
        cy.get('[data-testid="add-category-name-input"]').should('exist');
        cy.get('[data-testid="add-category-date-input"]').should('exist');
        cy.get('[data-testid="add-category-button"]').should('contain', 'Add category');
    });

    it('has accessible markup: aria-labels, placeholder, and button title', () => {
        cy.mount(AddCategoryForm, { props: defaultProps });
        cy.get('[data-testid="add-category-name-input"]').should('have.attr', 'aria-label', 'New Category');
        cy.get('[data-testid="add-category-date-input"]').should('have.attr', 'aria-label', 'Visible Date');
        cy.get('[data-testid="add-category-date-input"]').should('have.attr', 'placeholder', 'YYYY-MM-DD');
        cy.get('[data-testid="add-category-button"]').should('have.attr', 'title', 'Add new category');
    });

    it('emits add-category with name, visibleDate, and csrfToken on click', () => {
        mountWithEmitSpy(AddCategoryForm, 'add-category', defaultProps);
        cy.get('[data-testid="add-category-name-input"]').type('Homework Help');
        cy.get('[data-testid="add-category-button"]').click();
        cy.get('@eventHandler').should('have.been.calledOnceWith', {
            name: 'Homework Help',
            visibleDate: '',
            csrfToken: 'test-csrf-token',
        });
    });

    it('includes the selected visible date in the add-category payload', () => {
        const pad = (n) => String(n).padStart(2, '0');
        const today = `${new Date().getFullYear()}-${pad(new Date().getMonth() + 1)}-${pad(new Date().getDate())}`;

        mountWithEmitSpy(AddCategoryForm, 'add-category', defaultProps);
        cy.get('[data-testid="add-category-name-input"]').type('Dated Category');
        cy.get('[data-testid="add-category-date-input"]').click();
        // flatpickr renders its calendar into the document body, so its own DOM is
        // selected by flatpickr's class
        cy.get('.flatpickr-day.today').click();
        cy.get('[data-testid="add-category-date-input"]').should('have.value', today);
        cy.get('[data-testid="add-category-button"]').click();
        cy.get('@eventHandler').should('have.been.calledOnceWith', {
            name: 'Dated Category',
            visibleDate: today,
            csrfToken: 'test-csrf-token',
        });
    });

    it('emits max-length when the name input reaches 50 characters', () => {
        mountWithEmitSpy(AddCategoryForm, 'max-length', defaultProps);
        cy.get('[data-testid="add-category-name-input"]').type('a'.repeat(50));
        cy.get('@eventHandler').should('have.been.calledOnce');
    });

    it('does not emit max-length below 50 characters', () => {
        mountWithEmitSpy(AddCategoryForm, 'max-length', defaultProps);
        cy.get('[data-testid="add-category-name-input"]').type('Short name');
        cy.get('@eventHandler').should('not.have.been.called');
    });

    it('does not throw when no event handlers are provided', () => {
        cy.mount(AddCategoryForm, { props: defaultProps });
        cy.get('[data-testid="add-category-name-input"]').type('No Handlers');
        cy.get('[data-testid="add-category-button"]').click();
        cy.get('[data-testid="add-category-form"]').should('exist');
    });
});
