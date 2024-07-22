describe('Rubric Access Test', () => {
    it('test sample file exists student', () => {
        cy.login('aphacker');
        cy.visit(['sample', 'download']);
        cy.get('.content').should('contain', "You don't have access to this page.");
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);
        cy.get('#error-0').should('contain', 'You do not have permission to grade Grading Homework');
        cy.get('.alert-error').contains('You do not have permission to grade Grading Homework');
        cy.logout();
    });
    it('test rubric delete file attachment ta and instructor', () => {
        cy.login('ta');
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);
        cy.get('[data-testid="popup-window"]').should('exist');
        cy.get('#agree-button').click();
        cy.get('[data-testid="view-sections"]').click();
        cy.get('[data-testid="grade-button"]').eq(12).click();
        cy.get('[data-testid="grading-rubric-btn"]').click();
        const filePath = '../more_autograding_examples/image_diff_mirror/submissions/student1.png';
        cy.get('#attachment-upload').selectFile(filePath);
        cy.get('.key_to_click').find('[title="Delete the file"]').click();
    });
});
