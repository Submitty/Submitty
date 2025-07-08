import { buildUrl, verifyWebSocketFunctionality } from '../../support/utils';

const title1 = 'Cypress Title 1 Cypress';
const title2 = 'Cypress Title 2 Cypress';
const title3 = 'Cypress Title 3 Cypress';
const title4 = 'Python Tutorials';
const title5 = 'WebSocket Title Test';
const content1 = 'Cypress Content 1 Cypress';
const content2 = 'Cypress Content 2 Cypress';
const content3 = 'Cypress Content 3 Cypress';
const content4 = 'Cypress Content 4 Cypress';
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
    cy.get('.flex-row > .thread-title-cont').should('contain', title);
};

const replyToThread = (title, reply) => {
    cy.get('[data-testid="thread-list-item"]').contains(title).click();
    cy.get('[data-testid="create-post-head"]').should('contain', title);
    cy.get('#reply_box_3').type(reply);
    cy.get('[data-testid="forum-submit-reply-all"]').should('not.be.disabled').click();
    cy.get('#posts_list').should('contain', reply);
};

const mergeThreads = (fromThread, toThread, mergedContent) => {
    // Add more to tests for uploading attachments
    cy.get('[data-testid="thread-list-item"]').contains(fromThread).click();
    // eslint-disable-next-line cypress/no-unnecessary-waiting
    cy.wait(1000);
    cy.get('[title="Merge Thread Into Another Thread"]').click();
    // eslint-disable-next-line cypress/no-unnecessary-waiting
    cy.wait(1000);
    cy.get('.chosen-single > span').click();
    // eslint-disable-next-line cypress/no-unnecessary-waiting
    cy.wait(1000);
    cy.get('.active-result').contains(toThread).click();
    // eslint-disable-next-line cypress/no-unnecessary-waiting
    cy.wait(1000);
    cy.get('[value="Merge Thread"]').click();
    cy.get('.pre-forum > .post_content').should('contain', mergedContent);
};

// Checks if a thread with the specified title exists
const threadExists = (title) => {
    return cy.get('[data-testid="thread-list-item"]').then(($thread_items) => {
        return $thread_items.filter(`:contains(${title})`).length > 0;
    });
};

// Removes all threads matching the specified title
const removeThread = (title) => {
    cy.reload();
    threadExists(title).then((exists) => {
        if (exists) {
            cy.get('[data-testid="thread-list-item"]').contains(title).click();
            cy.get('[data-testid="thread-dropdown"]').first().click();
            cy.get('[data-testid="delete-post-button"]').first().click({ force: true });
            removeThread(title);
        }
    });
};

const uploadAttachmentAndDelete = (title, attachment) => {
    cy.get('[data-testid="thread-list-item"]').contains(title).click();
    cy.get('[data-testid="create-post-head"]').should('contain', title);
    cy.get('[data-testid="thread-dropdown"]').first().click();
    cy.get('[data-testid="edit-post-button"]').first().click();
    cy.get('[data-testid="input-file1"]').selectFile(`cypress/fixtures/${attachment}`);
    cy.get('[data-testid="file-upload-table-1"]').should('contain', attachment);
    cy.get('[data-testid="forum-update-post"]').contains('Update Post').click();
    cy.get('[data-testid="thread-dropdown"]').first().click();
    cy.get('[data-testid="edit-post-button"]').first().click();
    cy.get('[data-testid="mark-for-delete-btn"]').first().should('contain', 'Delete').click();
    cy.get('[data-testid="mark-for-delete-btn"]').first().should('contain', 'Keep');
    cy.get('[data-testid="forum-update-post"]').contains('Update Post').click();
};

const replyDisabled = (title, attachment) => {
    cy.get('[data-testid="thread-list-item"]').contains(title).click();
    // Reply button should be disabled by default with no text
    cy.get('[data-testid="forum-submit-reply-all"]').should('be.disabled');

    // Ensure reply button is not disabled when attachments are added
    // waits here are needed to avoid a reload that would clear out the upload
    // eslint-disable-next-line cypress/no-unnecessary-waiting
    cy.wait(750);
    cy.get('[data-testid="input-file3"]').selectFile(`cypress/fixtures/${attachment}`);
    cy.get('[data-testid="forum-submit-reply-all"]').should('not.be.disabled').click();

    // Wait for submission and ensure attachment with no text is visible
    cy.get('.attachment-btn').click();
    cy.contains('p', attachment).should('be.visible');
};

const setLockDateToPast = (title) => {
    cy.get('[data-testid="thread-list-item"]').contains(title).click();
    cy.get('[data-testid="thread-dropdown"]').first().click();
    cy.get('[data-testid="edit-post-button"]').first().click();
    cy.get('#lock_thread_date').clear();
    cy.get('#lock_thread_date').type('2023-01-01 00:00:00');
    cy.get('[data-testid="forum-update-post"]').contains('Update Post').click();
};

const clearLockDate = (title) => {
    cy.get('[data-testid="thread-list-item"]').contains(title).click();
    cy.get('[data-testid="thread-dropdown"]').first().click();
    cy.get('[data-testid="edit-post-button"]').first().click();
    cy.get('#lock_thread_date').clear();
    cy.get('[data-testid="forum-update-post"]').contains('Update Post').click();
};

