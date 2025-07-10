import { buildUrl, getApiKey, verifyWebSocketFunctionality } from '../../support/utils';

const title1 = 'Cypress Title 1 Cypress';
const title2 = 'Cypress Title 2 Cypress';
const title3 = 'Cypress Title 3 Cypress';
const title4 = 'Python Tutorials';
const title5 = 'WebSocket Title Test 1';
const title6 = 'WebSocket Title Test 2';
const content1 = 'Cypress Content 1 Cypress';
const content2 = 'Cypress Content 2 Cypress';
const content3 = 'Cypress Content 3 Cypress';
const content4 = 'Cypress Content 4 Cypress';
const content5 = 'Cypress Content 5 Cypress';
const reply1 = 'Cypress Reply 1 Cypress';
const reply2 = 'Cypress Reply 2 Cypress';
const reply3 = 'Cypress Reply 3 Cypress';
const reply4 = 'Cypress Reply 4 Cypress';
const reply5 = 'Cypress Reply 5 Cypress';
const merged1 = 'Merged Thread Title: '.concat(title3, '\n\n', content3);
const merged2 = 'Merged Thread Title: '.concat(title2, '\n\n', content2);
const merged3 = 'Merged Thread Title: '.concat(title6, '\n\n', content5);
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

const submitCreateThreadRequest = (title, content, category = '2') => {
    const body = {
        'title': title,
        'markdown_status': 0,
        'lock_thread_date': '',
        'thread_post_content': content,
        'cat[]': category,
        'expirationDate': new Date(Date.now() + 1000 * 60 * 60 * 24 * 7).toISOString(), // 1 week from now
        'thread_status': -1,
    };

    // Properly return the Cypress chain containing the response object and the thread element
    return cy.wrap(null).then(() => {
        return verifyWebSocketFunctionality(
            ['sample', 'forum', 'threads', 'new'],
            'POST',
            'multipart/form-data',
            body,
            (response) => {
                return cy.get(`[data-thread_title*="${title}"]`)
                    .should('exist')
                    .then((thread) => [response, thread]);
            },
        );
    });
};

const submitCreatePostRequest = (threadId, parentPostId, content) => {
    const body = {
        thread_id: threadId,
        parent_id: parentPostId,
        thread_post_content: content,
        markdown_status: 0,
        display_option: 'tree',
        thread_status: -1,
    };

    // Properly return the Cypress chain containing the response object and the post element
    return cy.wrap(null).then(() => {
        return verifyWebSocketFunctionality(
            ['sample', 'forum', 'posts', 'new'],
            'POST',
            'multipart/form-data',
            body,
            (response) => {
                return cy.get('.post_box')
                    .contains(content)
                    .should('exist')
                    .closest('.post_box')
                    .then((post) => [response, post]);
            },
        );
    });
};

const submitDeletePostRequest = (title, threadId, postId, isFirstPost = false) => {
    const body = { thread_id: threadId, post_id: postId };

    return verifyWebSocketFunctionality(
        ['sample', 'forum', 'posts', 'delete'],
        'POST',
        'multipart/form-data',
        body,
        (response) => {
            // Verify the response type is correct
            if (isFirstPost) {
                const oldPage = buildUrl(['sample', 'forum', 'threads', threadId], true);
                cy.get(`[data-thread_title*="${title}"]`).should('not.exist');
                expect(response.type).to.equal('thread');
                cy.url().should('not.include', oldPage);
            }
            else {
                expect(response.type).to.equal('post');
            }

            // Post should always be deleted
            cy.get(`#${postId}`).should('not.exist');
        });
};

const submitMergeThreadRequest = (threadId, childThreadId) => {
    const body = {
        merge_thread_parent: threadId,
        merge_thread_child: childThreadId,
    };

    // Properly return the Cypress chain containing the response object and the merged thread element
    return cy.wrap(null).then(() => {
        return verifyWebSocketFunctionality(
            ['sample', 'forum', 'threads', 'merge'],
            'POST',
            'multipart/form-data',
            body,
            (response) => {
                return cy.get('.post_box').contains(`Merged Thread Title: ${title6}`).should('exist').closest('.post_box').then((post) => {
                    return [response, post];
                });
            },
        );
    });
};

