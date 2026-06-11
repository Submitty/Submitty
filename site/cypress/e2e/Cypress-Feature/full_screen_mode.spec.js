describe('Full screen mode toggle', () => {
    beforeEach(() => {
        cy.login('instructor');
    });

    it('Should toggle full screen mode on Discussion Forum', () => {
        cy.visit(['sample', 'forum']);

        // Verify full screen button exists
        cy.get('[data-fullscreen-toggle]').should('exist');

        // Main should not have full-screen-mode class initially
        cy.get('main#main').should('not.have.class', 'full-screen-mode');

        // Click to enter full screen
        cy.get('[data-fullscreen-toggle]').click();
        cy.get('main#main').should('have.class', 'full-screen-mode');

        // Icon should switch to compress
        cy.get('[data-fullscreen-toggle] i').should('have.class', 'fa-compress');
        cy.get('[data-fullscreen-toggle] i').should('not.have.class', 'fa-expand');

        // Click again to exit full screen
        cy.get('[data-fullscreen-toggle]').click();
        cy.get('main#main').should('not.have.class', 'full-screen-mode');

        // Icon should switch back to expand
        cy.get('[data-fullscreen-toggle] i').should('have.class', 'fa-expand');
        cy.get('[data-fullscreen-toggle] i').should('not.have.class', 'fa-compress');
    });

    it('Should toggle full screen mode on Simple Grading', () => {
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?view=all']);

        // Verify full screen button exists
        cy.get('[data-fullscreen-toggle]').should('exist');

        // Click to enter full screen
        cy.get('[data-fullscreen-toggle]').click();
        cy.get('main#main').should('have.class', 'full-screen-mode');
        cy.get('[data-fullscreen-toggle] i').should('have.class', 'fa-compress');

        // Click to exit full screen
        cy.get('[data-fullscreen-toggle]').click();
        cy.get('main#main').should('not.have.class', 'full-screen-mode');
        cy.get('[data-fullscreen-toggle] i').should('have.class', 'fa-expand');
    });

    it('Should toggle full screen mode on Live Chat', () => {
        cy.visit(['sample', 'chat']);

        // Verify full screen button exists
        cy.get('[data-fullscreen-toggle]').should('exist');

        // Click to enter full screen
        cy.get('[data-fullscreen-toggle]').click();
        cy.get('main#main').should('have.class', 'full-screen-mode');
        cy.get('[data-fullscreen-toggle] i').should('have.class', 'fa-compress');

        // Click to exit full screen
        cy.get('[data-fullscreen-toggle]').click();
        cy.get('main#main').should('not.have.class', 'full-screen-mode');
        cy.get('[data-fullscreen-toggle] i').should('have.class', 'fa-expand');
    });
});
