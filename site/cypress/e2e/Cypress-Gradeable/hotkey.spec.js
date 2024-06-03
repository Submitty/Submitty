/// <reference types="Cypress" />
describe('TA grading hotkey testing', () => {
    it('toggle keyboard shortcut', () => {
        cy.login();
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);
        cy.get('[data-testid="view-sections"]').click();
        cy.get('[data-testid="grade-button"]').eq(12).click();
        cy.get('[data-testid="grading-panel-header"]').as('navigationBar');
        cy.get('@navigationBar').type('{A}');
        cy.get('[data-testid="autograding-results"]').should('contain', 'Autograding Testcases');
        cy.get('@navigationBar').type('{G}');
        cy.get('[data-testid="grading-rubric"]').should('contain', 'Grading Rubric');
        cy.get('@navigationBar').type('{O}');
        cy.get('[data-testid="submission-browser"]').should('contain', 'Submissions and Results Browser');
        cy.get('@navigationBar').type('{S}');
        cy.get('[data-testid="student-info"]').should('contain', 'Student Information');
        cy.get('@navigationBar').type('{X}');
        cy.get('[data-testid="grade-inquiry-inner-info"]').should('contain', 'Grade Inquiry');
        cy.get('@navigationBar').type('{T}');
        cy.get('[data-testid="solution-ta-notes"]').should('contain', 'Solution/TA Notes');

    });
});
