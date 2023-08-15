describe('Test cases for grading stats', () => {
    ['instructor','ta','grader'].forEach((user) => {
        beforeEach(() => {
            cy.login(user);
            cy.visit(['sample']);
        });

        it(`${user} view should be accurate for teams.`, () => {
            cy.visit(['sample','gradeable','grading_team_homework','grading','status']);
            const on_time_submissions = cy.get('#left-grading-stats');
            on_time_submissions.should('contain', 'Students on a team: 101/101 (100%)');
            on_time_submissions.should('contain', 'Number of teams: 36');
            on_time_submissions.should('contain', 'Teams who have submitted on time: 25 / 36 (69.4%)');
            on_time_submissions.should('contain', 'Section 1: 1 / 3 (33.3%)');
        });

        it(`${user} view should be accurate for grades.`, () => {
            cy.visit(['sample','gradeable','grading_homework','grading','status']);
            const on_time_submissions = cy.get('#left-grading-stats').should('exist');
            on_time_submissions.should('contain', 'Students who have submitted on time: 59 / 101 (58.4%)');
            on_time_submissions.should('contain', 'Current percentage of TA grading done: 30 / 59 (50.8%)');
            on_time_submissions.should('contain', 'Section 1: 4 / 9 (44.4%)');
        });

        it(`${user} viewshould be accurate for released grades.`, () => {
            cy.visit(['sample','gradeable','grades_released_homework','grading','status']);
            const on_time_submissions = cy.get('#left-grading-stats');
            on_time_submissions.should('contain', 'Students who have submitted on time: 64 / 101 (63.4%)');
            on_time_submissions.should('contain', 'Current percentage of TA grading done: 64 / 64 (100.0%)');
            on_time_submissions.should('contain', 'Section 1: 10 / 10 (100.0%)');
            on_time_submissions.should('contain', 'Number of students who have viewed their grade: 49 / 71 (69.0%)');
        });
    });
});
