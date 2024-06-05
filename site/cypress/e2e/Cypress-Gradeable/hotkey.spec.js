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
        cy.get('#edit-mode-enabled').should('not.be.checked');
        cy.get('[data-testid="grading-rubric"]').type('{E}');
        cy.get('#edit-mode-enabled').should('be.checked');
        cy.get('@navigationBar').type('{O}');
        cy.get('[data-testid="submission-browser"]').should('contain', 'Submissions and Results Browser');
        cy.get('@navigationBar').type('{S}');
        cy.get('[data-testid="student-info"]').should('contain', 'Student Information');
        cy.get('@navigationBar').type('{X}');
        cy.get('[data-testid="grade-inquiry-inner-info"]').should('contain', 'Grade Inquiry');
        cy.get('@navigationBar').type('{T}');
        cy.get('[data-testid="solution-ta-notes"]').should('contain', 'Solution/TA Notes');
        cy.visit(['sample', 'gradeable', 'grading_homework', 'update']);
        // adding the peer grading panel, discussion panel, notebook panel
        cy.get('[data-testid="yes-discussion"]').then(($checkbox) => {
            if (!$checkbox.prop('checked')) {
                cy.get('[data-testid="yes-discussion"]').check();
            }
        });
        cy.get('[data-testid="yes-discussion"]').should('be.checked');
        cy.get('[data-testid="discussion-thread-id"]').type('1');
        cy.get('[data-testid="page-1-nav"]').click();
        cy.get('[data-testid="container-rubric"]').contains('Start New').click();
        cy.get('[data-testid="page-2-nav"]').click();
        cy.get('[data-testid="gradeable-rubric"]').contains('Add New Peer Component').click();
        cy.get('@navigationBar').type('{D}');
        cy.get('[data-testid="posts-list"]').should('contain', 'Discussion Posts').and('contain', ' Go to thread');
        cy.get('@navigationBar').type('{P}');
        cy.get('[data-testid="peer-info"]').should('contain', 'Peer Grading');
        cy.get('@navigationBar').type('{N}');
        cy.get('[data-testid="notebook-view"]').should('contain', 'Peer Grading');

    });
});
