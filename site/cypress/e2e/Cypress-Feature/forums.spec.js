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
    // eslint-disable-next-line cypress/no-unnecessary-waiting
    cy.wait(1000);
    cy.get('[name="post"]').click();
    cy.get('.flex-row > .thread-left-cont').should('contain', title);
};

const replyToThread = (title, reply) => {
    cy.get('[data-testid="thread-list-item"]').contains(title).click();
    cy.get('[data-testid="create-post-head"]').should('contain', title);
    cy.get('#reply_box_3').type(reply);
    cy.get('[data-testid="forum-submit-reply-all"]').should('not.be.disabled').click();
    cy.get('#posts_list').should('contain', reply);
};

const upduckPost = (thread_title) => {
    cy.get('[data-testid="thread-list-item"]').contains(thread_title).click();
    cy.get('[data-testid="create-post-head"]').should('contain', thread_title);
    cy.get('[data-testid="like-count"]').first().should('have.text', 0);
    cy.get('[data-testid="upduck-button"]').first().click();
    // eslint-disable-next-line cypress/no-unnecessary-waiting
    cy.wait(1000);
    cy.get('[data-testid="like-count"]', { timeout: 10000 }).first().should('have.text', 1);
};

const upduckReply = (thread_title) => {
    // Upduck the first reply
    cy.get('[data-testid="thread-list-item"]').contains(thread_title).click();
    cy.get('[data-testid="create-post-head"]').should('contain', thread_title);
    cy.get('[data-testid="upduck-button"]').eq(1).click();
    // eslint-disable-next-line cypress/no-unnecessary-waiting
    cy.wait(1000);
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
    cy.get('[data-testid="thread-list-item"]').contains(fromThread).click();
    cy.get('[title="Merge Thread Into Another Thread"]').click();
    cy.get('.chosen-single > span').click();
    cy.get('.active-result').contains(toThread).click({ force: true });
    cy.get('[value="Merge Thread"]').click({ force: true });
    cy.get('.pre-forum > .post_content').should('contain', mergedContent);
};

const removeThread = (title) => {
    cy.get('[data-testid="thread-list-item"]').contains(title).click();
    cy.get('[data-testid="thread-dropdown"]').first().click();
    cy.get('[data-testid="delete-post-button"]').first().click();
    cy.get('[data-testid="thread-list-item"]').contains(title).should('not.exist');
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

const removeUpduckPost = (thread_title) => {
    cy.get('[data-testid="create-post-head"]').should('contain', thread_title);
    cy.get('[data-testid="like-count"]').first().should('have.text', 1);
    cy.get('[data-testid="upduck-button"]').first().click();
    // wait for duck like to update
    // eslint-disable-next-line cypress/no-unnecessary-waiting
    cy.wait(1000);
    cy.get('[data-testid="like-count"]', { timeout: 10000 }).first().should('have.text', 0);
};

const staffUpduckPost = (user, thread_title) => {
    checkStaffUpduck(thread_title, 'be.not.visible');
    upduckPost(thread_title);
    checkStaffUpduck(thread_title, 'be.visible');

    // Ta will upduck reply in thread 2,3 and instructor will upduck reply in thread 1, 2 and 3
    if (!(user === 'ta' && thread_title === title1)) {
        upduckReply(thread_title);
    }
    if (user !== 'instructor') {
        removeUpduckPost(thread_title);
        checkStaffUpduck(thread_title, 'be.not.visible');
    }
};

const studentUpduckPost = (thread_title) => {
    checkStaffUpduck(thread_title, 'be.not.visible');
    upduckPost(thread_title);
    checkStaffUpduck(thread_title, 'be.not.visible');
    removeUpduckPost(thread_title);
    // upduck reply, do not remove yet, for checking thread sum duck purpose
    if (thread_title === title3) {
        upduckReply(thread_title);
    }
};

const checkStaffUpduck = (title, visible) => {
    cy.get('[data-testid="thread-list-item"]').contains(title).click();
    cy.get('[data-testid="create-post-head"]').should('contain', title);
    cy.get('[data-testid="instructor-like"]').first().should(visible);
};

const checkThreadduck = (order, ducks) => {
    // thread 1 suppose to have 2 total duck, thread 2 suppose to have 3 total ducks, thread 3 suppose to have 4 total ducks
    cy.get('.thread_box').eq(order).find('[data-testid="thread-like-count"]').should('have.text', ducks);
};

describe('Should test creating, replying, merging, removing, and upducks in forum', () => {
    beforeEach(() => {
        cy.login('instructor');
        cy.visit(['sample', 'forum']);
        cy.get('#nav-sidebar-collapse-sidebar').click();
    });

    it('Reply button is disabled when applicable and thread reply can contain an attachment', () => {
        createThread(title1, title1, 'Comment');
        replyDisabled(title1, attachment1);
        removeThread(title1);
    });

    it('Form content is not cleared while submitting with empty description', () => {
        cy.get('[title="Create Thread"]').click();
        cy.get('#title').type(title1);
        cy.get('.cat-buttons').contains('Comment').click();

        // eslint-disable-next-line cypress/no-unnecessary-waiting
        cy.wait(1000);
        cy.get('[name="post"]').click();

        // Check if the title is still there
        cy.get('#title').should('have.value', title1);

        // clear form title and de-select category
        cy.get('#title').clear();
        cy.get('.cat-buttons').contains('Comment').click();
    });

    it('Create, reply to, merge, and delete threads', () => {
        // Add and Delete Image Attachment
        // uploadAttachmentAndDelete(title4, attachment1);
        createThread(title1, content1, 'Comment');
        createThread(title2, content2, 'Question');
        createThread(title3, content3, 'Tutorials');

        replyToThread(title1, reply1);
        replyToThread(title2, reply2);
        replyToThread(title3, reply3);

        // Student upduck
        cy.logout();
        cy.login('student');
        cy.visit(['sample', 'forum']);
        studentUpduckPost(title1);
        studentUpduckPost(title2);
        studentUpduckPost(title3);

        // TA upduck
        cy.logout();
        cy.login('ta');
        cy.visit(['sample', 'forum']);
        staffUpduckPost('ta', title1);
        staffUpduckPost('ta', title2);
        staffUpduckPost('ta', title3);

        // Instructor upduck and check the stats page for instructor with 3 upducks
        cy.logout();
        cy.login('instructor');
        cy.visit(['sample', 'forum']);
        staffUpduckPost('instructor', title1);
        staffUpduckPost('instructor', title2);
        staffUpduckPost('instructor', title3);

        // Check thread sum duck
        cy.visit(['sample', 'forum']);
        checkThreadduck(2, 2);
        checkThreadduck(1, 3);
        checkThreadduck(0, 4);

        checkStatsUpducks('Instructor, Quinn', 9);

        // Tutorial into Questions
        mergeThreads(title3, title2, merged1);

        // Resulting thread into comment
        mergeThreads(title2, title1, merged2);

        // Remove threads
        removeThread(title1);
    });
});
