describe('Forum user timeout for discussion posts', () => {
    it('allows a student to create a forum thread before being blocked', () => {
        cy.login('student');
        cy.visit('http://localhost:1511/courses/s26/development/forum/threads/new');

        cy.get('#title').type('Test title');
        cy.get('#reply_box_1').type('This is a test forum post.');
        cy.get('#categories-pick-list .cat-buttons').first().click();
        cy.get('[data-testid="forum-publish-thread"]').click();

        cy.logout();
    });

    it('allows an instructor to block the student from the forum thread dropdown', () => {
        cy.login('instructor');
        cy.visit('http://localhost:1511/courses/s26/development/forum/threads');
        cy.get('[data-testid="thread-list-item"]').contains('Test title').click();

        cy.get('[data-testid="thread-dropdown"]').click();
        cy.get('[aria-label="Block from Forum"]').click();
        cy.get('#block-user-form').should('be.visible');
        cy.get('#block-user-form .form-button-container a').eq(1).click();

        cy.logout();
    });

    it('shows that the instructor can now unblock the blocked student', () => {
        cy.login('instructor');
        cy.visit('http://localhost:1511/courses/s26/development/forum/threads');
        cy.get('[data-testid="thread-list-item"]').contains('Test title').click();

        cy.reload();
        cy.get('[data-testid="thread-dropdown"]').click();
        cy.get('[aria-label="Unblock from Forum"]').should('exist');

        cy.logout();
    });

    it('prevents the blocked student from creating another forum thread', () => {
        cy.login('student');
        cy.visit('http://localhost:1511/courses/s26/development/forum/threads/new');

        cy.get('#title').type('Blocked student test title');
        cy.get('#reply_box_1').type('This post should not be created.');
        cy.get('#categories-pick-list .cat-buttons').first().click();
        cy.get('[data-testid="forum-publish-thread"]').click();

        cy.contains('You are currently blocked from making forum posts.').should('be.visible');

        cy.logout();
    });
});
