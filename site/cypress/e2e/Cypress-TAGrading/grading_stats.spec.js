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
            cy.get('@left-chunk-stats').should('contain', 'Teams who have submitted on time: 24 / 36 (66.7%)');
            cy.get('@left-chunk-stats').should('contain', 'Section 1: 2 / 2 (100.0% graded)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 7.90 / 12 (66%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 15 - avg 1.13 - stddev 0.90)');
            // Include Bad Submissions only
            ApplyFilter(true, false);
            cy.get('@left-chunk-stats').should('contain', 'Teams who have submitted: 27 / 36 (75%)');
            cy.get('@left-chunk-stats').should('contain', 'Section 1: 3 / 3 (100.0% graded)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 8.03 / 12 (67%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 17 - avg 1.06 - stddev 0.89)');
            // Omit Bad submissions (default)
            ApplyFilter(true, false);
        });

        it(`${user} view should be accurate for non team gradeables.`, () => {
            cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'status']);
            cy.get('#left-grading-stats').as('left-chunk-stats');
            cy.get('#right-grading-stats').as('right-chunk-stats');
            cy.get('#grader-info').as('right-chunk-grader-info');
            cy.get('@left-chunk-stats').should('not.contain', 'verified');
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted on time: 61 / 101 (60.4%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 35 / 61 (57.4%)');
            cy.get('@left-chunk-stats').should('contain', 'Section 3: 5 / 8 (62.5% graded)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 7.46 / 12 (62%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 35 - avg 0.84 - stddev 0.75)');
            // Include Bad Submissions only
            ApplyFilter(true, false);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted: 71 / 101 (70.3%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 40 / 71 (56.3%)');
            cy.get('@left-chunk-stats').should('contain', 'Section 3: 5 / 8 (62.5% graded)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 7.76 / 12 (65%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 40 - avg 0.94 - stddev 0.80)');
            // Omit Bad submissions and Include Null Section
            ApplyFilter(true, true);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted on time: 81 / 139 (58.3%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 44 / 81 (54.3%)');
            cy.get('@left-chunk-stats').should('contain', 'Section NULL: 9 / 20 (45.0% graded)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 7.69 / 12 (64%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 44 - avg 0.81 - stddev 0.77)');
            // Include Bad Submission with the Null section filter included
            ApplyFilter(true, false);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted: 96 / 139 (69.1%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 53 / 96 (55.2%)');
            cy.get('@left-chunk-stats').should('contain', 'Section NULL: 13 / 25 (52.0% graded)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 7.91 / 12 (66%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 53 - avg 0.84 - stddev 0.81)');
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
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted on time: 64 / 101 (63.4%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 64 / 64 (100.0%)');
            cy.get('@left-chunk-stats').should('contain', 'Section 10: 5 / 5 (100.0% graded)');
            cy.get('@left-chunk-stats').should('contain', 'Number of students who have viewed their grade: 42 / 64 (65.6%)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 4.92 / 10 (49%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 64 - avg 3.08 - stddev 1.90)');
            // Include Bad Submissions only
            ApplyFilter(true, false);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted: 64 / 101 (63.4%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 64 / 64 (100.0%)');
            cy.get('@left-chunk-stats').should('contain', 'Section 10: 5 / 5 (100.0% graded)');
            cy.get('@left-chunk-stats').should('contain', 'Number of students who have viewed their grade: 42 / 64 (65.6%)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 4.92 / 10 (49%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 64 - avg 3.08 - stddev 1.90)');
            // Omit Bad submissions and Include Null Section
            ApplyFilter(true, true);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted on time: 93 / 139 (66.9%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 93 / 93 (100.0%)');
            cy.get('@left-chunk-stats').should('contain', 'Section NULL: 29 / 29 (100.0% graded)');
            cy.get('@left-chunk-stats').should('contain', 'Number of students who have viewed their grade: 58 / 93 (62.4%)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 5.31 / 10 (53%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 93 - avg 3.23 - stddev 1.98)');
            // Include Bad Submission with the Null section filter included
            ApplyFilter(true, false);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted: 93 / 139 (66.9%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 93 / 93 (100.0%)');
            cy.get('@left-chunk-stats').should('contain', 'Section NULL: 29 / 29 (100.0% graded)');
            cy.get('@left-chunk-stats').should('contain', 'Number of students who have viewed their grade: 58 / 93 (62.4%)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 5.31 / 10 (53%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 93 - avg 3.23 - stddev 1.98)');
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
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted on time: 62 / 101 (61.4%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 62 / 62 (100.0%)');
            cy.get('@left-chunk-stats').should('contain', 'Section 10: 5 / 5 (100.0% graded)');
            cy.get('@left-chunk-stats').should('contain', 'Number of students who have viewed their grade: 42 / 64 (65.6%)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 4.92 / 10 (49%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 62 - avg 3.02 - stddev 1.90)');
            // Include Bad Submissions only
            ApplyFilter(true, false);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted: 64 / 101 (63.4%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 64 / 64 (100.0%)');
            cy.get('@left-chunk-stats').should('contain', 'Section 10: 5 / 5 (100.0% graded)');
            cy.get('@left-chunk-stats').should('contain', 'Number of students who have viewed their grade: 42 / 64 (65.6%)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 4.92 / 10 (49%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 64 - avg 3.08 - stddev 1.90)');
            // Omit Bad submissions and Include Null Section
            ApplyFilter(true, true);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted on time: 88 / 139 (63.3%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 88 / 88 (100.0%)');
            cy.get('@left-chunk-stats').should('contain', 'Section NULL: 26 / 26 (100.0% graded)');
            cy.get('@left-chunk-stats').should('contain', 'Number of students who have viewed their grade: 58 / 93 (62.4%)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 5.17 / 10 (52%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 88 - avg 3.14 - stddev 2.00)');
            // Include Bad Submission with the Null section filter included
            ApplyFilter(true, false);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted: 93 / 139 (66.9%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 93 / 93 (100.0%)');
            cy.get('@left-chunk-stats').should('contain', 'Section NULL: 29 / 29 (100.0% graded)');
            cy.get('@left-chunk-stats').should('contain', 'Number of students who have viewed their grade: 58 / 93 (62.4%)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 5.31 / 10 (53%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 93 - avg 3.23 - stddev 1.98)');
            // Default, omit both filter
            ApplyFilter(true, true);
        });
    });
});
