import { buildUrl } from '/cypress/support/utils.js';

const predictedStatus = (days_allowed, days_late, remaining) => {
    if (days_late === 0) {
        return 'Good';
    }
    else if (days_late <= days_allowed && days_late <= remaining) {
        // Valid
        return 'Late';
    }
    else if (days_late > remaining) {
        // Bad (too many for term)
        return 'Bad (too many late days used this term)';
    }
    else {
        // Bad (too many for assignment)
        return 'Bad (too many late days used on this assignment)';
    }
};

const calculateCache = () => {
    // Get cache recalculation request
    cy.intercept('GET', buildUrl(['sample', 'bulk_late_days', 'calculate'])).as('calculateCache');

    // Calculate all cache
    cy.get('button').contains('Calculate Info').click();
    cy.get('#rebuild-status-label').should('be.visible');

    // Wait for query to finish
    cy.wait('@calculateCache', { timeout: 300000 });

    // Wait for recalculation to finish
    cy.get('#rebuild-status-label', { timeout: 15000 }).should('not.be.visible');

    for (const user_id of all_user_ids) {
        cy.get(`[USER_ID="${user_id}"] > [ld_id="Late Days Remaining"]`)
            .then((cell) => expect(cell.text().trim()).not.to.equal(''));
    }
};

const checkStudentsInCache = () => {
    cy.visit(['sample', 'bulk_late_days']);
    for (const user_id of all_user_ids) {
        // Gradeable # of late days used should be empty
        cy.get(`[USER_ID="${user_id}"] > [id="Late Allowed Homework"]`)
            .then((cell) => expect(cell.text().trim()).to.equal(''));

        // Remaining late days isnt known, should be empty
        cy.get(`[USER_ID="${user_id}"] > [ld_id="Late Days Remaining"]`)
            .then((cell) => expect(cell.text().trim()).to.equal(''));
    }
};

const CheckStatusUpdated = (exceptions_given, late_days_remaining) => {
    for (const user_id of all_user_ids) {
        cy.login(user_id);
        cy.visit(['sample', 'late_table']);
        // Wait for login change to take place
        const status = predictedStatus(1, Math.max(0, all_late_users[user_id] - exceptions_given), late_days_remaining);

        // Find late day status within the row in the late day usage table
        cy.get('td[data-before-content="Event/Assignment"]')
            .contains('Late Allowed Homework')
            .siblings('td[data-before-content="Status"]')
            .contains(status)
            .should('exist');

        cy.logout();
    }
};
// Object with user_ids that have late submissions for gradeables
const all_late_users = {}; // {user_id: #days_late}
const all_user_ids = [];

all_late_users['moscie'] = 3;
all_user_ids.push('moscie');
// Submission is 3 days late and 0 late days => Bad (too many late days used this term)
// After given 2 late days => Bad (too many late days used this term)
// Or After given 2 extentions => Bad (too many late days used this term)
all_late_users['barteh'] = 2;
all_user_ids.push('barteh');
// Submission is 2 days late and 0 late days => Bad (too many late days used this term)
// After given 2 late days => Bad (too many late days used on this assignment) because only 1 late day is allowed
// Or After given 2 extentions => Good
all_late_users['harbel'] = 1;
all_user_ids.push('harbel');
// Submission is 1 day late and 0 late days => Bad (too many late days used this term)
// After given 2 late days => Late (valid submission)
// Or After given 2 extentions => Good

