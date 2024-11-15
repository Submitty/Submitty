describe('Test cases revolving around deletion of gradeables', () => {
    it('should delete gradeable', () => {
        cy.login('instructor');
        cy.visit(['blank', 'gradeable']);
        // Enter gradeable info
        cy.get('[data-testid=create-gradeable-title]').type('TestGradeable');
        cy.get('[data-testid=create-gradeable-id]').type('TestGradeable');
        // force needed to click radio button
        cy.get('[data-testid=radio-student-upload]').check({ force: true });
        // Create Gradeable
        cy.get('[data-testid=create-gradeable-btn]').click();
        cy.visit(['blank']);
        cy.get('[title="Delete Gradeable"]').click();
        cy.get('[data-testid="confirm-delete-gradeable"]').click();
        cy.get('[title="Delete Gradeable"]').should('not.exist');
    });
    it('should be unable to delete gradeable with submissions', () => {
        cy.login('instructor');
        cy.visit(['sample']);
        cy.get('[data-testid="no_due_date_no_release"] [title="Delete Gradeable"]').should('be.visible');
        cy.get('[data-testid="leaderboard"] [title="Delete Gradeable"]').should('not.exist');
    });
});
