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
            cy.get('@left-chunk-stats').should('contain', 'Students on a team: 105/105 (100%)');
            cy.get('@left-chunk-stats').should('contain', 'Number of teams: 37');
            cy.get('@left-chunk-stats').should('contain', 'Teams who have submitted on time: 22 / 37 (59.5%)');
            cy.get('@left-chunk-stats').should('contain', 'Section 1: 4 / 4 (100.0% graded)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 9.63 / 12 (80%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 12 - avg 1.21 - stddev 0.85)');
            // Include Bad Submissions only
            ApplyFilter(true, false);
            cy.get('@left-chunk-stats').should('contain', 'Teams who have submitted: 27 / 37 (73%)');
            cy.get('@left-chunk-stats').should('contain', 'Section 1: 6 / 6 (100.0% graded)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 10.07 / 12 (84%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 15 - avg 1.17 - stddev 0.85)');
            // Omit Bad submissions (default)
            ApplyFilter(true, false);
        });

        it(`${user} view should be accurate for non team gradeables.`, () => {
            cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'status']);
            cy.get('#left-grading-stats').as('left-chunk-stats');
            cy.get('#right-grading-stats').as('right-chunk-stats');
            cy.get('#grader-info').as('right-chunk-grader-info');
            cy.get('@left-chunk-stats').should('not.contain', 'verified');
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted on time: 61 / 105 (58.1%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 27 / 61 (44.3%)');
            cy.get('@left-chunk-stats').should('contain', 'Section 3: 1 / 5 (20.0% graded)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 8.74 / 12 (73%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 27 - avg 1.00 - stddev 0.87)');
            // Include Bad Submissions only
            ApplyFilter(true, false);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted: 77 / 105 (73.3%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 33 / 77 (42.9%)');
            cy.get('@left-chunk-stats').should('contain', 'Section 3: 2 / 8 (25.0% graded)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 8.94 / 12 (75%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 33 - avg 1.06 - stddev 0.89)');
            // Omit Bad submissions and Include Null Section
            ApplyFilter(true, true);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted on time: 89 / 143 (62.2%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 39 / 89 (43.8%)');
            cy.get('@left-chunk-stats').should('contain', 'Section NULL: 12 / 28 (42.9% graded)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 8.58 / 12 (72%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 39 - avg 0.90 - stddev 0.88)');
            // Include Bad Submission with the Null section filter included
            ApplyFilter(true, false);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted: 107 / 143 (74.8%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 47 / 107 (43.9%)');
            cy.get('@left-chunk-stats').should('contain', 'Section NULL: 14 / 30 (46.7% graded)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 8.71 / 12 (73%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 47 - avg 0.91 - stddev 0.90)');
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
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted on time: 72 / 105 (68.6%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 72 / 72 (100.0%)');
            cy.get('@left-chunk-stats').should('contain', 'Section 10: 6 / 6 (100.0% graded)');
            cy.get('@left-chunk-stats').should('contain', 'Number of students who have viewed their grade: 48 / 72 (66.7%)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 4.94 / 10 (49%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 72 - avg 2.96 - stddev 2.14)');
            // Include Bad Submissions only
            ApplyFilter(true, false);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted: 72 / 105 (68.6%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 72 / 72 (100.0%)');
            cy.get('@left-chunk-stats').should('contain', 'Section 10: 6 / 6 (100.0% graded)');
            cy.get('@left-chunk-stats').should('contain', 'Number of students who have viewed their grade: 48 / 72 (66.7%)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 4.94 / 10 (49%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 72 - avg 2.96 - stddev 2.14)');
            // Omit Bad submissions and Include Null Section
            ApplyFilter(true, true);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted on time: 102 / 143 (71.3%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 102 / 102 (100.0%)');
            cy.get('@left-chunk-stats').should('contain', 'Section NULL: 30 / 30 (100.0% graded)');
            cy.get('@left-chunk-stats').should('contain', 'Number of students who have viewed their grade: 71 / 102 (69.6%)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 5.13 / 10 (51%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 102 - avg 3.09 - stddev 2.14)');
            // Include Bad Submission with the Null section filter included
            ApplyFilter(true, false);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted: 102 / 143 (71.3%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 102 / 102 (100.0%)');
            cy.get('@left-chunk-stats').should('contain', 'Section NULL: 30 / 30 (100.0% graded)');
            cy.get('@left-chunk-stats').should('contain', 'Number of students who have viewed their grade: 71 / 102 (69.6%)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 5.13 / 10 (51%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 102 - avg 3.09 - stddev 2.14)');
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
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted on time: 62 / 105 (59%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 62 / 62 (100.0%)');
            cy.get('@left-chunk-stats').should('contain', 'Section 10: 5 / 5 (100.0% graded)');
            cy.get('@left-chunk-stats').should('contain', 'Number of students who have viewed their grade: 48 / 72 (66.7%)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 5.06 / 10 (51%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 62 - avg 2.98 - stddev 2.16)');
            // Include Bad Submissions only
            ApplyFilter(true, false);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted: 72 / 105 (68.6%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 72 / 72 (100.0%)');
            cy.get('@left-chunk-stats').should('contain', 'Section 10: 6 / 6 (100.0% graded)');
            cy.get('@left-chunk-stats').should('contain', 'Number of students who have viewed their grade: 48 / 72 (66.7%)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 4.94 / 10 (49%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 72 - avg 2.96 - stddev 2.14)');
            // Omit Bad submissions and Include Null Section
            ApplyFilter(true, true);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted on time: 88 / 143 (61.5%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 88 / 88 (100.0%)');
            cy.get('@left-chunk-stats').should('contain', 'Section NULL: 26 / 26 (100.0% graded)');
            cy.get('@left-chunk-stats').should('contain', 'Number of students who have viewed their grade: 71 / 102 (69.6%)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 5.26 / 10 (53%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 88 - avg 3.10 - stddev 2.15)');
            // Include Bad Submission with the Null section filter included
            ApplyFilter(true, false);
            cy.get('@left-chunk-stats').should('contain', 'Students who have submitted: 102 / 143 (71.3%)');
            cy.get('@left-chunk-stats').should('contain', 'Current percentage of TA grading done: 102 / 102 (100.0%)');
            cy.get('@left-chunk-stats').should('contain', 'Section NULL: 30 / 30 (100.0% graded)');
            cy.get('@left-chunk-stats').should('contain', 'Number of students who have viewed their grade: 71 / 102 (69.6%)');
            cy.get('@right-chunk-stats').should('contain', 'Average: 5.13 / 10 (51%)');
            cy.get('@right-chunk-grader-info').should('contain', 'instructor (count 102 - avg 3.09 - stddev 2.14)');
            // Default, omit both filter
            ApplyFilter(true, true);
        });
    });
});
