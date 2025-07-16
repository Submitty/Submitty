/// <reference types= "cypress" />
describe('testing grade override', () => {
    it('individual student and team grade override', () => {
        cy.login();
        ['grading_homework', 'grading_team_homework'].forEach((gradeable) => {
            cy.visit(['sample', 'grade_override']);
            const selectGradeable = (gradeable === 'grading_homework') ? 'Grading Homework' : 'Grading Team Homework';
            cy.get('[data-testid="grade-override-message-box"]').should('contain', 'No gradeable has been selected');
            cy.get('[data-testid="grade-override-select-gradeable"]').select(selectGradeable);
            cy.get('[data-testid="student-grade-override"]').click();
            cy.get('[data-testid="student-grade-override"]').type('student');
            cy.get('[data-testid="grade-override-score"]').click();
            cy.get('[data-testid="grade-override-score"]').type('10');
            cy.get('[data-testid="grade-override-submit"]').click();
            if (!(gradeable === 'grading_homework')) {
                cy.get('[data-testid="confirm-team-override"]').click();
            }
            cy.get('[data-testid="popup-message"]').should('contain', `Updated overridden Grades for ${gradeable}`);
            cy.get('[data-testid="load-overridden-grades"]').should('contain', 'student');
            cy.get('[data-testid="load-overridden-grades"]').should('contain', `Overridden Grades for ${gradeable}`);
            // When the same functionaliy for teams added,then remove the if block with adding some additional test
            if (gradeable === 'grading_homework') {
                cy.visit(['sample', 'gradeable', gradeable, 'grading', 'details']);
                cy.get('[data-testid="view-sections"]').click();
                cy.get('[data-testid="grade-button"]').eq(12).should('contain', 'Overridden');
                cy.get('[data-testid="grade-table"]').eq(12).should('contain', 'Overridden');
                cy.get('[data-testid="grade-button"]').eq(12).click();
                cy.get('[data-testid="bar-banner"]').should('contain', 'Overridden grades');
            }
            cy.visit(['sample', 'grade_override']);
            cy.get('[data-testid="grade-override-select-gradeable"]').select(selectGradeable);
            if (gradeable === 'grading_homework') {
                cy.get('[data-testid="grade-override-delete"]').click();
            }
            else {
                cy.get('[data-testid="grade-override-delete"]').first().click();
            }
            cy.get('[data-testid="popup-message"]').should('contain', 'Overridden Grades deleted.');
        });
    });
});