/**
 * Submit a like/unlike (toggle) request for a forum post via the API.
 *
 * @param {Object} params - The parameters for the request
 * @param {number} params.postId - The post ID to like/unlike
 * @param {number} params.threadId - The thread ID containing the post
 * @param {string} params.currentUser - The user performing the action
 * @param {string} [params.apiKey] - Optional API key for Authorization header
 * @returns {Cypress.Chainable}
 */
function submitToggleLikeRequest({ threadId, postId, currentUser, apiKey }) {
    const url = buildUrl(['sample', 'posts', 'likes'], true).replace('courses', 'api/courses');
    const headers = {
        'Content-Type': 'application/json',
        'Authorization': apiKey,
    };

    return cy.request({
        method: 'POST',
        url,
        headers,
        body: {
            thread_id: threadId,
            post_id: postId,
            current_user: currentUser
        },
    }).then((response) => {
        return JSON.parse(Cypress.Blob.arrayBufferToBinaryString(response.body) || '{}');
    });
}

const expectPostHierarchy = (post, expected) => {
    const { threadId, postId, parentPostId, level, content, nextPage } = expected;

    // Verify the post container data items values
    cy.wrap(post).within(() => {
        expect(Number(post.attr('id'))).to.equal(postId);
        expect(Number(post.attr('data-parent_id'))).to.equal(parentPostId);
        expect(Number(post.attr('data-reply_level'))).to.equal(level);
        cy.get('.post_content').invoke('text').then((text) => {
            expect(text.trim()).to.equal(content);
        });
    });

    // Verify the next page is the thread page
    expect(nextPage).to.equal(`${buildUrl(['sample', 'forum', 'threads', threadId], true)}?option=tree`);
};

