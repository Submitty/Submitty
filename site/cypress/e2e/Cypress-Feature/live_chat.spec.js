import { getApiKey, getCurrentSemester } from '../../support/utils';

const title1 = 'Test Chatroom Title';
const title2 = 'Non Anon Test Chatroom Title';
const description1 = 'Test Description';
const description2 = 'Non Anon Test Description';

// Messages for 'Should test starting chat sessions and chatting'
const chatMsg1 = 'Hello from instructor';
const chatMsg2 = 'Student response message';
const chatMsg3 = 'Follow-up from instructor';
const chatMsg4 = 'Final student message';

// Messages for anonymity tests
const anonMsg1 = 'Anon instructor message';
const anonMsg2 = 'Another anon instructor msg';
const anonMsg3 = 'Non-anon instructor msg';
const anonMsg4 = 'Student regular message';
const anonMsg5 = 'Student anon message';
const anonMsg6 = 'Final instructor anon msg';

// Messages for WebSocket tests
const wsMsg = 'WebSocket test message';

// Generic messages for other tests
const msgText1 = 'Message 1';
const msgText2 = 'Message 2';
const msgText3 = 'Message 3';

const name1 = 'Quinn Instructor';
const name2 = 'Joe S.';

const getChatroom = (title) => {
    return cy.get('[data-testid="chatroom-item"]').filter((_, el) => {
        return Cypress.$(el).find('[data-testid="chatroom-title"]').text().trim() === title;
    }).first();
};

const startChatSession = (title) => {
    getChatroom(title).find('[data-testid="enable-chatroom"]').first().click();
};

const endChatSession = (title) => {
    getChatroom(title).then(($chatroom) => {
        if ($chatroom.find('[data-testid="disable-chatroom"]').length > 0) {
            cy.window().then((win) => {
                cy.stub(win, 'confirm').callsFake((confirmText) => {
                    expect(confirmText).to.equal('This will close the chatroom. Are you sure?');
                    return true;
                });
            });
            cy.wrap($chatroom).find('[data-testid="disable-chatroom"]').first().click();
        }
    });
};

const toggleLiveChat = (enableChat) => {
    cy.visit(['sample', 'config']);
    return cy.get('input#chat-enabled').first().then(($checkbox) => {
        const isCurrentlyEnabled = $checkbox.is(':checked');
        if (isCurrentlyEnabled !== enableChat) {
            cy.wrap($checkbox).click();
        }
    });
};

const chatroomExists = (title) => {
    return cy.get('body').then(($body) => {
        if ($body.find('[data-testid="chatroom-item"]').length === 0) {
            return false;
        }

        return cy.get('[data-testid="chatroom-item"]').then(($rows) => {
            return $rows.filter((_, el) => {
                return Cypress.$(el).find('[data-testid="chatroom-title"]').text().trim() === title;
            }).length > 0;
        });
    });
};

const deleteChatroom = (title) => {
    cy.reload();
    chatroomExists(title).then((exists) => {
        if (exists) {
            endChatSession(title);
            cy.window().then((win) => {
                cy.stub(win, 'confirm').callsFake((confirmText) => {
                    expect(confirmText).to.contain(`This will delete chatroom '${title}'. Are you sure?`);
                    return true;
                });
            });

            getChatroom(title).find('[data-testid="delete-chatroom"]').first().click();
            deleteChatroom(title);
        }
    });
};

const checkChatExists = (title, exists = true) => {
    if (exists) {
        cy.get('[data-testid="chatroom-title"]')
            .contains(title)
            .should('exist');
    }
    else {
        cy.get('body').then(($body) => {
            if ($body.find('[data-testid="chatroom-item"]').length !== 0) {
                cy.get('[data-testid="chatroom-title"]')
                    .contains(title)
                    .should('not.exist');
            }
        });
    }
};

