const ApplyFilter = (toggle_bad_submissions, toggle_null_section) => {
    cy.get('a.edit-filters-button').click();
    if (toggle_bad_submissions) {
        cy.get('#toggle-filter-include-bad-submissions').click();
    }
    if (toggle_null_section) {
        cy.get('#toggle-filter-include-null-section').click();
    }
    //Apply Button
    cy.get('#apply_changes').click();
};

describe('Test cases for grading stats', () => {
    ['instructor', 'ta', 'grader'].forEach((user) => {
        beforeEach(() => {
            cy.login(user);
        });

        it(`${user} view should be accurate for teams.`, () => {
            //Team gradeables on Sample Course don't have teams in Null section
            cy.visit(['sample', 'gradeable', 'grading_team_homework', 'grading', 'status']);
            cy.get('#left-grading-stats').as('on_time_submissions');
            cy.get('@on_time_submissions').should('contain', 'Students on a team: 101/101 (100%)');
            cy.get('@on_time_submissions').should('contain', 'Number of teams: 36');
            cy.get('@on_time_submissions').should('contain', 'Teams who have submitted on time: 25 / 36 (69.4%)');
            cy.get('@on_time_submissions').should('contain', 'Section 1: Graded: 1 / 3 (33.3%)');
            //Include Bad Submissions only
            ApplyFilter(true, false);
            cy.get('#left-grading-stats').as('all_submissions');
            cy.get('@all_submissions').should('contain', 'Teams who have submitted: 27 / 36 (75%)');
            cy.get('@all_submissions').should('contain', 'Section 1: Graded: 2 / 4 (50.0%)');
            //Omit Bad submissions (default)
            ApplyFilter(true, false);
        });

        it(`${user} view should be accurate for grades.`, () => {
            cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'status']);
            cy.get('#left-grading-stats').as('on_time_submissions');
            cy.get('@on_time_submissions').should('contain', 'Students who have submitted on time: 59 / 101 (58.4%)');
            cy.get('@on_time_submissions').should('contain', 'Current percentage of TA grading done: 30 / 59 (50.8%)');
            cy.get('@on_time_submissions').should('contain', 'Section 3: Graded: 3 / 7 (42.9%)');
            //Include Bad Submissions only
            ApplyFilter(true, false);
            cy.get('#left-grading-stats').as('all_submissions');
            cy.get('@all_submissions').should('contain', 'Students who have submitted: 68 / 101 (67.3%)');
            cy.get('@all_submissions').should('contain', 'Current percentage of TA grading done: 33 / 68 (48.5%)');
            cy.get('@all_submissions').should('contain', 'Section 3: Graded: 4 / 9 (44.4%)');
            //Omit Bad submissions and Include Null Section
            ApplyFilter(true, true);
            cy.get('#left-grading-stats').as('null_on_time_submissions');
            cy.get('@null_on_time_submissions').should('contain', 'Students who have submitted on time: 82 / 139 (59%)');
            cy.get('@null_on_time_submissions').should('contain', 'Current percentage of TA grading done: 42 / 82 (51.2%)');
            cy.get('@null_on_time_submissions').should('contain', 'Section NULL: Graded: 12 / 23 (52.2%)');
            //Include Bad Submission with the Null section filter included
            ApplyFilter(true, false);
            cy.get('#left-grading-stats').as('null_all_submissions');
            cy.get('@null_all_submissions').should('contain', 'Students who have submitted: 94 / 139 (67.6%)');
            cy.get('@null_all_submissions').should('contain', 'Current percentage of TA grading done: 45 / 94 (47.9%)');
            cy.get('@null_all_submissions').should('contain', 'Section NULL: Graded: 12 / 26 (46.2%)');
            //Default, omit both filter
            ApplyFilter(true, true);
        });

        it(`${user} viewshould be accurate for released grades.`, () => {
            cy.visit(['sample', 'gradeable', 'grades_released_homework', 'grading', 'status']);
            cy.get('#left-grading-stats').as('on_time_submissions');
            cy.get('@on_time_submissions').should('contain', 'Students who have submitted on time: 64 / 101 (63.4%)');
            cy.get('@on_time_submissions').should('contain', 'Current percentage of TA grading done: 64 / 64 (100.0%)');
            cy.get('@on_time_submissions').should('contain', 'Section 10: Graded: 3 / 3 (100.0%))');
            cy.get('@on_time_submissions').should('contain', 'Number of students who have viewed their grade: 49 / 71 (69.0%)');
            //Include Bad Submissions only
            ApplyFilter(true, false);
            cy.get('#left-grading-stats').as('all_submissions');
            cy.get('@all_submissions').should('contain', 'Students who have submitted: 71 / 101 (70.3%)');
            cy.get('@all_submissions').should('contain', 'Current percentage of TA grading done: 71 / 71 (100.0%)');
            cy.get('@all_submissions').should('contain', 'Section 10: Graded: 4 / 4 (100.0%)');
            //Omit Bad submissions and Include Null Section
            ApplyFilter(true, true);
            cy.get('#left-grading-stats').as('null_on_time_submissions');
            cy.get('@null_on_time_submissions').should('contain', 'Students who have submitted on time: 86 / 139 (61.9%)');
            cy.get('@null_on_time_submissions').should('contain', 'Current percentage of TA grading done: 86 / 86 (100.0%)');
            cy.get('@null_on_time_submissions').should('contain', 'Section NULL: Graded: 22 / 22 (100.0%)');
            cy.get('@on_time_submissions').should('contain', 'Number of students who have viewed their grade: 67 / 98 (68.4%)');
            //Include Bad Submission with the Null section filter included
            ApplyFilter(true, false);
            cy.get('#left-grading-stats').as('null_all_submissions');
            cy.get('@null_all_submissions').should('contain', 'Students who have submitted: 98 / 139 (70.5%)');
            cy.get('@null_all_submissions').should('contain', 'Current percentage of TA grading done: 98 / 98 (100.0%)');
            cy.get('@null_all_submissions').should('contain', 'Section NULL: Graded: 27 / 27 (100.0%)');
            //Default, omit both filter
            ApplyFilter(true, true);
        });
    });
});
