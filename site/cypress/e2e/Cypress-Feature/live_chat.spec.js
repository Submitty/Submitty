import { getApiKey, getCurrentSemester } from '../../support/utils';

const title1 = 'Test Chatroom Title';
const title2 = 'Non Anon Test Chatroom Title';
const description1 = 'Test Description';
const description2 = 'Non Anon Test Description';
const msgText1 = 'Message';
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
        expect($el.text().trim() === description);
    });
};

const checkHost = (title, host) => {
    getChatroom(title).find('[data-testid="chatroom-host"]').then(($el) => {
        expect($el.text().trim() === host);
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
    cy.get('[data-testid="new-chatroom-btn"]').should('exist');
    cy.get('[data-testid="new-chatroom-btn"]').click();
    cy.get('[data-testid="new-chatroom-btn"]').then(() => {
        cy.get('[data-testid="chatroom-name-entry"]').should('exist').type(title, { force: true });
        cy.get('[data-testid="chatroom-description-entry"]').should('exist').type(description, { force: true });
        if (!isAnon) {
            cy.get('[data-testid="enable-disable-anon"]').should('exist').click();
        }
        cy.get('[data-testid="submit-chat-creation"]').should('exist');
        cy.get('[data-testid="submit-chat-creation"]').click({ force: true });
        cy.get('[data-testid="submit-chat-creation"]').then(() => {
            checkChatExists(title);
            checkDescription(title, description);
            checkAnon(title, isAnon);
        });
    });
};

const editChatroom = (oldTitle, newTitle, newDescription, toggleAnon, expectedAnon) => {
    cy.reload();
    getChatroom(oldTitle).find('[data-testid="edit-chatroom"]').first().click().then(() => {
        cy.get('[data-testid="chatroom-name-edit"]').should('exist');
        cy.get('[data-testid="chatroom-name-edit"]').clear({ force: true });
        cy.get('[data-testid="chatroom-name-edit"]').type(newTitle, { force: true });
        cy.get('[data-testid="chatroom-description-edit"]').should('exist');
        cy.get('[data-testid="chatroom-description-edit"]').clear({ force: true });
        cy.get('[data-testid="chatroom-description-edit"]').type(newDescription, { force: true });
        if (toggleAnon) {
            cy.get('[data-testid="edit-anon"]').should('exist');
            cy.get('[data-testid="edit-anon"]').click();
        }
        cy.get('[data-testid="submit-chat-edit"]').should('exist');
        cy.get('[data-testid="submit-chat-edit"]').click({ force: true });
        cy.get('[data-testid="submit-chat-edit"]').then(() => {
            checkChatExists(newTitle);
            checkDescription(newTitle, newDescription);
            checkAnon(newTitle, expectedAnon);
        });
    });
};

const checkChatMessage = (text, name) => {
    const message = cy.get('[data-testid="message-container"]').last().text;
    expect(message === text);
    const sender = cy.get('[data-testid="sender-name"]').last().text ?? '';
    expect(sender.includes(name));
};

const getAnonName = () => {
    return cy.get('[data-testid="sender-name"]').last().text;
};

const sendChatMessage = (text, sender) => {
    cy.get('[data-testid="msg-input"]').should('exist');
    cy.get('[data-testid="msg-input"]').type(text);
    cy.get('[data-testid="msg-input"]').then(() => {
        cy.get('[data-testid="send-btn"]').should('exist');
        cy.get('[data-testid="send-btn"]').click();
        cy.get('[data-testid="send-btn"]').then(() => {
            checkChatMessage(text, sender);
        });
    });
};

const sendChatMessageEnter = (text, sender) => {
    cy.get('[data-testid="msg-input"]').should('exist');
    cy.get('[data-testid="msg-input"]').type(text);
    cy.get('[data-testid="msg-input"]').type('{enter}');
    cy.get('[data-testid="msg-input"]').then(() => {
        checkChatMessage(text, sender);
    });
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
            cy.get('[data-testid="new-chatroom-btn"]').should('exist');
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
        cy.logout();
        cy.login('instructor');
        cy.visit(['sample', 'chat']);
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

    it('Should test starting chat sessions and ending chat sessions', () => {
        // Create Chatroom but don't enable
        createChatroom(title1, description1, false);
        cy.logout();
        // Login to student account, which shouldn't see disabled chatrooms
        cy.login('student');
        cy.visit(['sample', 'chat']);
        // Check for chatroom nonexistence
        checkChatExists(title1, false);
        cy.logout();
        // Go back to instructor, enable chatroom
        cy.login('instructor');
        cy.visit(['sample', 'chat']);
        startChatSession(title1);
        cy.logout();
        // Go to student, now chatroom should exist
        cy.login('student');
        cy.visit(['sample', 'chat']);
        checkChatExists(title1);
        cy.logout();
        cy.login('instructor');
        cy.visit(['sample', 'chat']);
        // Now check for chatroom removal after ending session
        endChatSession(title1);
        cy.logout();
        cy.login('student');
        cy.visit(['sample', 'chat']);
        checkChatExists(title1, false);
    });

    it('Should test starting chat sessions and chatting', () => {
        // Create Chatroom with chat messages in it
        createChatroom(title1, description1, false);
        startChatSession(title1);
        getChatroom(title1).find('[data-testid="chat-join-btn"]').click();
        sendChatMessage(msgText1, name1);
        sendChatMessage(msgText3, name1);
        // Check for leave chat button, check for chat state from other user
        cy.get('[data-testid="leave-chat"]').click();
        cy.logout();
        cy.login('student');
        cy.visit(['sample', 'chat']);
        checkChatExists(title1);
        checkDescription(title1, description1);
        checkHost(title1, name1);
        getChatroom(title1);
        // Add new messages, check for chat message
        getChatroom(title1).find('[data-testid="chat-join-btn"]').click();
        checkChatMessage(msgText3, name1);
        sendChatMessage(msgText2, name2);
        // Check for message existence after reload, check that clicking enter sends a message
        cy.reload();
        checkChatMessage(msgText2, name2);
        sendChatMessageEnter(msgText3, name2);
        cy.get('[data-testid="leave-chat"]').click();
    });

    it('Should test anonymity', () => {
        // Check for basic anonymous chat functions
        createChatroom(title1, description1, true);
        startChatSession(title1);
        getChatroom(title1).find('[data-testid="anon-chat-join-btn"]').click();
        sendChatMessage(msgText1, 'Anonymous');
        sendChatMessage(msgText3, 'Anonymous');
        const instructorAnon = getAnonName();
        // Check that messages are still anonymous after leaving and rejoining, with the same anon name as before.
        cy.get('[data-testid="leave-chat"]').click();
        getChatroom(title1).find('[data-testid="anon-chat-join-btn"]').click();
        checkChatMessage(msgText3, instructorAnon);
        // Check for this even when rejoining non-anon
        cy.get('[data-testid="leave-chat"]').click();
        getChatroom(title1).find('[data-testid="chat-join-btn"]').click();
        checkChatMessage(msgText3, instructorAnon);
        sendChatMessage(msgText3, name1);
        // Check that the student sees the non-anon name
        cy.logout();
        cy.login('student');
        cy.visit(['sample', 'chat']);
        getChatroom(title1).find('[data-testid="chat-join-btn"]').click();
        checkChatMessage(msgText3, name1);
        sendChatMessage(msgText2, name2);
        cy.get('[data-testid="leave-chat"]').click();
        // Check for student anonymous function
        getChatroom(title1).find('[data-testid="anon-chat-join-btn"]').click();
        checkChatMessage(msgText2, name2);
        sendChatMessage(msgText2, 'Anonymous');
        const studentAnon = getAnonName();
        // expect(instructorAnon !== studentAnon); TODO: Add all anon names in a chat to a list so that it's impossible to have two equivalent names.
        cy.get('[data-testid="leave-chat"]').click();
        // Login to instructor, check for correct anonymous name and for anonymous name remaining across logins
        cy.logout();
        cy.login('instructor');
        cy.visit(['sample', 'chat']);
        getChatroom(title1).find('[data-testid="anon-chat-join-btn"]').click();
        checkChatMessage(msgText2, studentAnon);
        sendChatMessage(msgText3, instructorAnon);
        cy.get('[data-testid="leave-chat"]').click();
        deleteChatroom(title1);
    });

    it('Should test socket functionality', () => {
        let id = NaN;
        createChatroom(title1, description1, true);
        startChatSession(title1);
        getChatroom(title1).then(($chatroom) => {
            id = Number($chatroom.attr('id'));
            expect(id).to.be.a('number');
            cy.visit(['sample', 'chat', id]);
        });

        // Add the message via a request
        getApiKey('instructor', 'instructor').then((key) => {
            cy.request({
                method: 'POST',
                url: `${Cypress.config('baseUrl')}/api/courses/${getCurrentSemester()}/sample/chat/${id}/send/anonymous`,
                body: {
                    content: 'check',
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
                checkChatMessage('check', name1);
            });
        });
        deleteChatroom(title1);
    });
});