const checkDescription = (title, description) => {
    getChatroom(title).find('[data-testid="chatroom-description"]').then(($el) => {
        expect($el.text().trim()).to.equal(description);
    });
};

const checkHost = (title, host) => {
    getChatroom(title).find('[data-testid="chatroom-host"]').then(($el) => {
        expect($el.text().trim()).to.equal(host);
    });
};

const checkAnon = (title, expectedAnon) => {
    const exist = expectedAnon ? 'exist' : 'not.exist';
    getChatroom(title).then(($chatroom) => {
        cy.wrap($chatroom).find('[data-testid="chat-join-btn"]').should('exist');
        cy.wrap($chatroom).find('[data-testid="anon-chat-join-btn"]').should(exist);
    });
};

const createChatroom = (title, description, isAnon) => {
    cy.get('[data-testid="new-chatroom-btn"]').click();
    cy.get('[data-testid="chatroom-name-entry"]').type(title, { force: true });
    cy.get('[data-testid="chatroom-description-entry"]').type(description, { force: true });
    if (!isAnon) {
        cy.get('[data-testid="enable-disable-anon"]').click();
    }
    cy.get('[data-testid="submit-chat-creation"]').click({ force: true });
    checkChatExists(title);
    checkDescription(title, description);
    checkAnon(title, isAnon);
};

const editChatroom = (oldTitle, newTitle, newDescription, toggleAnon, expectedAnon) => {
    cy.reload();
    getChatroom(oldTitle).find('[data-testid="edit-chatroom"]').first().click().then(() => {
        cy.get('[data-testid="chatroom-name-edit"]').clear({ force: true });
        cy.get('[data-testid="chatroom-name-edit"]').type(newTitle, { force: true });
        cy.get('[data-testid="chatroom-description-edit"]').clear({ force: true });
        cy.get('[data-testid="chatroom-description-edit"]').type(newDescription, { force: true });
        if (toggleAnon) {
            cy.get('[data-testid="edit-anon"]').click();
        }
        cy.get('[data-testid="submit-chat-edit"]').click({ force: true });
        checkChatExists(newTitle);
        checkDescription(newTitle, newDescription);
        checkAnon(newTitle, expectedAnon);
    });
};

const checkChatMessage = (text, name, id) => {
    cy.get(`#${id}`).should('contain.text', text);
    cy.get(`#${id}`).should('contain.text', name);
};

const getAnonName = () => {
    return cy.get('[data-testid="sender-name"]').last().then(($el) => {
        return $el.text().trim();
    });
};

const getLastMessageId = () => {
    return cy.get('[data-testid="message-container"]').last().then(($el) => {
        return parseInt($el.attr('id'));
    });
};

const sendChatMessage = (text, sender, expectedId, action = 'click') => {
    cy.get('[data-testid="msg-input"]').type(text);
    if (action === 'enter') {
        cy.get('[data-testid="msg-input"]').type('{enter}');
        checkChatMessage(text, sender, expectedId);
    }
    else {
        cy.get('[data-testid="send-btn"]').click();
        checkChatMessage(text, sender, expectedId);
    }
};

const generateBaseMessageID = () => {
    return cy.get('[data-testid="msg-input"]').then(() => {
        return cy.get('[data-testid="msg-input"]').type('grabid');
    }).then(() => {
        return cy.get('[data-testid="msg-input"]').type('{enter}');
    }).then(() => {
        cy.reload();
        return getLastMessageId();
    });
};

const leaveChat = (title) => {
    cy.get('[data-testid="leave-chat"]').click();
    checkChatExists(title);
    cy.url().should('match', /\/chat$/);
};

const enterChat = (title, anonymous = false) => {
    if (anonymous) {
        getChatroom(title).find('[data-testid="anon-chat-join-btn"]').click();
    }
    else {
        getChatroom(title).find('[data-testid="chat-join-btn"]').click();
    }
};

