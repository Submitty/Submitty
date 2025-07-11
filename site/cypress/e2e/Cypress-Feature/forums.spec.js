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
        cy.login('instructor');
        cy.visit(['sample', 'forum']);
        cy.get('#nav-sidebar-collapse-sidebar').click();
        removeThread(title1);
        removeThread(title2);
        removeThread(title3);
    });

    it('Remove threads removes all threads with the same title', () => {
        createThread(title1, content1, 'Comment');
        createThread(title1, content1, 'Comment');
        removeThread(title1);
        cy.get('[data-testid="thread-list-item"]').contains(title1).should('not.exist');
    });

    it('Reply button is disabled when applicable and thread reply can contain an attachment', () => {
        createThread(title1, title1, 'Comment');
        replyDisabled(title1, attachment1);
        removeThread(title1);
    });

    it('Form content is not cleared while submitting with empty description', () => {
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

const submitCreateThreadRequest = (title, content) => {
    const body = {
        'title': title,
        'markdown_status': 0,
        'lock_thread_date': '',
        'thread_post_content': content,
        'cat[]': '2', // "Question" category
        'expirationDate': new Date(Date.now() + 1000 * 60 * 60 * 24 * 7).toISOString(), // 1 week from now
        'thread_status': -1,
    };

    return cy.wrap(null).then(() => {
        return verifyWebSocketFunctionality(
            ['sample', 'forum', 'threads', 'new'],
            'POST',
            'multipart/form-data',
            body,
            // Return the server response and local thread element within the Cypress chain
            (response) => cy.get(`[data-thread_title*="${title}"]`).then((post) => [response, post]),
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

    return cy.wrap(null).then(() => {
        return verifyWebSocketFunctionality(
            ['sample', 'forum', 'posts', 'new'],
            'POST',
            'multipart/form-data',
            body,
            // Return the server response and local post element within the Cypress chain
            (response) => cy.get('.post_box').contains(content).should('exist').closest('.post_box').then((post) => [response, post]),
        );
    });
};

const submitDeletePostRequest = (title, threadId, postId, isFirstPost = false) => {
    const body = {
        thread_id: threadId,
        post_id: postId,
    };

    return verifyWebSocketFunctionality(
        ['sample', 'forum', 'posts', 'delete'],
        'POST',
        'multipart/form-data',
        body,
        (response) => {
            if (isFirstPost) {
                // Verify thread deletion
                const redirectPage = buildUrl(['sample', 'forum'], true);
                expect(response.type).to.equal('thread');
                cy.get('[data-testid="thread-list-item"]').contains(title).should('not.exist');
                // Verify automatic redirect to thread list page after deletion
                cy.url().should('equal', redirectPage);
            }
            else {
                // Verify server-side post deletion
                expect(response.type).to.equal('post');
            }

            cy.get(`#${postId}`).should('not.exist');
        });
};

const submitMergeThreadRequest = (threadId, childThreadId) => {
    const body = {
        merge_thread_parent: threadId,
        merge_thread_child: childThreadId,
    };

    return cy.wrap(null).then(() => {
        return verifyWebSocketFunctionality(
            ['sample', 'forum', 'threads', 'merge'],
            'POST',
            'multipart/form-data',
            body,
            // Return the server response and local merged thread element within the Cypress chain
            (response) => cy.get('.post_box').contains(`Merged Thread Title: ${title6}`).should('exist').closest('.post_box').then((post) => [response, post]),
        );
    });
};

const submitToggleLikeRequest = (currentUser, apiKey, threadId, postId, expected) => {
    const { likeCount, likeIcon, instructorLikeBadge } = expected;
    const body = {
        thread_id: threadId,
        post_id: postId,
        current_user: currentUser,
    };

    return verifyWebSocketFunctionality(
        ['sample', 'posts', 'likes'],
        'POST',
        'application/json',
        body,
        () => {
            cy.get(`[data-testid="like-count"]#likeCounter_${postId}`).should('have.text', likeCount);
            cy.get(`img#likeIcon_${postId}`).should('have.attr', 'src').and('include', likeIcon);
            cy.get(`[data-testid="instructor-like"]#likedByInstructor_${postId}`).should(instructorLikeBadge ? 'not.have.attr' : 'have.attr', 'style', 'display: none;');
        },
        apiKey,
    );
};

const expectPostHierarchy = (post, expected) => {
    const { threadId, postId, parentPostId, level, content, nextPage } = expected;

    // Verify the post container data items values
    cy.wrap(post).should(($post) => {
        expect(Number($post.attr('id'))).to.equal(postId);
        expect(Number($post.attr('data-parent_id'))).to.equal(parentPostId);
        expect(Number($post.attr('data-reply_level'))).to.equal(level);
        expect($post.find('.post_content').text()).to.include(content);
    });

    // Verify the next page is the thread page for non-merging threads
    if (nextPage) {
        expect(nextPage).to.equal(`${buildUrl(['sample', 'forum', 'threads', threadId], true)}?option=tree`);
    }
};

describe('Should test WebSocket functionality', () => {
    beforeEach(() => {
        cy.login('instructor');
        cy.visit(['sample', 'forum']);
        cy.get('#nav-sidebar-collapse-sidebar').click();
        removeThread(title5);
    });

    it('Should verify WebSocket functionality for creating and deleting a new thread', () => {
        submitCreateThreadRequest(title5, content4).then(([response, thread]) => {
            cy.wrap(thread).as('thread');

            // Verify all the inner-thread components
            cy.get('@thread').within(() => {
                cy.get('[data-testid="thread-list-item"]').should('contain', title5);
                cy.get('.thread-content').should('contain', content4);
                cy.get('.label_forum').should('contain', 'Question');
            });

            // Verify the server response data matches the DOM components
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

                        // Verify the thread page content and initial reply level
                        cy.get('[data-testid="create-post-head"]').should('contain', title5);
                        cy.get('.post_content').should('contain', content4);
                        expect(Number(post.attr('data-reply_level'))).to.equal(1);
                    });
                });
            }).then(() => {
                submitDeletePostRequest(title5, response.thread_id, response.post_id, true);
            });
        });
    });

    it('Should verify WebSocket functionality for incoming and deleting posts with the correct reply level', () => {
        submitCreateThreadRequest(title5, content4).then(([response, _]) => {
            const { thread_id: threadId, post_id: parentPostId, next_page: nextPage } = response;

            cy.visit(nextPage).then(() => {
                submitCreatePostRequest(threadId, parentPostId, reply4).then(([firstPostResponse, firstPost]) => {
                    const firstPostId = firstPostResponse.post_id;
                    cy.wrap(expectPostHierarchy(firstPost, {
                        threadId,
                        postId: firstPostId,
                        parentPostId,
                        level: 2, // Reply to the original parent post
                        content: reply4,
                        nextPage: firstPostResponse.next_page,
                    })).as('firstPost');

                    let secondPostId;
                    cy.get('@firstPost').then(() => {
                        submitCreatePostRequest(threadId, firstPostId, reply5).then(([secondPostResponse, secondPost]) => {
                            secondPostId = secondPostResponse.post_id;
                            cy.wrap(expectPostHierarchy(secondPost, {
                                threadId,
                                postId: secondPostId,
                                parentPostId: firstPostId,
                                level: 3, // Reply to the post above
                                content: reply5,
                                nextPage: secondPostResponse.next_page,
                            })).as('secondPost');
                        });
                    });

                    cy.get('@secondPost').then(() => {
                        // Verify the thread tree from the first post is deleted, but the parent post is still remains
                        submitDeletePostRequest(title5, threadId, firstPostId, false).then(() => {
                            cy.get(`#${parentPostId}`).should('exist');
                            cy.get(`#${firstPostId}`).should('not.exist');
                            cy.get(`#${secondPostId}`).should('not.exist');
                        });
                    });
                });
            });
        });
    });

    it('Should verify WebSocket functionality for merging threads', () => {
        // Remove the merging thread title once during the entire testing file to avoid redundant calls
        removeThread(title6);

        // Create the base thread
        submitCreateThreadRequest(title5, content4).then(([baseResponse, _]) => {
            const { thread_id: baseThreadId, post_id: basePostId, next_page: baseNextPage } = baseResponse;

            // Create the merging thread
            submitCreateThreadRequest(title6, content5).then(([mergingResponse, _]) => {
                let replyId;
                const { thread_id: mergingThreadId, post_id: mergingPostId, next_page: mergingNextPage } = mergingResponse;

                // Visit the merging thread
                cy.visit(mergingNextPage).then(() => {
                    // Submit a reply to the merging thread
                    submitCreatePostRequest(mergingThreadId, mergingPostId, reply5).then(([replyResponse, reply]) => {
                        const { post_id, next_page: replyNextPage } = replyResponse;
                        replyId = post_id;

                        return cy.wrap(null).then(() => {
                            expectPostHierarchy(reply, {
                                threadId: mergingThreadId,
                                postId: replyId,
                                parentPostId: mergingPostId,
                                level: 2, // Reply to the merging thread parent post
                                content: reply5,
                                nextPage: replyNextPage,
                            });

                            return cy.wrap(replyId);
                        });
                    }).then((replyId) => {
                        // Submit the merge request
                        submitMergeThreadRequest(baseThreadId, mergingThreadId).then(([mergeResponse, mergedPost]) => {
                            expect(mergeResponse.redirect).to.equal(baseNextPage);
                            expectPostHierarchy(mergedPost, {
                                threadId: mergingThreadId,
                                postId: mergingPostId,
                                parentPostId: basePostId,
                                level: 2, // Reply to the base thread parent post
                                content: merged3,
                            });
                            // Refetch the reply container to verify the reply level is correct
                            cy.get(`#${replyId}`).then((reply) => {
                                expectPostHierarchy(reply, {
                                    threadId: mergingThreadId,
                                    postId: replyId,
                                    parentPostId: mergingPostId,
                                    level: 3, // Reply to the merging thread parent post
                                    content: reply5,
                                });
                            });
                        });
                    });
                });
            });
        });
    });

    it('Should verify WebSocket functionality for liking and unliking posts via API', () => {
        // Create a thread as instructor
        submitCreateThreadRequest(title5, content4).then(([response, _]) => {
            const { thread_id: threadId, post_id: postId, next_page: nextPage } = response;

            // Fetch the required API keys for the test
            getApiKey('student', 'student').then((studentApiKey) => {
                getApiKey('instructor', 'instructor').then((instructorApiKey) => {
                    cy.wrap({ studentApiKey, instructorApiKey }).as('apiKeys');
                });
            });

            // Visit the thread as instructor
            cy.visit(nextPage).then(() => {
                cy.get('@apiKeys').then(({ studentApiKey, instructorApiKey }) => {
                    // Like as 'student' via API
                    submitToggleLikeRequest('student', studentApiKey, threadId, postId, {
                        likeCount: 1,
                        likeIcon: 'light-mode-off-duck.svg',
                        instructorLikeBadge: false,
                    }).as('studentLike');

                    cy.get('@studentLike').then(() => {
                        // Like as 'instructor' via API
                        submitToggleLikeRequest('instructor', instructorApiKey, threadId, postId, {
                            likeCount: 2,
                            likeIcon: 'on-duck-button.svg',
                            instructorLikeBadge: true,
                        }).as('instructorLike');
                    });

                    cy.get('@instructorLike').then(() => {
                        // Unlike as 'student' via API
                        submitToggleLikeRequest('student', studentApiKey, threadId, postId, {
                            likeCount: 1,
                            likeIcon: 'on-duck-button.svg',
                            instructorLikeBadge: true,
                        }).as('studentUnlike');
                    });

                    cy.get('@studentUnlike').then(() => {
                        // Unlike as 'instructor' via API
                        submitToggleLikeRequest('instructor', instructorApiKey, threadId, postId, {
                            likeCount: 0,
                            likeIcon: 'light-mode-off-duck.svg',
                            instructorLikeBadge: false,
                        });
                    });
                });
            });
        });
    });
});
