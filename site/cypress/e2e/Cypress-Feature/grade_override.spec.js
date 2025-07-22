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
            if (gradeable !== 'grading_homework') {
                cy.get('[data-testid="confirm-team-override"]').click();
            }
            cy.get('[data-testid="popup-message"]').should('contain', `Updated overridden Grades for ${gradeable}`);
            cy.get('[data-testid="load-overridden-grades"]').should('contain', `Overridden Grades for ${gradeable}`);
            cy.get('#grade-override-table thead tr th').as('headers');
            cy.get('@headers').eq(0).should('have.text', 'Student ID');
            cy.get('@headers').eq(1).should('have.text', 'Given Name');
            cy.get('@headers').eq(2).should('have.text', 'Family Name');
            cy.get('@headers').eq(3).should('have.text', 'Marks');
            cy.get('@headers').eq(4).should('have.text', 'Comments');
            cy.get('@headers').eq(5).should('have.text', 'Delete');

            cy.get('#grade-override-table tbody tr').first().within(() => {
                cy.get('td').eq(0).should('contain', 'student');
                cy.get('td').eq(1).should('contain', 'Joe');
                cy.get('td').eq(2).should('contain', 'Student');
                cy.get('td').eq(3).should('contain', '10');
            });

            // Only test grades page for non-team gradeables because it does not yet show team overrides
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
