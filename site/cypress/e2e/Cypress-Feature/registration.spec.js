import { getFullCurrentSemester } from '../../support/utils';

const openMessage = `The course testing for ${getFullCurrentSemester()} is open to self registration`;
const selectMessage = 'You may select below to add yourself to the course.';
const notifiedMessage = 'Your instructor will be notified and can then choose to keep you in the course.';

const no_access_message = "You don't have access to this course.";

describe('Tests for self registering for courses', () => {
    before(() => {
        cy.login();
        cy.visit(['testing', 'config']);
        cy.get('[data-testid="enable-self-registration"]').uncheck();
        cy.get('[data-testid="enable-self-registration"]').should('not.be.checked');
        cy.get('[data-testid="default-section-id"]').select('1');
        cy.logout();
    });
    it('Should enable self registration, and allow user to register for courses.', () => {
        cy.login('gutmal');
        cy.visit();
        cy.get('[data-testid="courses-list"').should('not.contain', 'Courses Available for Self Registration');
        cy.visit(['testing']);
        cy.get('[data-testid="no-access-message"]').should('contain', no_access_message);
        cy.logout();
        cy.login();
        cy.visit(['testing', 'config']);
        cy.get('[data-testid="enable-self-registration"]').check();
        cy.get('[data-testid="enable-self-registration"]').should('be.checked');
        cy.get('[data-testid="default-section-id"]').select('5');
        cy.logout();
        cy.login('gutmal');
        cy.visit();
        cy.get('[data-testid="courses-list"').should('contain', 'Courses Available for Self Registration');
        cy.get('[data-testid="testing-button"]').click();
        cy.get('[data-testid="no-access-message"]').should('contain', openMessage)
            .and('contain', selectMessage)
            .and('contain', notifiedMessage);
        cy.get('[data-testid="register-button"]').click();
        cy.get('[data-testid="open_homework-row"]').should('exist');
        cy.visit();
        cy.get('[data-testid="testing-button"]').should('contain', 'Section 5');
    });
});
