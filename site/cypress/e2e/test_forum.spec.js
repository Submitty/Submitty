const title1 = 'Cypress Title 1 Cypress';
const title2 = 'Cypress Title 2 Cypress';
const title3 = 'Cypress Title 3 Cypress';
const content1 = 'Cypress Content 1 Cypress';
const content2 = 'Cypress Content 2 Cypress';
const content3 = 'Cypress Content 3 Cypress';
const reply1 = 'Cypress Reply 1 Cypress';
const reply2 = 'Cypress Reply 2 Cypress';
const reply3 = 'Cypress Reply 3 Cypress';
const merged1 = 'Merged Thread Title: '.concat(title3, '\n\n', content3);
const merged2 = 'Merged Thread Title: '.concat(title2, '\n\n', content2);

describe('Test cases revolving around initializating, modifying, and merging discussion forum threads', () => {

    ['instructor'].forEach((user) => {
        beforeEach(() => {
            cy.visit('/');
        });

        it('Make sure discussion forum is enabled', () => {
            cy.login(user);
            cy.visit(['sample']);
            cy.get('#nav-sidebar-course-settings').click();
            cy.get('#forum-enabled').check();
        });

        it('Create threads', () => {
            cy.login(user);
            cy.visit(['sample', 'forum']);
            // Comment
            cy.get('[title="Create Thread"]').click();
            cy.get('#title').type(title1);
            cy.get('.thread_post_content').type(content1);
            // Add more to tests for uploading attachments
            cy.get('.cat-buttons').contains('Comment ').click();
            cy.get('[name="post"]').click();
            cy.get('.thread_box > .flex-row > .thread-left-cont').should('contain', title1);
            // Question
            cy.get('[title="Create Thread"]').click();
            cy.get('#title').type(title2);
            cy.get('.thread_post_content').type(content2);
            // Add more to tests for uploading attachments
            cy.get('.cat-buttons').contains('Question ').click();
            cy.get('[name="post"]').click();
            cy.get('.thread_box > .flex-row > .thread-left-cont').should('contain', title2);

            // Tutorials
            cy.get('[title="Create Thread"]').click();
            cy.get('#title').type(title3);
            cy.get('.thread_post_content').type(content3);
            // Add more to tests for uploading attachments
            cy.get('.cat-buttons').contains('Tutorials ').click();
            cy.get('[name="post"]').click();
            cy.get('.thread_box > .flex-row > .thread-left-cont').should('contain', content3);
        });

        it('Reply to comment thread', () => {
            // Add more to tests for uploading attachments
            cy.login(user);
            cy.visit(['sample']);
            // Comment
            cy.get('#nav-sidebar-forum').click();
            cy.get('.thread_box > .flex-row > .thread-left-cont > .thread-list-item').contains(title1).click();
            cy.get('#reply_box_2').type(reply1);
            cy.get('[value="Submit Reply to All"]').click();
        });
        it('Reply to question thread', () => {
            // Add more to tests for uploading attachments
            cy.login(user);
            cy.visit(['sample']);
            // Question
            cy.get('#nav-sidebar-forum').click();
            cy.get('.thread_box > .flex-row > .thread-left-cont > .thread-list-item').contains(title2).click();
            cy.get('#reply_box_2').type(reply2);
            cy.get('[value="Submit Reply to All"]').click();
        });
        it('Reply to tutorial thread', () => {
            // Add more to tests for uploading attachments
            cy.login(user);
            cy.visit(['sample']);
            // Tutorials
            cy.get('#nav-sidebar-forum').click();
            cy.get('.thread_box > .flex-row > .thread-left-cont > .thread-list-item').contains(title3).click();
            cy.get('#reply_box_2').type(reply3);
            cy.get('[value="Submit Reply to All"]').click();
        });

        it('Merge tutorial thread into question thread', () => {
            // Add more to tests for uploading attachments
            cy.login(user);
            cy.visit(['sample']);
            cy.get('#nav-sidebar-forum').click();
            // Tutorial into Questions
            cy.get('.thread_box > .flex-row > .thread-left-cont > .thread-list-item').contains(title3).click();
            cy.get('[title="Merge Thread Into Another Thread"]').click();
            cy.get('.chosen-single > span').click();
            cy.get('.active-result').contains(title2).click();
            cy.get('[value="Merge Thread"]').click();
            cy.get('.pre-forum > .post_content').should('contain', merged1);
        });
        it('Merge resulting thread into comment thread', () => {
            cy.login(user);
            cy.visit(['sample']);
            cy.get('#nav-sidebar-forum').click();
            // Result into comments
            cy.get('.thread_box > .flex-row > .thread-left-cont > .thread-list-item').contains(title2).click();
            cy.get('[title="Merge Thread Into Another Thread"]').click();
            cy.get('.chosen-single > span').click();
            cy.get('.active-result').contains(title1).click();
            cy.get('[value="Merge Thread"]').click();
            cy.get('.pre-forum > .post_content').should('contain', merged2);
        });

        it('Remove thread', () => {
            // Add more to tests for uploading attachments
            cy.login(user);
            cy.visit(['sample']);
            cy.get('#nav-sidebar-forum').click();
            cy.get('.thread_box > .flex-row > .thread-left-cont > .thread-list-item').contains(title1).click();
            cy.get('.first_post > .post-action-container > .delete-post-button').click();
            cy.get('.thread_box > .flex-row > .thread-left-cont > .thread-list-item').contains(title1).should('not.exist');
        });

    });
});
