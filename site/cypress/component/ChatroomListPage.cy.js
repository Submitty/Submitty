import ChatroomListPage from '../../vue/src/pages/ChatroomListPage.vue';
import { mountWithEmitSpy } from '../support/component_test_utils.js';

const baseUrl = '/courses/s26/sample/chat';

const chatrooms = [
    { id: 1, title: 'Office Hours', description: 'General Q&A', hostName: 'User1', isAllowAnon: true, isActive: true, isReadOnly: false, allowReadOnlyAfterEnd: true },
    { id: 2, title: 'Exam Review', description: 'Final exam Q&A session', hostName: 'User2', isAllowAnon: false, isActive: false, isReadOnly: false, allowReadOnlyAfterEnd: false },
];

describe('ChatroomListPage', () => {
    describe('admin view', () => {
        it('renders New Chatroom button for admin', () => {
            cy.mount(ChatroomListPage, { props: { chatrooms, userAdmin: true, baseUrl } });
            cy.get('[data-testid="new-chatroom-btn"]').should('be.visible');
        });

        it('renders all chatrooms with action columns', () => {
            cy.mount(ChatroomListPage, { props: { chatrooms, userAdmin: true, baseUrl } });
            cy.get('[data-testid="chatroom-item"]').should('have.length', 2);
            cy.get('[data-testid="edit-chatroom"]').should('have.length', 2);
            cy.get('[data-testid="clear-chatroom"]').should('have.length', 2);
        });

        it('shows delete only for inactive chatrooms', () => {
            cy.mount(ChatroomListPage, { props: { chatrooms, userAdmin: true, baseUrl } });
            // Chatroom 1 is active, chatroom 2 is inactive
            cy.get('[data-testid="delete-chatroom"]').should('have.length', 1);
        });

        it('shows End Session for active and Start Session for inactive', () => {
            cy.mount(ChatroomListPage, { props: { chatrooms, userAdmin: true, baseUrl } });
            cy.get('[data-testid="disable-chatroom"]').should('have.length', 1);
            cy.get('[data-testid="enable-chatroom"]').should('have.length', 1);
        });

        it('opens create modal from New Chatroom button', () => {
            cy.mount(ChatroomListPage, { props: { chatrooms, userAdmin: true, baseUrl } });
            cy.get('[data-testid="new-chatroom-btn"]').click();
            cy.contains('h1', 'Create Chatroom').should('be.visible');
        });

        it('opens edit modal with pre-filled data', () => {
            cy.mount(ChatroomListPage, { props: { chatrooms, userAdmin: true, baseUrl } });
            cy.get('[data-testid="edit-chatroom"]').first().click({ force: true });
            cy.contains('h1', 'Edit Chatroom').should('be.visible');
            cy.get('[data-testid="chatroom-name-edit"]').should('have.value', 'Office Hours');
        });

        it('closes modal on Discard', () => {
            cy.mount(ChatroomListPage, { props: { chatrooms, userAdmin: true, baseUrl } });
            cy.get('[data-testid="new-chatroom-btn"]').click();
            cy.get('[data-testid="popup-close-button"]').click();
            cy.get('[data-testid="popup-window"]').should('not.exist');
        });
    });

    describe('student view', () => {
        it('does not render New Chatroom button', () => {
            cy.mount(ChatroomListPage, { props: { chatrooms, userAdmin: false, baseUrl } });
            cy.get('[data-testid="new-chatroom-btn"]').should('not.exist');
        });

        it('renders only accessible chatrooms', () => {
            cy.mount(ChatroomListPage, { props: { chatrooms, userAdmin: false, baseUrl } });
            // Chatroom 2 is inactive without allowReadOnlyAfterEnd
            cy.get('[data-testid="chatroom-item"]').should('have.length', 1);
            cy.get('[data-testid="chatroom-title"]').should('contain', 'Office Hours');
        });

        it('does not show admin columns', () => {
            cy.mount(ChatroomListPage, { props: { chatrooms, userAdmin: false, baseUrl } });
            cy.get('[data-testid="edit-chatroom"]').should('not.exist');
            cy.get('[data-testid="clear-chatroom"]').should('not.exist');
        });

        it('shows host name', () => {
            cy.mount(ChatroomListPage, { props: { chatrooms, userAdmin: false, baseUrl } });
            cy.get('[data-testid="chatroom-host"]').should('contain', 'Dr. Smith');
        });
    });

    describe('emit events', () => {
        it('emits delete-chatroom when delete is clicked', () => {
            mountWithEmitSpy(ChatroomListPage, 'deleteChatroom', { chatrooms, userAdmin: true, baseUrl }, 'onDelete');
            cy.get('[data-testid="delete-chatroom"]').click({ force: true });
            cy.get('@onDelete').should('have.callCount', 1);
        });

        it('emits clear-chatroom with the chatroom data', () => {
            mountWithEmitSpy(ChatroomListPage, 'clearChatroom', { chatrooms, userAdmin: true, baseUrl }, 'onClear');
            cy.get('[data-testid="clear-chatroom"]').first().click();
            cy.get('@onClear').should('have.been.calledWith', chatrooms[0]);
        });

        it('emits toggle-chatroom when Start Session is clicked', () => {
            mountWithEmitSpy(ChatroomListPage, 'toggleChatroom', { chatrooms, userAdmin: true, baseUrl }, 'onToggle');
            cy.get('[data-testid="enable-chatroom"]').click();
            cy.get('@onToggle').should('have.callCount', 1);
        });

        it('emits create-chatroom with form data on modal submit', () => {
            mountWithEmitSpy(ChatroomListPage, 'createChatroom', { chatrooms, userAdmin: true, baseUrl }, 'onCreate');
            cy.get('[data-testid="new-chatroom-btn"]').click();
            cy.get('[data-testid="chatroom-name-entry"]').type('New Room');
            cy.get('[data-testid="popup-save-button"]').click();
            cy.get('@onCreate').should('have.been.calledWith', {
                title: 'New Room',
                description: '',
                allowAnon: true,
                allowReadOnlyAfterEnd: false,
            });
        });

        it('emits edit-chatroom with id and data on modal submit in edit mode', () => {
            mountWithEmitSpy(ChatroomListPage, 'editChatroom', { chatrooms, userAdmin: true, baseUrl }, 'onEdit');
            cy.get('[data-testid="edit-chatroom"]').first().click({ force: true });
            cy.get('[data-testid="chatroom-name-edit"]').clear();
            cy.get('[data-testid="chatroom-name-edit"]').type('Updated Room');
            cy.get('[data-testid="popup-save-button"]').click();
            cy.get('@onEdit').should('have.been.calledWith', {
                id: 1,
                title: 'Updated Room',
                description: 'General Q&A',
                allowAnon: true,
                allowReadOnlyAfterEnd: true,
            });
        });
    });

    describe('empty state', () => {
        it('renders nothing when no chatrooms exist', () => {
            cy.mount(ChatroomListPage, { props: { chatrooms: [], userAdmin: true, baseUrl } });
            cy.get('[data-testid="chatroom-item"]').should('not.exist');
            cy.get('[data-testid="new-chatroom-btn"]').should('be.visible');
        });
    });
});
