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

Cypress.Commands.add('createThread', (title, content, category) => {
    // Add more to tests for uploading attachments
    cy.get('[title="Create Thread"]').click();
    cy.get('#title').type(title);
    cy.get('.thread_post_content').type(content);
    cy.get('.cat-buttons').contains(category).click();
    cy.get('[name="post"]').click();
    cy.get('.flex-row > .thread-left-cont').should('contain', title);
});
Cypress.Commands.add('replyToThread', (title, reply) => {
    cy.get('.thread-left-cont > .thread-list-item').contains(title).click();
    cy.get('.create-post-head').should('contain', title);
    cy.get('#reply_box_2').type(reply);
    cy.get('[value="Submit Reply to All"]').click();
    cy.get('#posts_list').should('contain', reply);
});
Cypress.Commands.add('mergeThreads', (fromThread, toThread, mergedContent) => {
    // Add more to tests for uploading attachments
    cy.get('.thread-left-cont > .thread-list-item').contains(fromThread).click({ force: true });
    cy.get('[title="Merge Thread Into Another Thread"]').click();
    cy.get('.chosen-single > span').click();
    cy.wait(500);
    cy.get('.active-result').contains(toThread).click({ force: true });
    cy.get('[value="Merge Thread"]').click({ force: true });
    cy.get('.pre-forum > .post_content').should('contain', mergedContent);
});
Cypress.Commands.add('removeThread', (title) => {
    cy.get('.thread-left-cont > .thread-list-item').contains(title).click();
    cy.get('.first_post > .post-action-container > .delete-post-button').click();
    cy.get('.thread-left-cont > .thread-list-item').contains(title).should('not.exist');
});

describe('Test cases revolving around creating, replying to, merging, and removing discussion forum threads', () => {

    beforeEach(() => {
        cy.visit('/');
        cy.login('instructor');
        cy.visit(['sample']);
        cy.get('#nav-sidebar-forum').click();
        cy.get('#nav-sidebar-collapse-sidebar').click();
    });

    it('Create, reply to, merge, and delete threads', () => {
        // Comment
        cy.createThread(title1, content1, 'Comment');
        // Question
        cy.createThread(title2, content2, 'Question');
        // Tutorials
        cy.createThread(title3, content3, 'Tutorials');

        // Comment
        cy.replyToThread(title1, reply1);
        // Question
        cy.replyToThread(title2, reply2);
        // Tutorial
        cy.replyToThread(title3, reply3);

        // Tutorial into Questions
        cy.mergeThreads(title3, title2, merged1);

        // Resulting thread into comment
        cy.mergeThreads(title2, title1, merged2);

        // Remove threads
        cy.removeThread(title1);
    });
});
