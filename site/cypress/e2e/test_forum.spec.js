const title1 = 'E2E Test 1 E2E';
const content1 = 'E2E Content 1 E2E';
const title2 = 'E2E Test 2 E2E';
const content2 = 'E2E Content 2 E2E';
const title3 = 'E2E Test 3 E2E';
const content3 = 'E2E Content 3 E2E';
const reply1 = 'E2E Reply 1 E2E';
const reply2 = 'E2E Reply 2 E2E';
const reply3 = 'E2E Reply 3 E2E';


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

        it('Create a comment thread', () => {
            cy.login(user);
            cy.visit(['sample', 'forum']);
            cy.get('[title="Create Thread"]').click();
            cy.get('#title').type(title1);
            cy.get('.thread_post_content').type(content1);
            // Add more to tests for uploading attachments

            cy.get('.cat-buttons').contains('Comment ').click();
            cy.get('[name="post"]').click();
        });

        it('Create a question thread', () => {
            cy.login(user);
            cy.visit(['sample']);
            cy.get('#nav-sidebar-forum').click();
            cy.get('[title="Create Thread"]').click();
            cy.get('#title').type(title2);
            cy.get('.thread_post_content').type(content2);
            // Add more to tests for uploading attachments
            cy.get('.cat-buttons').contains('Question ').click();
            cy.get('[name="post"]').click();
        });

        it('Create a tutorial thread', () => {
            cy.login(user);
            cy.visit(['sample']);
            cy.get('#nav-sidebar-forum').click();
            cy.get('[title="Create Thread"]').click();
            cy.get('#title').type(title3);
            cy.get('.thread_post_content').type(content3);
            // Add more to tests for uploading attachments
            cy.get('.cat-buttons').contains('Tutorials ').click();
            cy.get('[name="post"]').click();
        });

        it('Reply to comment thread', () => {
            // Add more to tests for uploading attachments
            cy.login(user);
            cy.visit(['sample']);
            cy.get('#nav-sidebar-forum').click();
            cy.get('#thread_box_link_9 > .thread_box').click();
            cy.get('#reply_box_2').type(reply1);
            cy.get('[value="Submit Reply to All"]').click();
        });

        it('Reply to question thread', () => {
            // Add more to tests for uploading attachments
            cy.login(user);
            cy.visit(['sample']);
            cy.get('#nav-sidebar-forum').click();
            cy.get('#thread_box_link_10 > .thread_box').click();
            cy.get('#reply_box_2').type(reply2);
            cy.get('[value="Submit Reply to All"]').click();
        });

        it('Reply to tutorial thread', () => {
            // Add more to tests for uploading attachments
            cy.login(user);
            cy.visit(['sample']);
            cy.get('#nav-sidebar-forum').click();
            cy.get('#thread_box_link_11 > .thread_box').click();
            cy.get('#reply_box_2').type(reply1);
            cy.get('[value="Submit Reply to All"]').click();
        });

        it('Merge comment and question threads', () => {
            // Add more to tests for uploading attachments
            cy.login(user);
            cy.visit(['sample']);
            cy.get('#nav-sidebar-forum').click();
            cy.get('#thread_box_link_9 > .thread_box').click();
            cy.get('[title="Merge Thread Into Another Thread"]').click();
        });
    });
});