describe('Forum Thread Lock Date Functionality', () => {
    beforeEach(() => {
        cy.login('instructor');
        cy.visit(['sample', 'forum']);
        cy.get('#nav-sidebar-collapse-sidebar').click();
    });

    it('Should verify WebSocket functionality', () => {
        // Visit the forum thread page
        cy.visit(['sample', 'forum']);

        // Create a new thread via a POST request
        const body = {
            'title': title5,
            'markdown_status': 0,
            'lock_thread_date': '',
            'thread_post_content': content4,
            'cat[]': '2', //  TODO: to prevent flaky tests, fetch all categories to not map to magic numbers ["Question" == 2]
            'expirationDate': new Date(Date.now() + 1000 * 60 * 60 * 24 * 7).toISOString(), // 1 week from now
            'thread_status': -1,
        };

        removeThread(title5);

        verifyWebSocketFunctionality(buildUrl(['sample', 'forum', 'threads', 'new'], true), 'POST', 'multipart/form-data', body, (response) => {
            expect(response.status).to.eq(200);

            // Verify the thread is created
            cy.get(`[data-thread_title*="${title5}"]`) // Thread container
                .should('exist')
                .within(() => {
                    cy.get('[data-testid="thread-list-item"]') // Verify the thread title
                        .should('contain', title5);
                    cy.get('.thread-content') // Verify the thread content
                        .should('contain', content4);
                    cy.get('.label_forum') // Verify the thread category
                        .should('contain', 'Question');
                });

            cy.get(`[data-thread_title*="${title5}"]`)
                .should('exist')
                .invoke('attr', 'data-thread_id')
                .then((val) => {
                    const id = Number(val); // Parse the inserted thread ID
                    expect(id).to.be.a('number');
                    expect(id).to.be.greaterThan(0);

                    cy.get(`[data-thread_title*="${title5}"]`) // Verify we can visit the new thread
                        .should('exist')
                        .click();
                    cy.url().should('include', buildUrl(['sample', 'forum', 'threads', id], true));
                });

            removeThread(title5);
            cy.get('[data-testid="thread-list-item"]').contains(title5).should('not.exist');
        });
    });

    it('Should prevent students from replying when lock date is in the past and allow replying when lock date is cleared', () => {
        return;
        createThread(title1, content1, 'Comment');
        setLockDateToPast(title1);
        cy.login('student');
        cy.visit(['sample', 'forum']);
        cy.get('[data-testid="thread-list-item"]').contains(title1).click();
        cy.get('[data-testid="forum-submit-reply-all"]').should('be.disabled');

        cy.login('instructor');
        cy.visit(['sample', 'forum']);
        clearLockDate(title1);

        cy.login('student');
        cy.visit(['sample', 'forum']);
        replyToThread(title1, reply1);

        removeThread(title1);
    });
});

describe('Should test creating, replying, merging, removing, and upducks in forum', () => {
    beforeEach(() => {
        return;
        cy.login('instructor');
        cy.visit(['sample', 'forum']);
        cy.get('#nav-sidebar-collapse-sidebar').click();
        removeThread(title1);
        removeThread(title2);
        removeThread(title3);
    });

    it('Remove threads removes all threads with the same title', () => {
        return;
        createThread(title1, content1, 'Comment');
        createThread(title1, content1, 'Comment');
        removeThread(title1);
        cy.get('[data-testid="thread-list-item"]').contains(title1).should('not.exist');
    });

    it('Reply button is disabled when applicable and thread reply can contain an attachment', () => {
        return;
        createThread(title1, title1, 'Comment');
        replyDisabled(title1, attachment1);
        removeThread(title1);
    });

    it('Form content is not cleared while submitting with empty description', () => {
        return;
        cy.get('[data-testid="Create Thread"]').click();
        cy.get('[data-testid="title"]').type(title1);
        cy.get('[data-testid="categories-pick-list"]').contains('Comment').click();
        cy.get('[name="post"]').click();

        // Check if the title is still there
        cy.get('[data-testid="title"]').should('have.value', title1);

        // clear form title and de-select category
        cy.get('[data-testid="title"]').clear();
        cy.get('[data-testid="categories-pick-list"]').contains('Comment').click();
    });

    it('Create, reply to, merge, and delete threads', () => {
        return;
        // Add and Delete Image Attachment
        uploadAttachmentAndDelete(title4, attachment1);
        createThread(title1, content1, 'Comment');
        createThread(title2, content2, 'Question');
        createThread(title3, content3, 'Tutorials');

        replyToThread(title1, reply1);
        replyToThread(title2, reply2);
        replyToThread(title3, reply3);

        // Tutorial into Questions
        mergeThreads(title3, title2, merged1);

        // Resulting thread into comment
        mergeThreads(title2, title1, merged2);
        cy.get('[data-testid="thread-list-item"]').contains(title2).should('not.exist');
        cy.get('[data-testid="thread-list-item"]').contains(title3).should('not.exist');
        cy.get('[data-testid="thread-list-item"]').contains(title1).should('exist');
        removeThread(title1);
    });
});