describe('Should test WebSocket functionality', () => {
    beforeEach(() => {
        cy.login('instructor');
        cy.visit(['sample', 'forum']);
        cy.get('#nav-sidebar-collapse-sidebar').click();
        removeThread(title5);
    });

    it('Should verify WebSocket functionality for creating and deleting a new thread', () => {
        return;
        submitCreateThreadRequest(title5, content4, '2').then(([response, thread]) => {
            cy.wrap(thread).as('thread');

            // Verify all the thread inner-components
            cy.get('@thread').within(() => {
                cy.get('[data-testid="thread-list-item"]').should('contain', title5);
                cy.get('.thread-content').should('contain', content4);
                cy.get('.label_forum').should('contain', 'Question');
            });

            // Verify the server response matches the DOM
            cy.get('@thread').invoke('attr', 'data-thread_id').then((val) => {
                const threadId = Number(val);
                expect(response.thread_id).to.equal(threadId);

                // Ensure the new container on the thread list allows us to visit the thread
                const nextPage = buildUrl(['sample', 'forum', 'threads', threadId], true);
                expect(response.next_page).to.equal(nextPage);
                cy.get('@thread').click();

                return cy.url().should('include', nextPage).then(() => {
                    cy.get('.first_post').should('exist').then((post) => {
                        // Verify the inserted initial post ID
                        expect(Number(post.attr('id'))).to.equal(response.post_id);

                        // Verify the initial reply level is 1
                        expect(Number(post.attr('data-reply_level'))).to.equal(1);
                    });
                });
            }).then(() => {
                submitDeletePostRequest(title5, response.thread_id, response.post_id, true);
            });
        });
    });

    it('Should verify WebSocket functionality for incoming and deleting posts with the correct reply level', () => {
        return;
        submitCreateThreadRequest(title5, content4, '2').then(([threadResponse, _]) => {
            const [threadId, parentPostId, nextPage] = [threadResponse.thread_id, threadResponse.post_id, threadResponse.next_page];

            cy.visit(nextPage).then(() => {
                let firstPostId, secondPostId;

                submitCreatePostRequest(threadId, parentPostId, reply4).then(([firstPostResponse, firstPost]) => {
                    firstPostId = firstPostResponse.post_id;
                    cy.wrap(expectPostHierarchy(firstPost, {
                        threadId,
                        postId: firstPostId,
                        parentPostId,
                        level: 2,
                        content: reply4,
                        nextPage: firstPostResponse.next_page,
                    })).as('firstPost');

                    cy.get('@firstPost').then(() => {
                        firstPostId = firstPostResponse.post_id;
                        submitCreatePostRequest(threadId, firstPostId, reply5).then(([secondPostResponse, secondPost]) => {
                            secondPostId = secondPostResponse.post_id;
                            cy.wrap(expectPostHierarchy(secondPost, {
                                threadId,
                                postId: secondPostId,
                                parentPostId: firstPostId,
                                level: 3,
                                content: reply5,
                                nextPage: secondPostResponse.next_page,
                            })).as('secondPost');
                        });
                    });

                    cy.get('@secondPost').then(() => {
                        submitDeletePostRequest(title5, threadId, firstPostId, false).then(() => {
                            // Verify the thread tree is deleted
                            cy.get(`#${firstPostId}`).should('not.exist');
                            cy.get(`#${secondPostId}`).should('not.exist');
                        });
                    });
                });
            });
        });
    });

    it('Should verify WebSocket functionality for merging threads', () => {
        return;
        // Create the base thread
        submitCreateThreadRequest(title5, content4, '2').then(([threadResponse, _]) => {
            const [threadId, parentPostId, baseNextPage] = [threadResponse.thread_id, threadResponse.post_id, threadResponse.next_page];

            // Create the merging thread
            submitCreateThreadRequest(title6, content5, '2').then(([threadResponse, _]) => {
                const [mergingThreadId, mergingPostId, mergingNextPage] = [threadResponse.thread_id, threadResponse.post_id, threadResponse.next_page];

                cy.visit(mergingNextPage).then(() => {
                    submitMergeThreadRequest(threadId, mergingThreadId).then(([mergeResponse, mergedPost]) => {
                        expect(mergeResponse.redirect).to.equal(baseNextPage);
                        expectPostHierarchy(mergedPost, {
                            threadId: mergingThreadId,
                            postId: mergingPostId,
                            parentPostId: parentPostId,
                            level: 2,
                            content: merged3,
                            nextPage: `${mergingNextPage}?option=tree`,
                        });
                    });
                });
            });
        });
    });

    it('Should verify WebSocket functionality for liking and unliking posts via API', () => {
        // Create a thread as instructor
        submitCreateThreadRequest(title5, content4, '2').then(([threadResponse, thread]) => {
            const [threadId, postId, nextPage] = [threadResponse.thread_id, threadResponse.post_id, threadResponse.next_page];
            getApiKey('student', 'student').then((studentApiKey) => {
                getApiKey('instructor', 'instructor').then((instructorApiKey) => {
                    cy.wrap({ studentApiKey, instructorApiKey }).as('apiKeys');
                });
            });

            // Visit the thread as instructor
            cy.visit(nextPage).then(() => {
                // Get API key for 'student'
                cy.get('@apiKeys').then(({ studentApiKey, instructorApiKey }) => {
                    // Like as 'student' via API
                    submitToggleLikeRequest({ threadId, postId, currentUser: 'student', apiKey: studentApiKey }).then(() => {
                        // Verify like count is 1, duck is grey, instructor-like badge is not visible
                        cy.get(`[data-testid="like-count"]#likeCounter_${postId}`).should('have.text', '1');
                        cy.get(`img#likeIcon_${postId}`).should('have.attr', 'src').and('include', 'light-mode-off-duck.svg');
                        cy.get(`[data-testid="instructor-like"]#likedByInstructor_${postId}`).should('have.attr', 'style').and('include', 'display: none');

                        // Like as instructor via API
                        submitToggleLikeRequest({ threadId, postId, currentUser: 'instructor', apiKey: instructorApiKey }).then(() => {
                            // Verify like count is 2, duck is yellow, instructor-like badge is visible
                            cy.get(`[data-testid="like-count"]#likeCounter_${postId}`).should('have.text', '2');
                            cy.get(`img#likeIcon_${postId}`).should('have.attr', 'src').and('include', 'on-duck-button.svg');
                            cy.get(`[data-testid="instructor-like"]#likedByInstructor_${postId}`).should('not.have.attr', 'style', 'display: none;');

                            // Unlike as 'student' via API
                            submitToggleLikeRequest({ threadId, postId, currentUser: 'student', apiKey: studentApiKey }).then(() => {
                                // Verify like count is 1, duck is yellow, instructor-like badge is still visible
                                cy.get(`[data-testid="like-count"]#likeCounter_${postId}`).should('have.text', '1');
                                cy.get(`img#likeIcon_${postId}`).should('have.attr', 'src').and('include', 'on-duck-button.svg');
                                cy.get(`[data-testid="instructor-like"]#likedByInstructor_${postId}`).should('not.have.attr', 'style', 'display: none;');
                            });
                        });
                    });
                });
            });
        });
    });
});
