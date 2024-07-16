describe('Test cases for TA grading page', () => {
    it('Grader should be able to add and remove overall comments', () => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=jKjodWaRdEV9pBb&sort=id&direction=ASC']);
        cy.get('[data-testid="grading-rubric-btn"]').click();
        cy.get('[data-testid="overall-comment-instructor"]').should('have.value', '');
        cy.get('[data-testid="overall-comment-instructor"]').type('Comment1');
        cy.get('[data-testid="overall-comment-instructor"]').blur();
        cy.get('[data-testid="overall-comment-status"]').should('contain', 'All Changes Saved');

        cy.reload();
        cy.get('[data-testid="overall-comment-instructor"]').should('have.value', 'Comment1');

        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=aYl92mR3NvJYGrK&sort=id&direction=ASC']);
        cy.get('[data-testid="overall-comment-instructor"]').should('have.value', '');
        cy.get('[data-testid="overall-comment-instructor"]').type('Comment2');
        cy.get('[data-testid="overall-comment-instructor"]').blur();
        cy.get('[data-testid="overall-comment-status"]').should('contain', 'All Changes Saved');

        cy.reload();
        cy.get('[data-testid="overall-comment-instructor"]').should('have.value', 'Comment2');

        cy.get('[data-testid="overall-comment-instructor"]').clear();
        cy.get('[data-testid="overall-comment-instructor"]').blur();
        cy.get('[data-testid="overall-comment-status"]').should('contain', 'All Changes Saved');

        cy.reload();
        cy.get('[data-testid="overall-comment-instructor"]').should('have.value', '');

        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=jKjodWaRdEV9pBb&sort=id&direction=ASC']);
        cy.get('[data-testid="overall-comment-instructor"]').should('have.value', 'Comment1');
        cy.get('[data-testid="overall-comment-instructor"]').clear();
        cy.get('[data-testid="overall-comment-instructor"]').blur();
        cy.get('[data-testid="overall-comment-status"]').should('contain', 'All Changes Saved');
    });
});
