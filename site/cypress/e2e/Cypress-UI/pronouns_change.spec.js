describe('Tests cases abut changing user pronouns', () => {
    // Set the stage by saving old pronouns and setting a desired pronoun to look for
    before(() => {
        cy.visit('/user_profile');
        cy.login('student');

        // type in new pronouns and check display in forum option
        cy.get('[data-testid="pronouns-val"]').click();
        cy.get('[data-testid="clear-pronoun-input"]').click();
        cy.get('[data-testid="pronouns-input"]').type('They/Them');
        cy.get('[data-testid="pronouns-forum-display"]').check();
        cy.get('[data-testid="edit-pronouns-submit"]').click();

        // ensure pronouns and display option changed on page
        cy.get('[data-testid="pronouns-val"]').should('contain', 'They/Them');
        cy.get('[data-testid="display-pronouns-val"]').should('contain', 'True');

        cy.logout();
    });

    // restore pronouns to previous value at the end
    after(() => {
        cy.visit('/user_profile');
        cy.login('student');

        // change back to old pronouns
        cy.get('[data-testid="pronouns-val"]').click();

        // set to default
        cy.get('[data-testid="clear-pronoun-input"]').click();

        // set to false
        cy.get('[data-testid="pronouns-forum-display"]').uncheck();
        cy.get('[data-testid="edit-pronouns-submit"]').click();

        // ensure pronouns and display option changed on page
        cy.get('[data-testid="pronouns-val"]').should('contain', ' ');
        cy.get('[data-testid="display-pronouns-val"]').should('contain', 'False');
    });

    it('Verifies changed pronouns as instructor in Manage Students', () => {
        cy.visit(['sample', 'users']);
        cy.login('instructor');

        // ensure pronouns column is on
        cy.get('[data-testid="toggle-columns"]').click(); // open toggle columns form
        cy.get('[data-testid="toggle-pronouns"]').check();
        cy.get('[data-testid="toggle-student-col-submit"]').first().click();

        // Ensure correctness in table
        cy.get('.td-pronouns:eq( 12 )').should('have.text', 'They/Them');
    });

    it('Verifies changed pronouns as instructor in Student Photos', () => {
        cy.visit(['sample', 'student_photos']);
        cy.login('instructor');

        // Select text from photo area and parse to get pronoun
        cy.get('.student-image-container > .name').first().should('contain', 'They/Them');
    });

    it('Verifies pronoun is displayed in discussion forum', () => {
        cy.visit(['sample', 'forum']);
        cy.login('student');

        // create thread
        cy.get('[data-testid="Create Thread"]').click();
        cy.get('[data-testid="title"]').type('Test pronouns display');
        cy.get('[data-testid="reply_box_1"]').type('My pronouns is');
        cy.get('.cat-buttons').contains('Question').click();
        // wait for the current state to be saved to local storage before submitting
        cy.wait(3000);
        cy.get('[data-testid="forum-publish-thread"]').click();
        cy.get('[data-testid="create-post-head"]').should('contain', 'Test pronouns display');
        cy.get('[data-testid="post-user-pronouns"]').should('contain', 'They/Them');

        // verify pronouns is shown in overall forum page
        cy.visit(['sample', 'forum']);
        cy.get('[data-testid="post-action-container"]').first().should('contain', 'Joe S.');
        cy.get('[data-testid="post-action-container"]').first().should('contain', 'They/Them');
        cy.get('[data-testid="post-action-container"]').first().click();

        // comment on the thread, verify pronouns is shown
        cy.get('[data-testid="create-post-head"]').should('contain', 'Test pronouns display');
        cy.get('[data-testid="reply_box_3"]').type('my pronouns are They/Them');
        cy.get('[data-testid="forum-submit-reply-all"]').click();
        cy.get('.post_box').should('contain', 'my pronouns are They/Them');
        cy.get('[data-testid="post-user-pronouns"]').should('contain', 'They/Them');

        // create thread anonymously
        cy.get('[data-testid="Create Thread"]').click();
        cy.get('[data-testid="title"]').type('Test Anonymous thread, should not show pronouns');
        cy.get('[data-testid="reply_box_1"]').type('My pronouns is');
        cy.get('.cat-buttons').contains('Question').click();
        cy.get('[data-testid="thread-anon-checkbox"]').click();
        cy.get('[data-testid="forum-publish-thread"]').click();
        cy.get('[data-testid="create-post-head"]').should('contain', 'Test Anonymous thread, should not show pronouns');
        cy.get('[data-testid="post-user-id"]').should('contain', 'Anonymous');
        cy.get('[data-testid="post-user-pronouns"]').should('not.exist');

        // verify pronouns is not shown in overall forum page
        cy.visit(['sample', 'forum']);
        cy.get('[data-testid="post-action-container"]').first().should('contain', 'Anonymous');
        cy.get('[data-testid="post-action-container"]').first().find('[data-testid="post-user-pronouns"]').should('not.exist');
        cy.get('[data-testid="post-action-container"]').first().click();

        // comment on the thread anonymously, verify pronouns is not shown
        cy.get('[data-testid="create-post-head"]').should('contain', 'Test Anonymous thread, should not show pronouns');
        cy.get('[data-testid="thread-anon-checkbox"]').eq(2).click();
        cy.get('#reply_box_3').type('I can not see your pronouns');
        cy.get('[data-testid="forum-submit-reply-all"]').click();
        cy.get('.post_box').should('contain', 'I can not see your pronouns');
        cy.get('[data-testid="post-user-id"]').eq(1).should('contain', 'Anonymous');
        cy.get('[data-testid="post-user-pronouns"]').should('not.exist');

        // login as instructor, verify if pronouns is displayed
        cy.logout();
        cy.visit(['sample', 'forum']);
        cy.login('instructor');

        // verify pronouns exist and remove thread
        cy.get('[data-testid="post-action-container"]').eq(1).should('contain', 'Joe S.');
        cy.get('[data-testid="post-action-container"]').eq(1).should('contain', 'They/Them');
        cy.get('[data-testid="post-action-container"]').eq(1).click();
        cy.get('[data-testid="post-user-pronouns"]').should('contain', 'They/Them');
        cy.get('.first_post > .post-action-container > .dropdown-menu ').find(':contains("Delete")').click({ force: true });
        cy.get('[data-testid="flex-row"]').contains('Test pronouns display').should('not.exist');

        // verify pronouns do not exist (post thread Anonymously) and remove thread
        cy.get('[data-testid="post-action-container"]').eq(0).should('contain', 'Anonymous');
        cy.get('[data-testid="post-action-container"]').eq(0).find('[data-testid="post_user_pornouns"]').should('not.exist');
        cy.get('[data-testid="post-action-container"]').eq(0).click();
        cy.get('[data-testid="post-user-id"]').should('contain', 'Anonymous');
        cy.get('[data-testid="post-user-pronouns"]').should('not.exist');
        cy.get('.first_post > .post-action-container > .dropdown-menu ').find(':contains("Delete")').click({ force: true });
        cy.get('[data-testid="flex-row"]').contains('Test Anonymous thread, should not show pronouns').should('not.exist');
    });
});
