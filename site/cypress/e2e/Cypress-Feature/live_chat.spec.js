const title1 = "Test Chatroom Title";
const title2 = "Non Anon Test Chatroom Title"
const description1 = "Test Description"
const description2 = "Non Anon Test Description"

const getChatroom = (title) => {
    return cy.get('[data-testid="chatroom-item"]').filter((index, el) => {
        return Cypress.$(el).find('[data-testid="chatroom-title"]').text().trim() === title;
    }).first();
}

const startChatSession = (title) => {
    chatroomExists(title).then((exists) => {
        if (exists) {
            getChatroom(title).find('[data-testid="enable-chatroom"]').first().click();
        }
    });
}

const endChatSession = (title) => {
    chatroomExists(title).then((exists) => {
        if (exists) {
            getChatroom(title).find('[data-testid="disable-chatroom"]').first().click();
        }
    })
}

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
            return $rows.filter((index, el) => {
                return Cypress.$(el).find('[data-testid="chatroom-title"]').text().trim() === title;
            }).length > 0;
        });
    });
}

const deleteChatroom = (title) => {
    cy.reload();
    chatroomExists(title).then((exists) => {
        if (exists) {
            cy.get('body').then(($body) => {
                const chatroom = $body.find(`[data-testid="chatroom-item"]:has([data-testid="chatroom-title"]:contains("${title}"))`);
                const disableBtn = chatroom.find('[data-testid="disable-chatroom"]');
                if (disableBtn.length > 0) {
                    cy.window().then((win) => {
                        cy.stub(win, 'confirm').callsFake((confirmText) => {
                            expect(confirmText).to.contain('close the chatroom');
                            return true;
                        });
                    });
                    
                    getChatroom(title).find('[data-testid="disable-chatroom"]').first().click();
                }
            }).then(() => {
                cy.window().then((win) => {
                    cy.stub(win, 'confirm').callsFake((confirmText) => {
                        expect(confirmText).to.contain('delete chatroom');
                        return true;
                    });
                });
                
                getChatroom(title).find('[data-testid="delete-chatroom"]').first().click();
                deleteChatroom(title);
            });
        }
    });
}

const checkTitle = (title) => {
    expect(chatroomExists(title) > 0);
}

const checkDescription = (title, description) => {
    getChatroom(title).find('[data-testid="chatroom-description"]').then(($el) => {
        expect($el.text().trim() == description);
    })
}

const checkAnon = (title, expectedAnon) => {
    getChatroom(title).then($chatroom => {
        cy.wrap($chatroom).find('[data-testid="chat-join-btn"]').should('exist');
        let exist = expectedAnon ? 'exist' : 'not.exist';
        cy.wrap($chatroom).find('[data-testid="anon-chat-join-btn"]').should(exist);
    });
}

const createChatroom = (title, description, isAnon) => {
    cy.get('[data-testid="new-chatroom-btn"]').should('exist').click().then(() => {
        cy.get('[data-testid="chatroom-name-entry"]').should('exist').type(title, { force: true });
        cy.get('[data-testid="chatroom-description-entry"]').should('exist').type(description, { force: true });
        if (!isAnon){
            cy.get('[data-testid="enable-disable-anon"]').should('exist').click();
        }
        cy.get('[data-testid="submit-chat-creation"]').should('exist').click({ force: true }).then(() => {
            checkTitle(title);
            checkDescription(title, description);
            checkAnon(title, isAnon);
        })
    });
}

const editChatroom = (oldTitle, newTitle, newDescription, toggleAnon, expectedAnon) => {
    cy.reload();
    chatroomExists(oldTitle).then((exists) => {
        if (exists) {
            getChatroom(oldTitle).find('[data-testid="edit-chatroom"]').first().click().then(() => {
                cy.get('[data-testid="chatroom-name-edit"]').should('exist').clear({force : true}).type(newTitle, { force: true });
                cy.get('[data-testid="chatroom-description-edit"]').should('exist').clear({force : true}).type(newDescription, { force: true });
                if (toggleAnon) {
                    cy.get('[data-testid="edit-anon"]').should('exist').click();
                }
                cy.get('[data-testid="submit-chat-edit"]').should('exist').click({ force: true }).then(() => {
                    checkTitle(newTitle);
                    checkDescription(newTitle, newDescription);
                    checkAnon(newTitle, expectedAnon);
                })
            });
           
        }
    });
}

const sendChatMessage = (text) => {
    cy.get('[data-testid="msg-input"]').should('exist').type(text).then(() => {
        cy.get('[data-testid="send-btn"]').should('exist').click().then(() => {
            cy.find('[data-testid="message-container"]').last();
        });
    });
}

describe('Tests for enabling Live Chat', () => {
    beforeEach(() => {
        cy.login('instructor');
    })

    it('Should enable the Live Chat feature for the sample course', () => {
        toggleLiveChat(true).then(() => {
            cy.visit(['sample', 'chat']);
            cy.get('[data-testid="new-chatroom-btn"]').should('exist');
        }).then(() => {
            toggleLiveChat(false).then(() => {
                cy.visit(['sample', 'chat']);
                cy.get('[data-testid="new-chatroom-btn"]').should('not.exist');
            });
        });
    });
    
    it('Should test creating new chats, allowing and disallowing anonymous participants.', () => {
        toggleLiveChat(true).then(() => {
            cy.visit(['sample', 'chat']);
            deleteChatroom(title1);
            deleteChatroom(title2);
            createChatroom(title1, description1, true);
            createChatroom(title2, description2, false);
            deleteChatroom(title1);
            deleteChatroom(title2);
            toggleLiveChat(false).then(() => {
                cy.reload();
            });
        });
    });

    it('Should test editing chats', () => {
        toggleLiveChat(true).then(() => {
            cy.visit(['sample', 'chat']);
            deleteChatroom(title1);
            createChatroom(title1, description1, true);
            editChatroom(title1, title2, description2, true, false);
            deleteChatroom(title2);
            toggleLiveChat(false).then(() => {
                cy.reload();
            });
        });
    });

    it('Should test starting chat sessions and chatting', () => {
        toggleLiveChat(true).then(() => {
            cy.visit(['sample', 'chat']);
            deleteChatroom(title1);
            createChatroom(title1, description1, false);
            startChatSession(title1);
            cy.get('[data-testid="chat-join-btn"]').click();
            sendChatMessage("Test");
        });
    });

    it('Should test anonymity', () => {

    })
});