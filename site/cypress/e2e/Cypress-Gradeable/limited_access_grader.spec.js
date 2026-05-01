describe('Limited Access Grader Submission View Restriction', () => {
    it('Should redirect limited access grader trying to view submission before grade start date', () => {
        // Login as an instructor and submit on behalf of a student with user id boehmd
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'notebook_filesubmission']);
        cy.get('#radio-student').click();
        cy.get('#user_id').type('boehmd');
        cy.get('input.key_to_click[value="d"]').check({ force: true });
        cy.get('#submit').click();
        cy.logout();

        // Login as limited access grader
        cy.login('grader');
        cy.visit(['sample', 'gradeable', 'notebook_filesubmission', 'grading', 'details']);
        // Handle agree popup if it appears
        cy.get('body').then(($body) => {
            if ($body.find('[data-testid="agree-popup-btn"]').length > 0) {
                cy.get('[data-testid="agree-popup-btn"]').click();
            }
        });
        cy.contains('td', 'boehmd').siblings('td').find('a[data-testid="grade-button"]').click();
        cy.url().should('include', '/courses/s26/sample');
        cy.url().should('not.include', 'grading/grade');
        cy.get('[data-testid="popup-message"]').should('be.visible').and('contain', 'You do not have permission to view submissions until grading opens.');
    });
});
