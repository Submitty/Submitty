describe('Notebook Section Testing', () => {
    it('Notebook builder and Notebook panel', () => {
        cy.login();
        cy.visit(['sample', 'gradeable', 'grading_homework', 'update?nav_tab=1']);
        cy.get('[data-testid="start-new-notebook"]').click(); // creating notebook
        cy.get('[data-testid="notebook-builder-title"]').should('contain', 'Grading Homework Notebook Builder');
        // Assertion for the present buttons on Notebook Builder Section
        cy.get('[data-testid="multiple-choice"]').should('have.value', 'Multiple Choice');
        cy.get('[data-testid="markdown"]').should('have.value', 'Markdown');
        cy.get('[data-testid="short-answer"]').should('have.value', 'Short Answer');
        cy.get('[data-testid="image"]').should('have.value', 'Image');
        cy.get('[data-testid="item"]').should('have.value', 'Item');
        cy.get('[data-testid="itempool-item"]').should('have.value', 'Itempool Item');
        // Creating a notebook with Markdown
        cy.get('[data-testid="markdown"]').click();
        cy.get('[data-testid="notebook-builder-markdown-0"]').click();
        cy.get('[data-testid="notebook-builder-markdown-0"]').type('# Notebook-Cypress-Test');
        cy.get('[data-testid="notebook-save"]').click();
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=hG1b13ljpDjKu32&sort=id&direction=ASC']);
        cy.get('body').type('{N}'); // Notebook View
        cy.get('[data-testid="notebook-view"]').should('contain', 'Notebook View');
        cy.get('[data-testid="notebook-main-view"]').should('contain', 'Notebook-Cypress-Test');
        // removing the notebook from notebook builder widget control
        cy.visit(['sample', 'gradeable', 'grading_homework', 'update?nav_tab=1']);
        cy.get('[data-testid="edit-existing-notebook"]').click();
        cy.get('[data-testid="remove"]').click();
        cy.get('[data-testid="notebook-save"]').click();
    });
});
