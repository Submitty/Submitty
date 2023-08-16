describe('Test cases for grading stats', () => {
    ['instructor','ta','grader'].forEach((user) => {
        beforeEach(() => {
            cy.login(user);
        });

        it(`${user} view should be accurate for teams.`, () => {
            cy.visit(['sample','gradeable','grading_team_homework','grading','status']);
            cy.get('#left-grading-stats').as('on_time_submissions');
            cy.get('@on_time_submissions').should('contain', 'Students on a team: 101/101 (100%)');
            cy.get('@on_time_submissions').should('contain', 'Number of teams: 36');
            cy.get('@on_time_submissions').should('contain', 'Teams who have submitted on time: 25 / 36 (69.4%)');
            cy.get('@on_time_submissions').should('contain', 'Section 1: 1 / 3 (33.3%)');
        });

        it(`${user} view should be accurate for grades.`, () => {
            cy.visit(['sample','gradeable','grading_homework','grading','status']);
            cy.get('#left-grading-stats').as('on_time_submissions');
            cy.get('@on_time_submissions').should('contain', 'Students who have submitted on time: 59 / 101 (58.4%)');
            cy.get('@on_time_submissions').should('contain', 'Current percentage of TA grading done: 30 / 59 (50.8%)');
            cy.get('@on_time_submissions').should('contain', 'Section 1: 4 / 9 (44.4%)');
        });

        it(`${user} viewshould be accurate for released grades.`, () => {
            cy.visit(['sample','gradeable','grades_released_homework','grading','status']);
            cy.get('#left-grading-stats').as('on_time_submissions');
            cy.get('@on_time_submissions').should('contain', 'Students who have submitted on time: 64 / 101 (63.4%)');
            cy.get('@on_time_submissions').should('contain', 'Current percentage of TA grading done: 64 / 64 (100.0%)');
            cy.get('@on_time_submissions').should('contain', 'Section 1: 10 / 10 (100.0%)');
            cy.get('@on_time_submissions').should('contain', 'Number of students who have viewed their grade: 49 / 71 (69.0%)');
        });
    });
});
