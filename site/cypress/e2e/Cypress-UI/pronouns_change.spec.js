
describe('Tests cases abut changing user pronouns', () => {
    //Set the stage by saving old pronouns and setting a desired pronoun to look for
    before(() => {
        cy.visit('/user_profile');
        cy.login('student');

        //open pronouns form
        cy.get('#pronouns_val').click();
        cy.get('#user-pronouns-change').as('e');

        //type in new pronouns and check display in forum option
        cy.get('button[aria-label="Clear pronoun input"]').click(); //clear input using trash can
        cy.get('@e').type('They/Them');
        cy.get('#pronouns-forum-display').check();
        cy.get('#edit-pronouns-submit').click();

        //ensure pronouns and display option changed on page
        cy.get('#pronouns_val').contains('They/Them');
        cy.get('#display_pronouns_val').contains('True');

        cy.logout();

    });

    //restore pronouns to previous value at the end
    after(() => {
        cy.visit('/user_profile');
        cy.login('student');

        //change back to old pronouns
        cy.get('#pronouns_val').click();

        //set to default
        cy.get('button[aria-label="Clear pronoun input"]').click(); //clear input using trash can

        //set to false
        cy.get('#pronouns-forum-display').uncheck();

        cy.get('#edit-pronouns-submit').click();

        //ensure pronouns and display option changed on page
        cy.get('#pronouns_val').should('have.text', ' ');
        cy.get('#display_pronouns_val').contains('False');
    });

    it('Verifies changed pronouns as instructor in Manage Students', () => {
        cy.visit(['sample','users']);
        cy.login('instructor');

        //ensure pronouns column is on
        cy.get('#toggle-columns').click(); //open toggle columns form
        cy.get('#toggle-pronouns').check();
        cy.get('#toggle-student-col-submit').first().click();

        //Ensure correctness in table
        cy.get('.td-pronouns:eq( 12 )').should('have.text', 'They/Them');

    });

    it('Verifies changed pronouns as instructor in Student Photos', () => {
        cy.visit(['sample','student_photos']);
        cy.login('instructor');

        //Select text from photo area and parse to get pronoun
        cy.get('.student-image-container > .name').first().contains('They/Them');

    });

    it('Verifies pronoun is displayed in discussion forum', () => {
        cy.visit(['sample','forum']);
        cy.login('student');

        //create thread
        cy.get('[title="Create Thread"]').click();
        cy.get('#title').type('Test pronouns display');
        cy.get('.thread_post_content').type('My pronouns is');
        cy.get('.cat-buttons').contains('Question').click();
        cy.get('[name="post"]').click();
        cy.get('.create-post-head').should('contain', 'Test pronouns display');
        cy.get('.post_user_pronouns').should('contain', 'They/Them');

        //verify pronouns is shown in overall fourm page
        cy.get('#nav-sidebar-forum').click();
        cy.get('.thread-list-item').should('contain', 'Test pronouns display');
        cy.contains('Test pronouns display').find('.post_user_pronouns').should('contain','They/Them');
        cy.contains('Test pronouns display').find('.post_user_pronouns').click();

        //comment on the thread, verify pronouns is shown
        cy.get('.create-post-head').should('contain', 'Test pronouns display');
        cy.get('#reply_box_2').type('my pronouns is They/Them{ctrl}{enter}');
        cy.contains('Submit Reply to All').click();
        cy.get('.post_box').should('contain', 'my pronouns is They/Them');
        cy.get('.post_user_pronouns').should('contain', 'They/Them');

        //remove thread
        //cy.get('.first_post > .post-action-container > .delete-post-button').click();
        //cy.get('.thread-left-cont > .thread-list-item').contains('Test pronouns display').should('not.exist');

        //create thread anonymously
        cy.get('[title="Create Thread"]').click();
        cy.get('#title').type('Test Anonymous thread, should not show pronouns');
        cy.get('.thread_post_content').type('My pronouns is');
        cy.get('.cat-buttons').contains('Question').click();
        cy.get('.thread-anon-checkbox').click();
        cy.get('[name="post"]').click();
        cy.get('.create-post-head').should('contain', 'Test Anonymous thread, should not show pronouns');
        cy.get('.post_user_id').should('contain', 'Anonymous');
        cy.get('.post_user_pronouns').should('not.exist');

        //verify pronouns is not shown in overall fourm page
        cy.get('#nav-sidebar-forum').click();
        cy.get('.thread-list-item').should('contain', 'Test Anonymous thread, should not show pronouns');
        cy.contains('Test Anonymous thread, should not show pronouns').find('.post_user_id').should('contain','Anonymous');
        cy.contains('Test Anonymous thread, should not show pronouns').find('.post_user_pronouns').should('not.be.exist');
        cy.contains('Test Anonymous thread, should not show pronouns').find('.post_user_id').click();

        //comment on the thread anonymously, verify pronouns is not shown
        cy.get('.create-post-head').should('contain', 'Test Anonymous thread, should not show pronouns');
        cy.get('.thread-anon-checkbox').filter(':visible').click();
        cy.get('#reply_box_2').type('I can not see your pronouns{ctrl}{enter}');
        cy.contains('Submit Reply to All').click();
        cy.get('.post_box').should('contain', 'I can not see your pronouns');
        cy.get('.post_user_id').should('contain', 'Anonymous');
        cy.get('.post_user_pronouns').should('not.exist');

        //login as instructor, verify if pronouns is displayed
        cy.logout();
        cy.visit(['sample','forum']);
        cy.login('instructor');

        //verify pronouns exist and remove thread
        cy.contains('Test pronouns display').find('.post_user_pronouns').should('contain','They/Them');
        cy.contains('Test pronouns display').find('.post_user_pronouns').click();
        cy.get('.post_user_pronouns').should('contain', 'They/Them');
        cy.get('.first_post').find('.fa-trash').click();
        cy.contains('Test pronouns display').should('not.exist');

        //verify pronouns do not exist (post thread Anonymously) and remove thread
        cy.contains('Test Anonymous thread, should not show pronouns').find('.post_user_id').should('contain','Anonymous');
        cy.contains('Test Anonymous thread, should not show pronouns').find('.post_user_pronouns').should('not.be.exist');
        cy.contains('Test Anonymous thread, should not show pronouns').find('.post_user_id').click();
        cy.get('.post_user_id').should('contain', 'Anonymous');
        cy.get('.post_user_pronouns').should('not.exist');
        cy.get('.first_post').find('.fa-trash').click();
        cy.contains('Test Anonymous thread, should not show pronouns').should('not.exist');
        //cy.get('.first_post > .post-action-container > .delete-post-button').click();
        //cy.get('.thread-left-cont > .thread-list-item').contains('Test Anonymous thread, should not show pronouns').should('not.exist');

    });

});
