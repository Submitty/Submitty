

const title1 = "Test Chatroom Title";
const title2 = "Non Anon Test Chatroom Title"
const description1 = "Test Description"
const description2 = "Non Anon Test Description"

const chatroomExists = (title) => {
    return cy.get('[data-testid="chatroom-list-item"]').then(($thread_items) => {
        return $thread_items.filter(`:contains(${title})`).length > 0;
    });
}

const deleteChatroom = (title) => {
    cy.reload();
    chatroomExists(title).then((exists) => {
        if (exists) {
            cy.get('[data-testid="chatroom-list-item"]').filter(`:contains(${title})`).first().find('[data-testid="delete-chatroom"]').first().click();
            cy.on('window:confirm', (confirmText) => {
                expect(confirmText).to.contain('delete chatroom');
                return true;
            });
            deleteChatroom(title);
        }
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
            cy.get('[data-testid="chat-join-btn"]').first().should('be.visible');
            let visible = 'be.visible';
            if (!isAnon){
                visible = 'not.be.visible';
            }
            cy.get('[data-testid="anon-chat-join-btn"]').first().should(visible);
        })
    });
}

describe('Tests for enabling Live Lecture Chat', () => {
    
    beforeEach(() => {
        cy.login('instructor');
        
        
    })

    it('Should enable the Live Lecture chat feature for the sample course', () => {
        cy.visit(['sample', 'config']);
        cy.get('[id="chat-enabled"]').first().should('exist').click().then(() => {
            cy.visit(['sample', 'chat']);
            cy.get('[data-testid="new-chatroom-btn"]').should('exist');
        });
    });
    
    it('Should test creating new chats, allowing and disallowing anonymous participants.', () => {
        cy.visit(['sample', 'chat']);
        deleteChatroom(title1);
        deleteChatroom(title2);
        createChatroom(title1, description1, true);
        createChatroom(title2, description2, false);
        
        
    })
});