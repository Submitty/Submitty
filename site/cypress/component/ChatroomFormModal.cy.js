import ChatroomFormModal from '../../vue/src/components/chat/ChatroomFormModal.vue';
import { mountWithEmitSpy } from '../support/component_test_utils.js';

const editChatroom = {
    title: 'Office Hours',
    description: 'General Q&A',
    isAllowAnon: true,
    allowReadOnlyAfterEnd: false,
};

describe('ChatroomFormModal', () => {
    describe('create mode', () => {
        it('renders with empty fields when visible in create mode', () => {
            cy.mount(ChatroomFormModal, { props: { visible: true, mode: 'create' } });
            cy.get('[data-testid="chatroom-name-entry"]').should('have.value', '');
            cy.get('[data-testid="chatroom-description-entry"]').should('have.value', '');
            cy.get('[data-testid="enable-disable-anon"]').should('be.checked');
            cy.get('[data-testid="edit-read-only"]').should('not.be.checked');
        });
    });

    describe('edit mode', () => {
        it('pre-fills fields from the chatroom prop', () => {
            cy.mount(ChatroomFormModal, { props: { visible: true, mode: 'edit', chatroom: editChatroom } });
            cy.get('[data-testid="chatroom-name-edit"]').should('have.value', 'Office Hours');
            cy.get('[data-testid="chatroom-description-edit"]').should('have.value', 'General Q&A');
            cy.get('[data-testid="edit-anon"]').should('be.checked');
            cy.get('[data-testid="edit-read-only"]').should('not.be.checked');
        });

        it('renders the edit title', () => {
            cy.mount(ChatroomFormModal, { props: { visible: true, mode: 'edit', chatroom: editChatroom } });
            cy.contains('h1', 'Edit Chatroom').should('be.visible');
        });

        it('shows empty fields when chatroom is null in edit mode', () => {
            cy.mount(ChatroomFormModal, { props: { visible: true, mode: 'edit', chatroom: null } });
            cy.get('[data-testid="chatroom-name-edit"]').should('have.value', '');
            cy.get('[data-testid="chatroom-description-edit"]').should('have.value', '');
        });
    });

    describe('visibility', () => {
        it('does not render when visible is false', () => {
            cy.mount(ChatroomFormModal, { props: { visible: false, mode: 'create' } });
            cy.get('[data-testid="popup-window"]').should('not.exist');
        });
    });

    describe('emit events', () => {
        it('emits close when Discard button is clicked', () => {
            cy.mount(ChatroomFormModal, { props: { visible: true, mode: 'create', onClose: cy.stub().as('onClose') } });
            cy.get('[data-testid="popup-close-button"]').click();
            cy.get('@onClose').should('have.callCount', 1);
        });

        it('emits save with form data on Submit', () => {
            mountWithEmitSpy(ChatroomFormModal, 'save', { visible: true, mode: 'create' }, 'saveHandler');
            cy.get('[data-testid="chatroom-name-entry"]').type('New Chat');
            cy.get('[data-testid="chatroom-description-entry"]').type('Description here');
            cy.get('[data-testid="popup-save-button"]').click();
            cy.get('@saveHandler').should('have.been.calledWith', {
                title: 'New Chat',
                description: 'Description here',
                allowAnon: true,
                allowReadOnlyAfterEnd: false,
            });
        });

        it('emits save with checkbox changes reflected', () => {
            mountWithEmitSpy(ChatroomFormModal, 'save', { visible: true, mode: 'create' }, 'saveHandler');
            cy.get('[data-testid="chatroom-name-entry"]').type('Test');
            cy.get('[data-testid="enable-disable-anon"]').uncheck();
            cy.get('[data-testid="edit-read-only"]').check();
            cy.get('[data-testid="popup-save-button"]').click();
            cy.get('@saveHandler').should('have.been.calledWith', {
                title: 'Test',
                description: '',
                allowAnon: false,
                allowReadOnlyAfterEnd: true,
            });
        });

        it('emits save with edited data in edit mode', () => {
            mountWithEmitSpy(ChatroomFormModal, 'save', { visible: true, mode: 'edit', chatroom: editChatroom }, 'saveHandler');
            cy.get('[data-testid="chatroom-name-edit"]').clear();
            cy.get('[data-testid="chatroom-name-edit"]').type('Updated Title');
            cy.get('[data-testid="popup-save-button"]').click();
            cy.get('@saveHandler').should('have.been.calledWith', {
                title: 'Updated Title',
                description: 'General Q&A',
                allowAnon: true,
                allowReadOnlyAfterEnd: false,
            });
        });
    });
});
