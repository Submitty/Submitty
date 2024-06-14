const title1 = 'Cypress Title 1 Cypress';
const title2 = 'Cypress Title 2 Cypress';
const title3 = 'Cypress Title 3 Cypress';
const title4 = 'Python Tutorials';
const content1 = 'Cypress Content 1 Cypress';
const content2 = 'Cypress Content 2 Cypress';
const content3 = 'Cypress Content 3 Cypress';
const reply1 = 'Cypress Reply 1 Cypress';
const reply2 = 'Cypress Reply 2 Cypress';
const reply3 = 'Cypress Reply 3 Cypress';
const merged1 = 'Merged Thread Title: '.concat(title3, '\n\n', content3);
const merged2 = 'Merged Thread Title: '.concat(title2, '\n\n', content2);
const attachment1 = 'sea_animals.png';

const createThread = (title, content, category) => {
    // Add more to tests for uploading attachments
    cy.get('[title="Create Thread"]').click();
    cy.get('#title').type(title);
    cy.get('.thread_post_content').type(content);
    cy.get('.cat-buttons').contains(category).click();
    cy.get('[name="post"]').click();
    cy.get('.flex-row > .thread-left-cont').should('contain', title);
};

const replyToThread = (title, reply) => {
    cy.get('[data-testid="thread-list-item"]').contains(title).click();
    cy.get('[data-testid="create-post-head"]').should('contain', title);
    cy.get('#reply_box_3').type(reply);
    cy.get('[value="Submit Reply to All"]').should('not.be.disabled').click();
    cy.get('#posts_list').should('contain', reply);
};

const upduckPost = (thread_title) => {
    cy.get('[data-testid="thread-list-item"]').contains(thread_title).click();
    cy.get('[data-testid="create-post-head"]').should('contain', thread_title);
    cy.get('[data-testid="like-count"]').first().should('have.text', 0);
    cy.get('[data-testid="upduck-button"]').first().click();
    cy.get('[data-testid="like-count"]').first().should('have.text', 1);
};

const checkStatsUpducks = (fullName, numUpducks) => {
    // Check the stats page for a user with fullName and
    // number of upducks numUpducks
    cy.get('[data-testid="more-dropdown"]').click();
    cy.get('#forum_stats').click();
    cy.get('[data-testid="user-stat"]').contains(fullName).siblings('[data-testid="upduck-stat"]').should('contain.text', numUpducks);
    cy.get('[title="Back to threads"]').click();
};

const mergeThreads = (fromThread, toThread, mergedContent) => {
    // Add more to tests for uploading attachments
    cy.get('[data-testid="thread-list-item"]').contains(fromThread).click({ force: true });
    cy.get('[title="Merge Thread Into Another Thread"]').click();
    cy.get('.chosen-single > span').click();
    cy.wait(500);
    cy.get('.active-result').contains(toThread).click({ force: true });
    cy.get('[value="Merge Thread"]').click({ force: true });
    cy.get('.pre-forum > .post_content').should('contain', mergedContent);
};

const removeThread = (title) => {
    cy.get('[data-testid="thread-list-item"]').contains(title).click();
    cy.get('.first_post > .post-action-container > .delete-post-button').click();
    cy.get('[data-testid="thread-list-item"]').contains(title).should('not.exist');
};

const uploadAttachmentAndDelete = (title) => {
    cy.get('[data-testid="thread-list-item"]').contains(title).click();
    cy.get('[data-testid="create-post-head"]').should('contain', title);
    cy.get('[data-testid="edit-post-button"]').first().click();
    cy.get('[data-testid="input-file1"]').selectFile('cypress/fixtures/sea_animals.png');
    cy.get('[data-testid="file-upload-table-1"]').should('contain', 'sea_animals.png');
    cy.get('[data-testid="forum-update-post"]').contains('Update Post').click();
    cy.get('[data-testid="edit-post-button"]').first().click();
    cy.get('[data-testid="mark-for-delete-btn"]').should('contain', 'Delete').click();
    cy.get('[data-testid="mark-for-delete-btn"]').should('contain', 'Keep');
    cy.get('[data-testid="forum-update-post"]').contains('Update Post').click();
};
const replyDisabled = (title, attachment) => {
    cy.get('[data-testid="thread-list-item"]').contains(title).click();
    // Reply button should be disabled by default with no text
    cy.get('[value="Submit Reply to All"]').should('be.disabled');

    // Ensure reply button is not disabled when attachments are added
    cy.get('#input-file3').attachFile(attachment);
    cy.get('[value="Submit Reply to All"]').should('not.be.disabled').click();

    // Wait for submission and ensure attachment with no text is visible
    cy.get('.attachment-btn').click();
    cy.contains('p', attachment).should('be.visible');
};

describe('Test cases revolving around creating, replying to, merging, and removing discussion forum threads', () => {

    beforeEach(() => {
        cy.login('instructor');
        cy.visit(['sample']);
        cy.get('#nav-sidebar-forum').click();
        cy.get('#nav-sidebar-collapse-sidebar').click();
    });

    it('Reply button is disabled when applicable and thread reply can contain an attachment', () => {
        createThread(title1, title1, 'Comment');
        replyDisabled(title1, attachment1);
        removeThread(title1);
    });

    it('Create, reply to, merge, and delete threads', () => {
        // Add and Delete Image Attachment
        uploadAttachmentAndDelete(title4);
        // Comment
        createThread(title1, content1, 'Comment');
        // Question
        createThread(title2, content2, 'Question');
        // Tutorials
        createThread(title3, content3, 'Tutorials');

        // Comment
        replyToThread(title1, reply1);
        // Question
        replyToThread(title2, reply2);
        // Tutorial
        replyToThread(title3, reply3);

        // Upduck the first post of each thread
        upduckPost(title1);
        upduckPost(title2);
        upduckPost(title3);

        // Check the stats page for instructor with 3 upducks
        checkStatsUpducks('Instructor, Quinn', 3);

        // Tutorial into Questions
        mergeThreads(title3, title2, merged1);

        // Resulting thread into comment
        mergeThreads(title2, title1, merged2);

        // Remove threads
        removeThread(title1);
    });
});
