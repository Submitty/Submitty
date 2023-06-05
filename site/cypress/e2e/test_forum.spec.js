const title1 = 'E2E Test 1 E2E';
const title2 = 'E2E Test 2 E2E';
const title3 = 'E2E Test 3 E2E';
const content1 = 'E2E Content 1 E2E';
const content2 = 'E2E Content 2 E2E';
const content3 = 'E2E Content 3 E2E';
const reply1 = 'E2E Reply 1 E2E';
const reply2 = 'E2E Reply 2 E2E';
const reply3 = 'E2E Reply 3 E2E';
const merged1 = 'Merged Thread Title: '.concat(title3,'\n\n',content3);
const merged2 = 'Merged Thread Title: '.concat(title2,'\n\n',content2);

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
            cy.get('#thread_box_link_9 > .thread_box > .flex-row > .thread-left-cont > .thread-content').should('have.text', content1);

            // Question
            cy.get('[title="Create Thread"]').click();
            cy.get('#title').type(title2);
            cy.get('.thread_post_content').type(content2);
            // Add more to tests for uploading attachments
            cy.get('.cat-buttons').contains('Question ').click();
            cy.get('[name="post"]').click();
            cy.get('#thread_box_link_10 > .thread_box > .flex-row > .thread-left-cont > .thread-content').should('have.text', content2);

            // Tutorials
            cy.get('[title="Create Thread"]').click();
            cy.get('#title').type(title3);
            cy.get('.thread_post_content').type(content3);
            // Add more to tests for uploading attachments
            cy.get('.cat-buttons').contains('Tutorials ').click();
            cy.get('[name="post"]').click();
            cy.get('#thread_box_link_11 > .thread_box > .flex-row > .thread-left-cont > .thread-content').should('have.text', content3);
        });

        it('Reply to threads', () => {
            // Add more to tests for uploading attachments
            cy.login(user);
            cy.visit(['sample']);
            // Comment
            cy.get('#nav-sidebar-forum').click();
            cy.get('#thread_box_link_9 > .thread_box').click();
            cy.get('#reply_box_2').type(reply1);
            cy.get('[value="Submit Reply to All"]').click();
            // Question
            cy.get('#nav-sidebar-forum').click();
            cy.get('#thread_box_link_10 > .thread_box').click();
            cy.get('#reply_box_2').type(reply2);
            cy.get('[value="Submit Reply to All"]').click();
            // Tutorials
            cy.get('#nav-sidebar-forum').click();
            cy.get('#thread_box_link_11 > .thread_box').click();
            cy.get('#reply_box_2').type(reply3);
            cy.get('[value="Submit Reply to All"]').click();
        });

        it('Merge threads', () => {
            // Add more to tests for uploading attachments
            cy.login(user);
            cy.visit(['sample']);
            cy.get('#nav-sidebar-forum').click();
            // Tutorial into Questions
            cy.get('#thread_box_link_10 > .thread_box').click();
            cy.get('[title="Merge Thread Into Another Thread"]').click();
            cy.get('.chosen-single > span').click();
            cy.get('.active-result').contains(title2).click();
            cy.get('[value="Merge Thread"]').click();
            cy.get('[id="35"] > .pre-forum > .post_content').should('have.text', merged1 );
            // Result into comments
            cy.get('#thread_box_link_9 > .thread_box').click();
            cy.get('[title="Merge Thread Into Another Thread"]').click();
            cy.get('.chosen-single > span').click();
            cy.get('.active-result').contains(title1).click();
            cy.get('[value="Merge Thread"]').click();
            cy.get('[id="34"] > .pre-forum > .post_content').should('have.text', merged2);
        });

        it('Remove thread', () => {
        //     // Add more to tests for uploading attachments
            cy.login(user);
            cy.visit(['sample']);
            cy.get('#nav-sidebar-forum').click();
            cy.get('#thread_box_link_9 > .thread_box').click();
            cy.get('[title="Remove post"]').click();
            cy.get('#thread_box_link_9').should('not.exist');
        });

    });
});
