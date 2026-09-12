import ToggleColumns from '../../vue/src/components/ToggleColumns.vue';

describe('ToggleColumns labels', () => {
    it('toggles the matching checkbox when its visible label is clicked', () => {
        cy.mount(ToggleColumns, {
            props: {
                columns: ['first-name', 'last-name'],
                labels: ['Given Name', 'Family Name'],
                cookie: 'test_column_labels',
            },
        });
        cy.get('[data-testid="toggle-columns"]').click();
        cy.get('[data-testid="toggle-first-name"]').should('be.checked');
        cy.get('[data-testid="toggle-last-name"]').should('be.checked');

        cy.contains('label', 'Given Name').click();
        cy.get('[data-testid="toggle-first-name"]').should('not.be.checked');
        cy.get('[data-testid="toggle-last-name"]').should('be.checked');

        cy.contains('label', 'Family Name').click();
        cy.get('[data-testid="toggle-last-name"]').should('not.be.checked');
        cy.get('[data-testid="toggle-first-name"]').should('not.be.checked');

        cy.contains('label', 'Given Name').click();
        cy.get('[data-testid="toggle-first-name"]').should('be.checked');
    });

    it('keeps forced columns disabled when their labels are clicked', () => {
        cy.mount(ToggleColumns, {
            props: {
                columns: ['user-id'],
                labels: ['User ID'],
                forced: ['user-id'],
                cookie: 'test_forced_column_labels',
            },
        });
        cy.get('[data-testid="toggle-columns"]').click();
        cy.get('[data-testid="toggle-user-id"]').should('be.checked').and('be.disabled');
        cy.contains('label', 'User ID').click();
        cy.get('[data-testid="toggle-user-id"]').should('be.checked').and('be.disabled');
    });
});
