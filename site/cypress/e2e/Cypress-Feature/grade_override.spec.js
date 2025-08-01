/// <reference types= "cypress" />

function checkGradeRow(user_id, expected) {
    cy.get(`[data-testid="grade-row-${user_id}"]`).within(() => {
        cy.get('[data-testid="student-id"]').should('contain', expected.user_id);
        cy.get('[data-testid="given-name"]').should('contain', expected.given);
        cy.get('[data-testid="family-name"]').should('contain', expected.family);
        cy.get('[data-testid="marks"]').should('contain', expected.marks);
    });
}

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
            cy.get('#grade-override-table thead [data-testid="student-id"]').should('have.text', 'Student ID');
            cy.get('#grade-override-table thead [data-testid="given-name"]').should('have.text', 'Given Name');
            cy.get('#grade-override-table thead [data-testid="family-name"]').should('have.text', 'Family Name');
            cy.get('#grade-override-table thead [data-testid="marks"]').should('have.text', 'Marks');
            cy.get('#grade-override-table thead [data-testid="comments"]').should('have.text', 'Comments');
            cy.get('#grade-override-table thead [data-testid="delete"]').should('have.text', 'Delete');

            checkGradeRow('student', {
                user_id: 'student',
                given: 'Joe',
                family: 'Student',
                marks: '10',
            });

            if (gradeable !== 'grading_homework') {
                checkGradeRow('wisoza', {
                    user_id: 'wisoza',
                    given: 'Adela',
                    family: 'Wisozk',
                    marks: '10',
                });
            }

            // Only test grades page for non-team gradeables because team overrides are not yet shown there.
            if (gradeable === 'grading_homework') {
                cy.visit(['sample', 'gradeable', gradeable, 'grading', 'details']);
                cy.get('[data-testid="view-sections"]').click();
                cy.get('[data-testid="grade-button"]').eq(13).should('contain', 'Overridden');
                cy.get('[data-testid="grade-table"]').eq(13).should('contain', 'Overridden');
                cy.get('[data-testid="grade-button"]').eq(13).click();
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
