describe('test for discussion post panel', () => {
    before(() => {
        cy.login();
        // Reset the gradeable to the expected state
        cy.visit(['sample', 'gradeable', 'grading_homework', 'update']);
        // toggle between options to trigger the save status
        cy.get('[data-testid="yes-discussion"]').click();
        cy.get('[data-testid="no-discussion"]').click();
        cy.get('[data-testid="save-status"]', { timeout: 30000 }).should('contain', 'All Changes Saved');
    });

    it('Enable discussion form and post', () => {
        cy.visit(['sample', 'config']);
        cy.get('[data-testid="forum-enabled"]').should('be.checked');

        cy.visit(['sample', 'gradeable', 'grading_homework', 'update']);
        cy.get('[data-testid="yes-discussion"]').click();
        cy.get('[data-testid="yes-discussion"]').should('be.checked');
        cy.get('[data-testid="save-status"]', { timeout: 30000 }).should('contain', 'All Changes Saved');

        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=L1LdxbPPN5GiLb4&sort=id&direction=ASC']);
        cy.get('[data-testid="discussion-browser-btn"]').type('{D}');
        cy.get('[data-testid="posts-list"]').should('contain', 'Discussion Posts');
        cy.get('[data-testid="posts-list"]').should('contain', 'No thread id specified.');
        cy.get('[data-testid="posts-list"]').should('not.contain', 'Go to thread');

        cy.visit(['sample', 'gradeable', 'grading_homework', 'update']);
        cy.get('[data-testid="discussion-thread-id"]').type('1,2,3');
        cy.get('[data-testid="discussion-thread-id"]').type('{enter}');
        cy.get('[data-testid="save-status"]', { timeout: 30000 }).should('contain', 'All Changes Saved');

        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=L1LdxbPPN5GiLb4&sort=id&direction=ASC']);
        cy.get('[data-testid="posts-list"]').should('contain', 'No posts for thread id: 1');
        cy.get('[data-testid="create-post-head"]').first().should('contain', '(2) Homework 1 print clarification');
        cy.get('[data-testid="posts-list"]').should('contain', 'Go to thread');
        cy.get('[data-testid="go-to-thread"]').its('length').should('eq', 3);
        cy.get('[data-testid="go-to-thread"]').eq(1).should('have.attr', 'href').and('contain', 'sample/forum/threads/2');
    });
});
