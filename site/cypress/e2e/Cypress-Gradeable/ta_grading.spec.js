describe('Test cases for TA grading page', () => {
    it('Grader should be able to add and remove overall comments', () => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=jKjodWaRdEV9pBb&sort=id&direction=ASC']);
        cy.get('#grading_rubric_btn').click();
        cy.get('#overall-comment-instructor').should('have.value', '');
        cy.get('#overall-comment-instructor').type('Comment1');
        cy.get('#overall-comment-instructor').blur();
        cy.get('.overall-comment-status').should('contain', 'All Changes Saved');

        cy.reload();
        cy.get('#overall-comment-instructor').should('have.value', 'Comment1');

        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=aYl92mR3NvJYGrK&sort=id&direction=ASC']);
        cy.get('#overall-comment-instructor').should('have.value', '');
        cy.get('#overall-comment-instructor').type('Comment2');
        cy.get('#overall-comment-instructor').blur();
        cy.get('.overall-comment-status').should('contain', 'All Changes Saved');

        cy.reload();
        cy.get('#overall-comment-instructor').should('have.value', 'Comment2');

        cy.get('#overall-comment-instructor').clear();
        cy.get('#overall-comment-instructor').blur();
        cy.get('.overall-comment-status').should('contain', 'All Changes Saved');

        cy.reload();
        cy.get('#overall-comment-instructor').should('have.value', '');

        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=jKjodWaRdEV9pBb&sort=id&direction=ASC']);
        cy.get('#overall-comment-instructor').should('have.value', 'Comment1');
        cy.get('#overall-comment-instructor').clear();
        cy.get('#overall-comment-instructor').blur();
        cy.get('.overall-comment-status').should('contain', 'All Changes Saved');
    });
});
