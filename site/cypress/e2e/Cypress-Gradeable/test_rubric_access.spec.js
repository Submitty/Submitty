describe('Rubric Access Test', () => {
    it('test sample file exists', () => {
        cy.login('aphacker');
        cy.visit(['sample']);
        cy.get('.content').should('contain', "You don't have access to this page.");
        cy.visit(['courses', 's24', 'sample', 'download', 'gradeable', 'grading_homework', 'grading', 'details']);
        cy.get('#error-0').should('exist').and('contain', ' You do not have permission to grade Grading Homework');
    });

});
