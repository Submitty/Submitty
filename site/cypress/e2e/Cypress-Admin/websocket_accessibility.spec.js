import {
    buildUrl,
    getCurrentSemester,
} from '../../support/utils.js';

const defaultWebSocketPages = [
    'discussion_forum',
    'office_hours_queue',
    'chatrooms',
];

describe('Tests for WebSocket accessibility', () => {
    before(() => {
        // Enable Live Chat
        cy.login('instructor');
        cy.visit(['sample', 'config']);
        cy.get('#chat-enabled').check();
        cy.logout();
    });

    beforeEach(() => {
        cy.login('instructor');
    });

    afterEach(() => {
        cy.logout();
    });

    after(() => {
        // Disable Live Chat
        cy.login('instructor');
        cy.visit(['sample', 'config']);
        cy.get('#chat-enabled').uncheck();
        cy.logout();
    });

    it('Should generate websocket token for basic pages', () => {
        defaultWebSocketPages.forEach((page) => {
            cy.visit(['sample', page]);
            // Banner is displayed on WS server failure or authentication failed
            cy.get('#socket-server-system-message').should('be.hidden');
        });
    });

    it('Should generate correct token for simple grading page', () => {
        cy.visit(['sample', 'gradeable', 'grading_lab', 'grading']);
        cy.get('#socket-server-system-message').should('be.hidden');
    });

    it('Should generate correct token for grade inquiry page', () => {
        // TODO: utils file for grade inquiry page
        const gradeableId = 'grades_released_homework';
        cy.visit(['sample', 'gradeable', gradeableId, 'grading', 'details']);

        // const users = ['beahaf', 'ta2', 'instructor'];
        // users.forEach((user) => {
        //     cy.login(user);
        //     cy.visit(['sample', 'gradeable', 'grades_released_homework']);
        //     cy.get('#socket-server-system-message').should('be.hidden');
        // });
    });


});
