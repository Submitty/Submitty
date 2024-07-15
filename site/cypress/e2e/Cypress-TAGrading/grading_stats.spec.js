const ApplyFilter = (toggle_bad_submissions, toggle_null_section) => {
    cy.get('a.edit-filters-button').click();
    if (toggle_bad_submissions) {
        cy.get('#toggle-filter-include-bad-submissions').click();
    }
    if (toggle_null_section) {
        cy.get('#toggle-filter-include-null-section').click();
    }
    // Apply Button
    cy.get('#apply_changes').click();
};

describe('Test cases for grading stats', () => {
    ['instructor', 'ta', 'grader'].forEach((user) => {
        beforeEach(() => {
            cy.login(user);
        });

        it(`${user} view should be accurate for team gradeables.`, () => {
            // Team gradeables on Sample Course don't have teams in Null section
            cy.visit(['sample', 'gradeable', 'grading_team_homework', 'grading', 'status']);
            cy.get('#left-grading-stats').as('left-chunk-stats');
            cy.get('#right-grading-stats').as('right-chunk-stats');
            cy.get('#grader-info').as('right-chunk-grader-info');
            cy.get('@left-chunk-stats').should('not.contain', 'verified');
            cy.get('@left-chunk-stats').should('contain', 'Students on a team: 101/101 (100%)');
            cy.get('@left-chunk-stats').should('contain', 'Number of teams: 36');
            cy.get('@left-chunk-stats').should('contain', 'Teams who have submitted on time: 25 / 36 (69.4%)');
            cy.get('@left-chunk-stats').should('contain', 'Section 1: 1 / 3 (33.3% graded)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 9.00 / 12 (75%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 10 - avg 1.15 - stddev 0.95)');
            // Include Bad Submissions only
            ApplyFilter(true, false);
            cy.get('@left-chunk-stats').should('contain', 'Teams who have submitted: 27 / 36 (75%)');
            cy.get('@left-chunk-stats').should('contain', 'Section 1: 2 / 4 (50.0% graded)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 9.18 / 12 (77%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 12 - avg 1.13 - stddev 0.96)');
            // Omit Bad submissions (default)
            ApplyFilter(true, false);
        });

        it(`${user} view should be accurate for non team gradeables.`, () => {
            cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'status']);
            cy.get('#left-grading-stats').as('left-chunk-stats');
            cy.get('#right-grading-stats').as('right-chunk-stats');
            cy.get('#grader-info').as('right-chunk-grader-info');
            cy.get('@left-chunk-stats').should('not.contain', 'verified');
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted on time: 59 / 101 (58.4%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 30 / 59 (50.8%)');
            cy.get('@left-chunk-stats').should('contain', 'Section 3: 3 / 7 (42.9% graded)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 7.85 / 12 (65%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 30 - avg 0.87 - stddev 0.83)');
            // Include Bad Submissions only
            ApplyFilter(true, false);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted: 68 / 101 (67.3%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 33 / 68 (48.5%)');
            cy.get('@left-chunk-stats').should('contain', 'Section 3: 4 / 9 (44.4% graded)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 7.71 / 12 (64%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 33 - avg 0.94 - stddev 0.83)');
            // Omit Bad submissions and Include Null Section
            ApplyFilter(true, true);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted on time: 82 / 139 (59%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 42 / 82 (51.2%)');
            cy.get('@left-chunk-stats').should('contain', 'Section NULL: 12 / 23 (52.2% graded)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 8.31 / 12 (69%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 42 - avg 0.98 - stddev 0.82)');
            // Include Bad Submission with the Null section filter included
            ApplyFilter(true, false);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted: 94 / 139 (67.6%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 45 / 94 (47.9%)');
            cy.get('@left-chunk-stats').should('contain', 'Section NULL: 12 / 26 (46.2% graded)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 8.18 / 12 (68%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 45 - avg 1.02 - stddev 0.82)');
            // Default, omit both filter
            ApplyFilter(true, true);
        });
        /* Currently the Bulk Upload gradeable on Sample course doesn't have any submissions.
        Until sample course is modified to have a bulk upload gradeable released,
        another way of mimicking the behavior of these gradeables is to disable the option
        to submit past the due date in a released homework gradeable, that way when we omit or
        include bad submissions, we can make sure the statistics stay the same */
        it(`${user} view should be accurate for released bulk upload exams grades.`, () => {
            cy.visit(['sample', 'gradeable', 'grades_released_homework', 'update?nav_tab=5']);
            // Disable submissions after due date
            cy.get('#no_late_submission').click();
            cy.visit(['sample', 'gradeable', 'grades_released_homework', 'grading', 'status']);
            cy.get('#left-grading-stats').as('left-chunk-stats');
            cy.get('#right-grading-stats').as('right-chunk-stats');
            cy.get('#grader-info').as('right-chunk-grader-info');
            cy.get('@left-chunk-stats').should('not.contain', 'verified');
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted on time: 71 / 101 (70.3%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 71 / 71 (100.0%)');
            cy.get('@left-chunk-stats').should('contain', 'Section 10: 4 / 4 (100.0% graded)');
            cy.get('@left-chunk-stats').should('contain', 'Number of students who have viewed their grade: 49 / 71 (69.0%)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 5.05 / 10 (51%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 71 - avg 3.08 - stddev 2.13)');
            // Include Bad Submissions only
            ApplyFilter(true, false);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted: 71 / 101 (70.3%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 71 / 71 (100.0%)');
            cy.get('@left-chunk-stats').should('contain', 'Section 10: 4 / 4 (100.0% graded)');
            cy.get('@left-chunk-stats').should('contain', 'Number of students who have viewed their grade: 49 / 71 (69.0%)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 5.05 / 10 (51%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 71 - avg 3.08 - stddev 2.13)');
            // Omit Bad submissions and Include Null Section
            ApplyFilter(true, true);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted on time: 98 / 139 (70.5%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 98 / 98 (100.0%)');
            cy.get('@left-chunk-stats').should('contain', 'Section NULL: 27 / 27 (100.0% graded)');
            cy.get('@left-chunk-stats').should('contain', 'Number of students who have viewed their grade: 67 / 98 (68.4%)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 5.28 / 10 (53%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 98 - avg 3.15 - stddev 2.12)');
            // Include Bad Submission with the Null section filter included
            ApplyFilter(true, false);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted: 98 / 139 (70.5%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 98 / 98 (100.0%)');
            cy.get('@left-chunk-stats').should('contain', 'Section NULL: 27 / 27 (100.0% graded)');
            cy.get('@left-chunk-stats').should('contain', 'Number of students who have viewed their grade: 67 / 98 (68.4%)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 5.28 / 10 (53%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 98 - avg 3.15 - stddev 2.12)');
            // Default, omit both filter
            ApplyFilter(true, true);
            cy.visit(['sample', 'gradeable', 'grades_released_homework', 'update?nav_tab=5']);
            // Enable back submissions after due date
            cy.get('#yes_late_submission').click();
        });
        it(`${user} view should be accurate for released grades.`, () => {
            cy.visit(['sample', 'gradeable', 'grades_released_homework', 'grading', 'status']);
            cy.get('#left-grading-stats').as('left-chunk-stats');
            cy.get('#right-grading-stats').as('right-chunk-stats');
            cy.get('#grader-info').as('right-chunk-grader-info');
            cy.get('@left-chunk-stats').should('not.contain', 'verified');
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted on time: 64 / 101 (63.4%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 64 / 64 (100.0%)');
            cy.get('@left-chunk-stats').should('contain', 'Section 10: 3 / 3 (100.0% graded)');
            cy.get('@left-chunk-stats').should('contain', 'Number of students who have viewed their grade: 49 / 71 (69.0%)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 5.27 / 10 (53%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 64 - avg 3.14 - stddev 2.14)');
            // Include Bad Submissions only
            ApplyFilter(true, false);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted: 71 / 101 (70.3%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 71 / 71 (100.0%)');
            cy.get('@left-chunk-stats').should('contain', 'Section 10: 4 / 4 (100.0% graded)');
            cy.get('@left-chunk-stats').should('contain', 'Number of students who have viewed their grade: 49 / 71 (69.0%)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 5.05 / 10 (51%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 71 - avg 3.08 - stddev 2.13)');
            // Omit Bad submissions and Include Null Section
            ApplyFilter(true, true);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted on time: 86 / 139 (61.9%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 86 / 86 (100.0%)');
            cy.get('@left-chunk-stats').should('contain', 'Section NULL: 22 / 22 (100.0% graded)');
            cy.get('@left-chunk-stats').should('contain', 'Number of students who have viewed their grade: 67 / 98 (68.4%)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 5.49 / 10 (55%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 86 - avg 3.20 - stddev 2.13)');
            // Include Bad Submission with the Null section filter included
            ApplyFilter(true, false);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted: 98 / 139 (70.5%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 98 / 98 (100.0%)');
            cy.get('@left-chunk-stats').should('contain', 'Section NULL: 27 / 27 (100.0% graded)');
            cy.get('@left-chunk-stats').should('contain', 'Number of students who have viewed their grade: 67 / 98 (68.4%)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 5.28 / 10 (53%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 98 - avg 3.15 - stddev 2.12)');
            // Default, omit both filter
            ApplyFilter(true, true);
        });
    });
});
