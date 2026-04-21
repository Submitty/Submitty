/// <reference types="cypress" />
import ChatMessage from './ChatMessage.vue';

describe('<ChatMessage />', () => {
    it('renders a simple student message correctly', () => {
        cy.mount(ChatMessage, {
            props: {
                id: '123',
                displayName: 'Alice Student',
                role: 'student',
                timestamp: '10:00 AM',
                content: 'Hello everyone!',
            },
        });

        cy.get('[data-testid="sender-name"]').should('have.text', 'Alice Student');
        cy.get('[data-testid="message-content"]').should('have.text', 'Hello everyone!');
        cy.get('[data-testid="message-container"]').should('not.have.class', 'admin-message');
    });

    it('appends role to the name for non-students and adds styling for instructors', () => {
        cy.mount(ChatMessage, {
            props: {
                id: '456',
                displayName: 'Bob Prof',
                role: 'instructor',
                timestamp: '10:01 AM',
                content: 'Class starts now.',
            },
        });

        cy.get('[data-testid="sender-name"]').should('have.text', 'Bob Prof [instructor]');
        cy.get('[data-testid="message-container"]').should('have.class', 'admin-message');
    });

    it('appends role for graders but does not add instructor styling', () => {
        cy.mount(ChatMessage, {
            props: {
                id: '789',
                displayName: 'Charlie TA',
                role: 'grader',
                timestamp: '10:02 AM',
                content: 'Don\'t forget your homework.',
            },
        });

        cy.get('[data-testid="sender-name"]').should('have.text', 'Charlie TA [grader]');
        cy.get('[data-testid="message-container"]').should('not.have.class', 'admin-message');
    });

    it('does not append role if displayName starts with Anonymous', () => {
        cy.mount(ChatMessage, {
            props: {
                id: '999',
                displayName: 'Anonymous Zebra',
                role: 'instructor',
                timestamp: '10:05 AM',
                content: 'Should stay anonymous.',
            },
        });

        // The name should just be 'Anonymous Zebra', NO role attached!
        cy.get('[data-testid="sender-name"]').should('have.text', 'Anonymous Zebra');
        // BUT it should still have the admin-message class!
        cy.get('[data-testid="message-container"]').should('have.class', 'admin-message');
    });
});