describe('Test cases involving late day cache updates', () => {
    // Ignore uncaught js exceptions
    Cypress.on('uncaught:exception', () => {
        return false;
    });

    describe('Test accessing Bulk Late Days page as a student', () => {
        it('should not allow access', () => {
            cy.visit(['sample', 'bulk_late_days']);
            cy.login('student');
            cy.get('.content').should('contain', "You don't have access to this page");
        });
    });

    describe('Test accessing Bulk Late Days as an instructor', () => {
        it('should load properly', () => {
            cy.visit(['sample', 'bulk_late_days']);
            cy.login('instructor');
            cy.get('#late-day-table').should('exist');
            calculateCache();
        });
    });

    describe('Test late submissions', () => {
        it('should have 0 late days used on bulk late days table', () => {
            cy.login('instructor');
            cy.visit(['sample', 'bulk_late_days']);
            // 0 late days should be charged
            for (const user_id of all_user_ids) {
                cy.get(`[USER_ID="${user_id}"] > [id="Late Allowed Homework"]`)
                    .contains('0')
                    .should('exist');
            }
        });
        it('Adds a new late submission', () => {
            cy.login('instructor');
            cy.visit(['sample', 'gradeable', 'late_allowed_homework']);
            const testfile = 'cypress/fixtures/file1.txt';
            // Make a new submission
            cy.get('#startnew').click();
            cy.get('#upload1').selectFile(testfile, { action: 'drag-drop' });
            cy.waitPageChange(() => {
                cy.get('#submit').click();
            });
            cy.get('#submitted-files > div').should('contain', 'file1.txt');

            // Check cache
            cy.visit(['sample', 'bulk_late_days']);
            calculateCache();
            cy.get('[USER_ID=instructor] > [id="Late Allowed Homework"]')
                .contains('0')
                .should('exist');
        });
    });

    describe('Test changes to late days allowed table', () => {
        it('should give late days and check new status', () => {
            cy.login('instructor');
            cy.visit(['sample', 'late_days']);
            cy.intercept('GET', buildUrl(['sample', 'late_days'])).as('late_days');

            for (const user_id of all_user_ids) {
                // update the number of late days
                cy.get('#user_id').type(user_id);
                cy.get('#datestamp').type('1972-01-01', { force: true });
                cy.get('#user_id').click(); // dismiss the calendar view
                cy.get('#late_days').clear();
                cy.get('#late_days').type(2);
                cy.get('input[type=submit]').click();
                cy.wait('@late_days');
            }
            checkStudentsInCache();
            cy.logout();
            CheckStatusUpdated(0, 2);
            // Adding late days represents a timestamp, which is a new entry in the cache
            // Should check that there a new header with the title of the datestamp
            cy.login('instructor');
            cy.visit(['sample', 'bulk_late_days']);
            cy.get('#1972-01-01').should('have.length.gt', 0);
            cy.visit(['sample', 'late_days']);

            // align the interception
            cy.wait('@late_days');
            const deleteLateDays = () => {
                cy.get('div.content').then((table) => {
                    if (table.find('#Delete').length > 0) {
                        cy.wrap(table).find('#Delete').first().click();
                        cy.wait('@late_days');
                        deleteLateDays();
                    }
                });
            };
            // Cleanup
            deleteLateDays();
            // View bulk late day changes
            checkStudentsInCache();
            // Now since the latedays are gone, the header should be gone
            cy.visit(['sample', 'bulk_late_days']);
            cy.get('#1972-01-01').should('have.length', 0);
        });
    });

    describe('Test changes to late day extensions', () => {
        it('should give extentions and check new status', () => {
            cy.login('instructor');
            cy.visit(['sample', 'extensions']);
            cy.get('#gradeable-select').select('Late Allowed Homework');
            cy.intercept('GET', buildUrl(['sample', 'extensions'])).as('extensions');
            cy.intercept('POST', buildUrl(['sample', 'extensions', 'update'])).as('extensions-update');
            for (const user_id of all_user_ids) {
                // update the number of late days
                cy.get('#user_id').type(user_id);
                cy.get('#late-days').type(2, { force: true });
                cy.get('#extensions-form').find('a').as('ext-form-link');

                cy.get('@ext-form-link').contains('Submit');
                cy.get('@ext-form-link').should('exist');
                cy.get('@ext-form-link').click();
                cy.wait('@extensions-update');
                cy.wait('@extensions');
            }
            checkStudentsInCache();
            cy.logout();
            CheckStatusUpdated(2, 0);
            cy.login('instructor');
            cy.visit(['sample', 'extensions']);

            // align the interception
            cy.wait('@extensions');

            const deleteExtensions = () => {
                cy.get('body').then((body) => {
                    if (body.find('#extensions-table').length > 0) {
                        cy.wrap(body).find('#Delete').first().click();
                        cy.wait('@extensions-update');
                        cy.wait('@extensions');
                        deleteExtensions();
                    }
                });
            };
            // Cleanup
            cy.get('#gradeable-select').select('Late Allowed Homework');
            deleteExtensions();
            // View bulk late day changes
            checkStudentsInCache();
        });
    });

    describe('Test changes to gradeable info', () => {
        const EditGradeablePage = () => {
            cy.visit(['sample', 'bulk_late_days']);
            calculateCache();
            cy.visit(['sample', 'gradeable', 'late_allowed_homework', 'update?nav_tab=5']);
            cy.get('.breadcrumb > span').should('have.text', 'Edit Gradeable');
        };

        it('Changes gradeable due date information', () => {
            cy.login('instructor');
            // Changes due date
            EditGradeablePage();
            cy.get('#date_due')
                .clear();
            cy.get('#date_due')
                .type('1972-01-02 11:59:59');
            cy.get('#date_due')
                .click();
            cy.get('#late_days').click(); // Dismiss calender and trigger save
            cy.get('#save_status', { timeout: 10000 }).should('have.text', 'All Changes Saved');
            cy.visit(['sample', 'bulk_late_days']);
            // Gradeable # of late days used should be empty
            cy.get('[id="Late Allowed Homework"]').then((cell) => expect(cell.text().trim()).to.equal(''));

            // Changes due date back
            EditGradeablePage();
            cy.get('#date_due')
                .clear();
            cy.get('#date_due')
                .type('1972-01-01 03:59:59');
            cy.get('#date_due')
                .click();
            cy.get('#late_days').click(); // Dismiss calender and trigger save
            cy.get('#save_status', { timeout: 10000 }).should('have.text', 'All Changes Saved');
            cy.visit(['sample', 'bulk_late_days']);
            cy.get('[id="Late Allowed Homework"]').then((cell) => expect(cell.text().trim()).to.equal(''));

            // Disables due date
            EditGradeablePage();
            cy.get('#has_due_date_no').check();
            cy.get('#save_status', { timeout: 10000 }).should('have.text', 'All Changes Saved');
            cy.visit(['sample', 'bulk_late_days']);
            // Bulk late days should not have gradeable title
            cy.get('[id="Late Allowed Homework"]').should('have.length', 0);

            // Re-enables due date
            EditGradeablePage();
            cy.get('#has_due_date_yes').check();
            cy.get('#save_status', { timeout: 10000 }).should('have.text', 'All Changes Saved');
            cy.visit(['sample', 'bulk_late_days']);
            // Bulk late days should have gradeable title
            cy.get('[id="Late Allowed Homework"]').should('have.length.gt', 0);
        });

        it('Changes gradeable late days allowed information', () => {
            cy.login('instructor');
            // Disables late days allowed
            EditGradeablePage();
            cy.get('#no_late_submission').check();
            cy.get('#save_status', { timeout: 10000 }).should('have.text', 'All Changes Saved');
            cy.visit(['sample', 'bulk_late_days']);
            cy.get('[id="Late Allowed Homework"]').should('have.length', 0);

            // Re-enables late days allowed
            EditGradeablePage();
            cy.get('#yes_late_submission').check();
            cy.get('#save_status', { timeout: 10000 }).should('have.text', 'All Changes Saved');
            cy.visit(['sample', 'bulk_late_days']);
            cy.get('[id="Late Allowed Homework"]').should('have.length.gt', 0);

            // Changes late days allowed number
            EditGradeablePage();
            cy.get('#late_days')
                .clear();
            cy.get('#late_days')
                .type('20000');
            cy.get('#late_days')
                .click();
            cy.get('#date_due').click(); // Dismiss calender and trigger save
            cy.get('#save_status', { timeout: 10000 }).should('have.text', 'All Changes Saved');
            cy.visit(['sample', 'bulk_late_days']);
            cy.get('[id="Late Allowed Homework"]').then((cell) => expect(cell.text().trim()).to.equal(''));

            // Changes late days allowed number back
            EditGradeablePage();
            cy.get('#late_days')
                .clear();
            cy.get('#late_days')
                .type('1');
            cy.get('#late_days')
                .click();
            cy.get('#date_due').click(); // Dismiss calender and trigger save
            cy.get('#save_status', { timeout: 10000 }).should('have.text', 'All Changes Saved');
            cy.visit(['sample', 'bulk_late_days']);
            cy.get('[id="Late Allowed Homework"]').then((cell) => expect(cell.text().trim()).to.equal(''));
        });
    });

    describe('Test changes to initial late days', () => {
        it('Changes default late days', () => {
            cy.visit(['sample', 'config']);
            cy.login('instructor');

            cy.get('#default-student-late-days')
                .clear();
            cy.get('#default-student-late-days')
                .type('1');

            // Remove focus to trigger config change
            cy.get('#default-hw-late-days').click();

            cy.visit(['sample', 'bulk_late_days']);

            cy.get('[initial_ld_id="Initial Late Days"]')
                .each((cell) => expect(cell.text().trim()).to.equal('1'));
            // Change back
            cy.visit(['sample', 'config']);
            cy.get('#default-student-late-days')
                .clear();
            cy.get('#default-student-late-days')
                .type('0');
            cy.get('#default-hw-late-days').click();
        });
    });
});
