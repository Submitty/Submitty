const ApplyFilter = () => {
    cy.get('#filters').click();
    cy.get('#bad_submissions').click();
    cy.get('#apply_button').click();
};

describe('Test cases for grading stats', () => {

    ['instructor','ta','grader'].forEach((user) => {
        beforeEach(() => {
            cy.login(user);
            cy.visit(['sample']);
        });
        it(`${user} view should be accurate for teams.`, () => {
            cy.visit(['sample','gradeable','grading_team_homework','grading','status']);

            const all_submissions = cy.get('#left-grading-stats');
            all_submissions.should('contain', 'Students on a team: 101/101 (100%)');
            all_submissions.should('contain', 'Number of teams: 36');
            all_submissions.should('contain', 'Teams who have submitted on time: 25 / 36 (69.4%)');
            all_submissions.should('contain', 'Section 1: 1 / 3 (33.3%)');

            ApplyFilter();
            const on_time_submissions = cy.get('#left-grading-stats');
            on_time_submissions.should('contain', 'Students on a team: 101/101 (100%)');
            on_time_submissions.should('contain', 'Number of teams: 36');
            on_time_submissions.should('contain', 'Teams who have submitted: 27 / 36 (75%)');
            on_time_submissions.should('contain', 'Section 1: 2 / 4 (50.0%)');
            //Change filters back to default
            ApplyFilter();
        });

        it(`${user} view should be accurate for grades.`, () => {
            cy.visit(['sample','gradeable','grading_homework','grading','status']);
            const all_submissions = cy.get('#left-grading-stats').should('exist');
            all_submissions.should('contain', 'Students who have submitted on time: 59 / 101 (58.4%)');
            all_submissions.should('contain', 'Current percentage of TA grading done: 30 / 59 (50.8%)');
            all_submissions.should('contain', 'Section 1: 4 / 9 (44.4%)');

            ApplyFilter();
            const on_time_submissions = cy.get('#left-grading-stats').should('exist');
            on_time_submissions.should('contain', 'Students who have submitted: 68 / 101 (67.3%)');
            on_time_submissions.should('contain', 'Current percentage of TA grading done: 33 / 68 (48.5%)');
            on_time_submissions.should('contain', 'Section 1: 4 / 9 (44.4%)');
            //Change filters back to default
            ApplyFilter();

        });

        it(`${user} viewshould be accurate for released grades.`, () => {
            cy.visit(['sample','gradeable','grades_released_homework','grading','status']);
            const all_submissions = cy.get('#left-grading-stats');
            all_submissions.should('contain', 'Students who have submitted on time: 64 / 101 (63.4%)');
            all_submissions.should('contain', 'Current percentage of TA grading done: 64 / 64 (100.0%)');
            all_submissions.should('contain', 'Section 1: 10 / 10 (100.0%)');
            all_submissions.should('contain', 'Number of students who have viewed their grade: 49 / 71 (69.0%)');

            ApplyFilter();
            const on_time_submissions = cy.get('#left-grading-stats').should('exist');
            on_time_submissions.should('contain', 'Students who have submitted: 71 / 101 (70.3%)');
            on_time_submissions.should('contain', 'Current percentage of TA grading done: 71 / 71 (100.0%)');
            on_time_submissions.should('contain', 'Section 1: 10 / 10 (100.0%)');
            on_time_submissions.should('contain', 'Number of students who have viewed their grade: 49 / 71 (69.0%)');
            //Change filters back to default
            ApplyFilter();
        });
    });
});