const visitLiveChat = (user) => {
    cy.logout();
    cy.login(user);
    cy.visit(['sample', 'chat']);
};

describe('Tests for enabling Live Chat', () => {
    beforeEach(() => {
        cy.login('instructor');
    });

    it('Should enable the Live Chat feature for the sample course', () => {
        toggleLiveChat(true).then(() => {
            cy.visit(['sample', 'chat']);
            cy.get('body').then(($body) => {
                expect($body.find('[class="icon-title"]').text()).includes('Live Chat');
            });
            cy.get('[data-testid="new-chatroom-btn"]');
        }).then(() => {
            toggleLiveChat(false).then(() => {
                cy.visit(['sample', 'chat']);
                cy.get('[data-testid="new-chatroom-btn"]').should('not.exist');
                cy.get('body').then(($body) => {
                    expect($body.find('main div b').text()).to.equal('The chat feature is not enabled.');
                });
            });
        });
    });
});

describe('Tests for creating, editing and using tests', () => {
    /**
     * Ensures a clean slate before and after each test by ensuring live chat is enabled/disabled and deleting sample chats.
     */
    beforeEach(() => {
        cy.login('instructor');
        toggleLiveChat(true).then(() => {
            cy.visit(['sample', 'chat']);
            deleteChatroom(title1);
            deleteChatroom(title2);
        });
    });

    afterEach(() => {
        visitLiveChat('instructor');
        deleteChatroom(title1);
        deleteChatroom(title2);
        toggleLiveChat(false).then(() => {
            cy.reload();
        });
    });

    it('Should test creating new chats, allowing and disallowing anonymous participants.', () => {
        createChatroom(title1, description1, true);
        createChatroom(title2, description2, false);
    });

    it('Should test editing chats', () => {
        createChatroom(title1, description1, true);
        editChatroom(title1, title2, description2, true, false);
    });

    it('Should test deleting chats', () => {
        createChatroom(title1, description1, true);
        deleteChatroom(title1);
        checkChatExists(title1, false);
    });

    it('Should test starting chat sessions and ending chat sessions', () => {
        // Create Chatroom but don't enable
        createChatroom(title1, description1, false);
        // Login to student account, which shouldn't see disabled chatrooms
        visitLiveChat('student');
        // Check for chatroom nonexistence
        checkChatExists(title1, false);
        // Go back to instructor, enable chatroom
        visitLiveChat('instructor');
        startChatSession(title1);
        // Go to student, now chatroom should exist
        visitLiveChat('student');
        checkChatExists(title1);
        visitLiveChat('instructor');
        // Now check for chatroom removal after ending session
        endChatSession(title1);
        visitLiveChat('student');
        checkChatExists(title1, false);
    });

    it('Should test starting chat sessions and chatting', () => {
        // Create Chatroom with chat messages in it
        createChatroom(title1, description1, false);
        startChatSession(title1);
        enterChat(title1);
        generateBaseMessageID().then((baseId) => {
            const instructorMsg1Id = baseId + 1;
            const instructorMsg2Id = baseId + 2;
            const studentMsg1Id = baseId + 3;
            const studentMsg2Id = baseId + 4;
            sendChatMessage(chatMsg1, name1, instructorMsg1Id);
            sendChatMessage(chatMsg3, name1, instructorMsg2Id);
            // Check for leave chat button, check for chat state from other user
            leaveChat(title1);
            visitLiveChat('student');
            checkChatExists(title1);
            checkDescription(title1, description1);
            checkHost(title1, name1);
            getChatroom(title1);
            // Add new messages, check for chat message
            enterChat(title1);
            checkChatMessage(chatMsg3, name1, instructorMsg2Id);
            sendChatMessage(chatMsg2, name2, studentMsg1Id);
            // Check for message existence after reload, check that clicking enter sends a message
            cy.reload();
            checkChatMessage(chatMsg2, name2, studentMsg1Id);
            sendChatMessage(chatMsg4, name2, studentMsg2Id, 'enter');
            leaveChat(title1);
        });
    });

    it('Should test anonymity', () => {
        // Check for basic anonymous chat functions
        createChatroom(title1, description1, true);
        startChatSession(title1);
        enterChat(title1, true);
        generateBaseMessageID().then((baseId) => {
            const anonInstructorMsg1Id = baseId + 1;
            const anonInstructorMsg2Id = baseId + 2;
            const nonAnonInstructorMsgId = baseId + 3;
            const regularStudentMsgId = baseId + 4;
            const anonStudentMsgId = baseId + 5;
            const finalAnonInstructorMsgId = baseId + 6;
            sendChatMessage(anonMsg1, 'Anonymous', anonInstructorMsg1Id);
            sendChatMessage(anonMsg2, 'Anonymous', anonInstructorMsg2Id);
            // Get the instructor's anonymous name and use it in subsequent tests
            getAnonName().then((instructorAnon) => {
                // Check that messages are still anonymous after leaving and rejoining, with the same anon name as before.
                leaveChat(title1);
                enterChat(title1, true);
                checkChatMessage(anonMsg2, instructorAnon, anonInstructorMsg2Id);
                // Check for this even when rejoining non-anon
                leaveChat(title1);
                enterChat(title1);
                checkChatMessage(anonMsg2, instructorAnon, anonInstructorMsg2Id);
                sendChatMessage(anonMsg3, name1, nonAnonInstructorMsgId);
                // Check that the student sees the non-anon name
                visitLiveChat('student');
                enterChat(title1);
                checkChatMessage(anonMsg3, name1, nonAnonInstructorMsgId);
                sendChatMessage(anonMsg4, name2, regularStudentMsgId);
                leaveChat(title1);
                // Check for student anonymous function
                enterChat(title1, true);
                checkChatMessage(anonMsg4, name2, regularStudentMsgId);
                sendChatMessage(anonMsg5, 'Anonymous', anonStudentMsgId);
                // Get the student's anonymous name and use it in subsequent tests
                getAnonName().then((studentAnon) => {
                    // expect(instructorAnon !== studentAnon); TODO: Add all anon names in a chat to a list so that it's impossible to have two equivalent names.
                    leaveChat(title1);
                    // Login to instructor, check for correct anonymous name and for anonymous name remaining across logins
                    visitLiveChat('instructor');
                    enterChat(title1, true);
                    checkChatMessage(anonMsg5, studentAnon, anonStudentMsgId);
                    sendChatMessage(anonMsg6, instructorAnon, finalAnonInstructorMsgId);
                });
            });
        });
    });

    it('Should test WebSocket functionality', () => {
        let id = NaN;
        createChatroom(title1, description1, true);
        startChatSession(title1);
        getChatroom(title1).then(($chatroom) => {
            id = Number($chatroom.attr('id'));
            expect(id).to.be.a('number');
            cy.visit(['sample', 'chat', id]);
        }).then(() => {
            generateBaseMessageID().then((messageId) => {
                const webSocketMsgId = messageId + 1;
                // Add the message via a request
                getApiKey('instructor', 'instructor').then((key) => {
                    cy.request({
                        method: 'POST',
                        url: `${Cypress.config('baseUrl')}/api/courses/${getCurrentSemester()}/sample/chat/${id}/send`,
                        body: {
                            content: wsMsg,
                            user_id: 'instructor',
                            display_name: 'Quinn+Instructor',
                            role: 'instructor',
                        },
                        headers: {
                            Authorization: key,
                        },
                    }).then((response) => {
                        // Verify a successful response and that the WebSocket message handler added the message
                        expect(response.body.status).to.eql('success');
                        // Get the message ID from the response if available
                        checkChatMessage(wsMsg, name1, webSocketMsgId);
                    });
                });
                deleteChatroom(title1);
            });
        });
    });
});
