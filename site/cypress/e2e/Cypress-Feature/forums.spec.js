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
const reply4 = 'Cypress Reply 4 Cypress';
const reply5 = 'Cypress Reply 5 Cypress';
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

describe('Should test WebSocket functionality', () => {
    beforeEach(() => {
        cy.login('instructor');
        cy.visit(['sample', 'forum']);
        cy.get('#nav-sidebar-collapse-sidebar').click();
        removeThread(title5);
    });

    it('Should verify WebSocket functionality for creating and deleting a new thread', () => {
        const createBody = {
            'title': title5,
            'markdown_status': 0,
            'lock_thread_date': '',
            'thread_post_content': content4,
            'cat[]': '2', // "Question" category
            'expirationDate': new Date(Date.now() + 1000 * 60 * 60 * 24 * 7).toISOString(), // 1 week from now
            'thread_status': -1,
        };

        verifyWebSocketFunctionality(['sample', 'forum', 'threads', 'new'], 'POST', 'multipart/form-data', createBody, (response) => {
            // Verify the thread is created
            cy.get(`[data-thread_title*="${title5}"]`).should('exist').as('newThread');
            cy.get('@newThread').within(() => {
                // Verify all the thread inner-components
                cy.get('[data-testid="thread-list-item"]').should('contain', title5);
                cy.get('.thread-content').should('contain', content4);
                cy.get('.label_forum').should('contain', 'Question');
            });
            // Verify the thread ID
            cy.get('@newThread').invoke('attr', 'data-thread_id').then((val) => {
                // Parse the inserted thread ID
                const threadId = Number(val);
                expect(threadId).to.be.a('number').and.be.greaterThan(0);
                expect(response.thread_id).to.equal(threadId);

                // Ensure the new container can allow us to visit the thread
                const nextPage = buildUrl(['sample', 'forum', 'threads', threadId], true);
                expect(response.next_page).to.equal(nextPage);
                cy.get('@newThread').click();
                cy.url().should('include', nextPage);

                return cy.get('.first_post').should('exist').then((post) => {
                    // Verify the inserted initial post ID
                    const postId = Number(post.attr('id'));
                    expect(postId).to.be.a('number').and.to.be.greaterThan(0);
                    expect(response.post_id).to.equal(postId);

                    // Verify the initial reply level is 1
                    const replyLevel = Number(post.attr('data-reply_level'));
                    expect(replyLevel).to.be.a('number').and.to.equal(1);
                });
            }).then(() => {
                // Submit a delete request for the thread, where removing the first post will also remove the thread
                const [threadId, postId] = [response.thread_id, response.post_id];
                const deleteBody = { thread_id: threadId, post_id: postId };
                const oldPage = buildUrl(['sample', 'forum', 'threads', threadId], true);

                verifyWebSocketFunctionality(['sample', 'forum', 'posts', 'delete'], 'POST', 'multipart/form-data', deleteBody, (response) => {
                    // Verify the delete type is the thread itself
                    expect(response.type).to.equal('thread');
                    // Verify the thread is deleted
                    cy.get('[data-testid="thread-list-item"]').contains(title5).should('not.exist');
                    // Verify the auto-redirection when the current thread is deleted from an external source
                    cy.url({ timeout: 10000 }).should('not.include', oldPage);
                });
            });
        });
    });

    it('Should verify WebSocket functionality for incoming and deleting posts with the correct reply level', () => {
        const createBody = {
            'title': title5,
            'markdown_status': 0,
            'lock_thread_date': '',
            'thread_post_content': content4,
            'cat[]': '2', // "Question" category
            'expirationDate': new Date(Date.now() + 1000 * 60 * 60 * 24 * 7).toISOString(), // 1 week from now
            'thread_status': -1,
        };

        verifyWebSocketFunctionality(['sample', 'forum', 'threads', 'new'], 'POST', 'multipart/form-data', createBody, (response) => {
            const [threadId, postId, nextPage] = [response.thread_id, response.post_id, response.next_page];
            cy.visit(nextPage).then(() => {
                const createBody = {
                    thread_id: threadId,
                    parent_id: postId,
                    thread_post_content: reply4,
                    markdown_status: 0,
                    display_option: 'tree',
                    thread_status: -1,
                };

                verifyWebSocketFunctionality(['sample', 'forum', 'posts', 'new'], 'POST', 'multipart/form-data', createBody, (response) => {
                    cy.get('.post_box').contains(reply4).should('exist').closest('.post_box').as('newPost');
                    cy.get('@newPost').then((post) => {
                        const newPostId = Number(post.attr('id'));
                        expect(newPostId).to.be.a('number').and.be.greaterThan(0);
                        expect(response.post_id).to.equal(newPostId);

                        // Verify the reply level is 2, as it is a reply to the first post
                        const replyLevel = Number(post.attr('data-reply_level'));
                        expect(replyLevel).to.be.a('number').and.to.equal(2);

                        const parentId = Number(post.attr('data-parent_id'));
                        expect(parentId).to.be.a('number').and.to.equal(postId);

                        // Verify the next page is the thread page
                        const nextPage = `${buildUrl(['sample', 'forum', 'threads', threadId], true)}?option=tree`;
                        expect(response.next_page).to.equal(nextPage);

                        return newPostId;
                    });
                }).then((newPostId) => {
                    // Reply to self
                    const createBody = {
                        thread_id: threadId,
                        parent_id: newPostId,
                        thread_post_content: reply5,
                        markdown_status: 0,
                    };

                    verifyWebSocketFunctionality(['sample', 'forum', 'posts', 'new'], 'POST', 'multipart/form-data', createBody, (response) => {
                        cy.get('.post_box').contains(reply5).should('exist').closest('.post_box').as('finalPost');
                        return cy.get('@finalPost').then((post) => {
                            const finalPostId = Number(post.attr('id'));
                            expect(finalPostId).to.be.a('number').and.be.greaterThan(0);
                            expect(response.post_id).to.equal(finalPostId);

                            // Verify the reply level is 3, as it is a reply to the second post
                            const replyLevel = Number(post.attr('data-reply_level'));
                            expect(replyLevel).to.be.a('number').and.to.equal(3);

                            // Verify the parent ID is the second post
                            const parentId = Number(post.attr('data-parent_id'));
                            expect(parentId).to.be.a('number').and.to.equal(newPostId);

                            // Verify the next page is the thread page
                            const nextPage = `${buildUrl(['sample', 'forum', 'threads', threadId], true)}?option=tree`;
                            expect(response.next_page).to.equal(nextPage);

                            return finalPostId;
                        });
                    }).then((finalPostId) => {
                        // Delete the initial reply to verify both posts are deleted
                        const deleteBody = { thread_id: threadId, post_id: newPostId };
                        verifyWebSocketFunctionality(['sample', 'forum', 'posts', 'delete'], 'POST', 'multipart/form-data', deleteBody, (response) => {
                            cy.get(`#${newPostId}`).should('not.exist');
                            cy.get(`#${finalPostId}`).should('not.exist');
                        });
                    });
                });
            });
        });
    });
});
