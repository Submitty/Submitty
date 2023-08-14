describe('Test cases for grading stats', () => {
    ['instructor','ta','grader'].forEach((user) => {
        beforeEach(() => {
            cy.login(user);
            cy.visit(['sample']);
        });
        it(`${user} view should be accurate for teams.`, () => {
            cy.get('a[href*="/sample/gradeable/grading_team_homework/grading/details"]').click();
            cy.get('a[href*="/sample/gradeable/grading_team_homework/grading/status"]').click();
            cy.get('#filters').click();
            cy.get('#bad_submissions').click();
            cy.get('#apply_button').click();

            const text = cy.get('#left-grading-stats');
            text.should('contain', 'Students on a team: 101/101 (100%)');
            text.should('contain', 'Number of teams: 36');
            text.should('contain', 'Teams who have submitted: 27 / 36 (75%)');
            text.should('contain', 'Section 1: 2 / 4 (50.0%)');
        });

        it(`${user} view should be accurate for grades.`, () => {
            cy.get('a[href*="/sample/gradeable/grading_homework/grading/details"]').click();
            cy.get('a[href*="/sample/gradeable/grading_homework/grading/status"]').click();
            cy.get('#filters').click();
            cy.get('#bad_submissions').click();
            cy.get('#apply_button').click();

            const text = cy.get('#left-grading-stats').should('exist');
            text.should('contain', 'Students who have submitted: 68 / 101 (67.3%)');
            text.should('contain', 'Current percentage of TA grading done: 33 / 68 (48.5%)');
            text.should('contain', 'Section 1: 4 / 9 (44.4%)');
        });

        it(`${user} viewshould be accurate for released grades.`, () => {
            cy.get('a[href*="/sample/gradeable/grades_released_homework/grading/details"]').click();
            cy.get('a[href*="/sample/gradeable/grades_released_homework/grading/status"]').click();
            cy.get('#filters').click();
            cy.get('#bad_submissions').click();
            cy.get('#apply_button').click();

            const text = cy.get('#left-grading-stats');
            text.should('contain', 'Students who have submitted: 71 / 101 (70.3%)');
            text.should('contain', 'Current percentage of TA grading done: 71 / 71 (100.0%)');
            text.should('contain', 'Section 1: 10 / 10 (100.0%)');
            text.should('contain', 'Number of students who have viewed their grade: 49 / 71 (69.0%)');
        });
    });
});
