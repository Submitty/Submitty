import {buildUrl} from '/cypress/support/utils.js';

const predictedStatus = (days_allowed, days_late, remaining) => {
    if (days_late <= 0) {
        return 'Good';
    }
    else if (days_late <= days_allowed && days_late <= remaining) {
        // good
        return 'Late';
    }
    else if (days_late > remaining) {
        // bad (too many for term)
        return 'Bad (too many late days used this term)';
    }
    else {
        // bad (too many for assignment)
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
    cy.wait('@calculateCache', {timeout: 300000});

    // Wait for recalculation to finish
    cy.get('#rebuild-status-label', {timeout: 15000}).should('not.be.visible');

    for (const user_id of all_user_ids) {
        cy.get(`[data-user="${user_id}"] > [data-before-content="Late Days Remaining"]`)
        .then((cell) => expect(cell.text().trim()).not.to.equal(''));
    }
};

// Test setup (sorted by submission due date)
const test_info = [{
    g_id: 'late_allowed_homework',
    g_title: 'Late Allowed Homework',
    info: '(1 day allowed)',
    days_allowed: 1
},
{
    g_id: 'late_allowed_homework_2',
    g_title: 'Late Allowed Homework 2',
    info: '(2 days allowed)',
    days_allowed: 2
}];

// Object with user_ids that have late submissions for gradeables
const all_late_users = {}; // {g_id: {user_id: #days_late}}
// Grab all user IDs across all gradeables
let all_user_ids = [];

describe('Test cases involving late day cache updates', {retries: {openMode: 2}}, () => {
    // Ignore uncaught js exceptions
    Cypress.on('uncaught:exception', (err, runnable) => {
        return false;
    })

    describe('Test accessing Bulk Late Days page as a student', () => {
        it('should not allow access', () => {
            cy.visit(['sample', 'bulk_late_days']);
            cy.login('student');
            cy.get('.content').contains("You don't have access to this page");
        });
    });

    describe('Test accessing Bulk Late Days as an instructor', () => {
        it('should load properly', () => {
            cy.visit(['sample', 'bulk_late_days']);
            cy.login('instructor');
            cy.get('#late-day-table');
        });
    });

    for (const test_case of test_info) {
        // Grab testcase info
        const { g_id, g_title, info, days_allowed } = test_case;
        // Set up late submission information
        all_late_users[g_id] = {};

        describe('Test students with late submissions '+info, () => {
            it('should check students who have used too many late days for assignment', () => {
                cy.visit(['sample', 'gradeable', g_id, 'grading', 'details']);
                cy.login('instructor');

                cy.get('a').contains('View All').click();

                // Grab rows of students with late submissions (not NULL registration)
                cy.get('table')
                    .find('tr')
                    .not(':contains(NULL)')
                    .filter(':contains(Too Many Days Late)')
                    .each((row) => {
                        cy.wrap(row).children().eq(2).then((user_id) => {
                            const id = user_id.text().trim();
                            all_late_users[g_id][id] = null;
                            all_user_ids.push(id);
                        });
                    });
            });

            it('should have days submitted late > 0', () => {
                cy.log()
                for (const user_id in all_late_users[g_id]) {
                    cy.visit([]);
                    cy.wait(3000);
                    cy.login(user_id);
                    cy.visit(['sample', 'late_table']);

                    // Wait for login change to take place
                    cy.wait(5000);

                    // Find # of days late within the row in the late day usage table
                    cy.get('td[data-before-content="Event/Assignment"]')
                        .contains(g_title)
                        .siblings('td[data-before-content="Days Submitted Late"]')
                        .should('not.have.value', 'N/A').then((value) => {
                            // Grab the # of days late (should be > 0)
                            const days_late = parseInt(value.text());
                            expect(days_late).to.be.greaterThan(0);
                            all_late_users[g_id][user_id] = days_late;

                            // Generate predicted status
                            cy.log(`days_allowed: ${days_allowed}, days_late: ${days_late}, late_gays_remaining: 0`);
                            const status = predictedStatus(days_allowed, days_late, 0);
            
                            // Find late day status within the row in the late day usage table
                            cy.get('td[data-before-content="Event/Assignment"]')
                                .contains(g_title)
                                .siblings('td[data-before-content="Status"]')
                                .contains(status)
                                .should('exist');
                        });
                    cy.logout();
                }
            });

            it('should have 0 late days used on bulk late days table', () => {
                cy.visit(['sample', 'bulk_late_days']);
                cy.login('instructor');

                // All statuses should be bad, 0 late days should be charged
                for (const user_id in all_late_users[g_id]) {
                    cy.get(`[data-user="${user_id}"] > [data-before-content="${g_title}"]`)
                        .contains('0')
                        .should('exist');
                }
            });
        });
    }

    describe('Test changes to late days allowed table', () => {
        const late_days_remaining = {};

        it('should grant students with 2 late days', () => {
            cy.visit(['sample', 'late_days']);
            cy.login('instructor');

            cy.log(all_user_ids);
            cy.log(all_late_users);

            for (const user_id of all_user_ids) {
                const days = Math.floor(Math.random() * 2) + 1;
                // update the number of late days
                cy.get('#user_id').type(user_id);
                cy.get('#datestamp').type('1972-01-01', {force: true});
                cy.get('#user_id').click(); // dismiss the calendar view
                cy.get('#late_days').clear();
                cy.get('#late_days').type(days);
                cy.get('input[type=submit]').click();
                cy.wait(3000); // make sure the late day registered
                late_days_remaining[user_id] = days;
            }
        });

        it('should make bulk late days has been emptied out', () => {
            cy.visit(['sample', 'bulk_late_days']);
            cy.login('instructor');

            for (const test_case of test_info) {
                const { g_id, g_title } = test_case;
                const late_users = all_late_users[g_id];

                for (const user_id in late_users) {
                    // Gradeable # of late days used should be empty
                    cy.get(`[data-user="${user_id}"] > [data-before-content="${g_title}"]`)
                    .then((cell) => expect(cell.text().trim()).to.equal(''));
                    
                    // Remaining late days isnt known, should be empty
                    cy.get(`[data-user="${user_id}"] > [data-before-content="Late Days Remaining"]`)
                        .then((cell) => expect(cell.text().trim()).to.equal(''));
                }
            }
        });

        it('should make sure late day status has updated', () => {
            cy.log(late_days_remaining);

            for (const test_case of test_info) {
                const { g_id, g_title, days_allowed } = test_case;
                const late_users = all_late_users[g_id];

                for (const user_id in late_users) {
                    cy.visit([]);
                    cy.wait(5000);
                    cy.login(user_id);
                    cy.visit(['sample', 'late_table']);

                    // Wait for login change to take place
                    cy.wait(3000);

                    cy.log(`days_allowed: ${days_allowed}, days_late: ${late_users[user_id]}, late_gays_remaining: ${late_days_remaining[user_id]}`);
                    const status = predictedStatus(days_allowed, late_users[user_id], late_days_remaining[user_id]);
                    
                    // Find late day status within the row in the late day usage table
                    cy.get('td[data-before-content="Event/Assignment"]')
                        .contains(g_title)
                        .siblings('td[data-before-content="Status"]')
                        .contains(status)
                        .should('exist');

                    // Update expected late days remaining
                    if (status == 'Late') {
                        late_days_remaining[user_id] -= late_users[user_id];
                    }

                    cy.logout();
                }
            }
        });

        it('should remove late day cache after deletion of late days', () => {
            cy.visit(['sample', 'late_days']);
            cy.login('instructor');

            const deleteLateDays = () => {
                cy.get('div.content').then((table) => {
                    if (table.find('td[data-before-content="Delete"]').length > 0) {
                        cy.wrap(table).find('td[data-before-content="Delete"]').first().click();
                        cy.wait(10000);
                        deleteLateDays();
                    }
                });
            };
            
            // Delete late day entry if any exist
            deleteLateDays();

            // View bulk late day changes
            cy.visit(['sample', 'bulk_late_days']);

            for (const test_case of test_info) {
                const { g_id, g_title } = test_case;
                const late_users = all_late_users[g_id];

                for (var user_id in late_users) {
                    // Gradeable # of late days used should be empty
                    cy.get(`[data-user="${user_id}"] > [data-before-content="${g_title}"]`)
                    .then((cell) => expect(cell.text().trim()).to.equal(''));
                    
                    // Remaining late days isnt known, should be empty
                    cy.get(`[data-user="${user_id}"] > [data-before-content="Late Days Remaining"]`)
                        .then((cell) => expect(cell.text().trim()).to.equal(''));
                }
            }
        });
    });

    describe('Test changes to late day extensions', () => {
        const late_day_exception = {};

        before(() => {
            cy.visit(['sample', 'bulk_late_days']);
            cy.login('instructor');
            calculateCache();
        });

        it('should grant students with extensions', () => {
            cy.visit(['sample', 'extensions']);

            for (const test_case of test_info) {
                const { g_id, g_title } = test_case;
                const late_users = all_late_users[g_id];
                late_day_exception[g_id] = {};

                cy.get('#gradeable-select').select(g_title);

                for (const user_id in late_users) {
                    const days = Math.floor(Math.random() * 2) + 1;
                    // update the number of late days
                    cy.get('#user_id').type(user_id);
                    cy.get('#late-days').type(days, {force: true});
                    cy.get('#extensions-form')
                      .find('a')
                      .contains('Submit')
                      .click();
                    cy.wait(4000); // make sure the late day registered
                    late_day_exception[g_id][user_id] = days;
                }
            }
        });

        it('should make bulk late days has been emptied out', () => {
            cy.visit(['sample', 'bulk_late_days']);
            cy.login('instructor');

            for (const test_case of test_info) {
                const { g_id, g_title } = test_case;
                const late_users = all_late_users[g_id];

                for (var user_id in late_users) {
                    // Gradeable # of late days used should be empty
                    cy.get(`[data-user="${user_id}"] > [data-before-content="${g_title}"]`)
                    .then((cell) => expect(cell.text().trim()).to.equal(''));
                    
                    // Remaining late days isnt known, should be empty
                    cy.get(`[data-user="${user_id}"] > [data-before-content="Late Days Remaining"]`)
                        .then((cell) => expect(cell.text().trim()).to.equal(''));
                }
            }
        });

        it('should make sure late day status has updated', () => {
            for (const test_case of test_info) {
                const { g_id, g_title, days_allowed } = test_case;
                const late_users = all_late_users[g_id];

                cy.log(late_day_exception);
                for (var user_id in late_users) {
                    cy.visit([]);
                    cy.wait(3000);
                    cy.login(user_id);
                    cy.visit(['sample', 'late_table']);

                    // Wait for login change to take place
                    cy.wait(3000);

                    cy.log(`days_allowed: ${days_allowed}, days_late: ${late_users[user_id] - late_day_exception[g_id][user_id]}, remaining: ${0}`)
                    const status = predictedStatus(days_allowed, late_users[user_id] - late_day_exception[g_id][user_id], 0);
                    
                    // Find late day status within the row in the late day usage table
                    cy.get('td[data-before-content="Event/Assignment"]')
                        .contains(g_title)
                        .siblings('td[data-before-content="Status"]')
                        .contains(status)
                        .should('exist');

                    cy.logout();
                }
            }
        });

        it('should remove late day cache after deletion of extension', () => {
            cy.visit(['sample', 'extensions']);
            cy.login('instructor');

            const deleteExtensions = () => {
                cy.get('body').then((body) => {
                    if (body.find('#extensions-table').length > 0) {
                        cy.wrap(body).find('#extensions-table > tbody > tr > td > a').first().click();
                        cy.wait(10000);
                        deleteExtensions();
                    }
                });
            };
            
            // Delete late day extension if any exist
            for (const test_case of test_info) {
                cy.get('#gradeable-select').select(test_case.g_title);
                deleteExtensions();
            }

            // View bulk late day changes
            cy.visit(['sample', 'bulk_late_days']);

            for (const test_case of test_info) {
                const { g_id, g_title } = test_case;
                const late_users = all_late_users[g_id];

                for (var user_id in late_users) {
                    // Gradeable # of late days used should be empty
                    cy.get(`[data-user="${user_id}"] > [data-before-content="${g_title}"]`)
                    .then((cell) => expect(cell.text().trim()).to.equal(''));
                    
                    // Remaining late days isnt known, should be empty
                    cy.get(`[data-user="${user_id}"] > [data-before-content="Late Days Remaining"]`)
                        .then((cell) => expect(cell.text().trim()).to.equal(''));
                }
            }
        });
    });

    describe('Test changes to gradeable info', () => {
        const { g_id: g_id1, g_title: g_title1 } = test_info[0];
        const { g_id: g_id2, g_title: g_title2 } = test_info[1];

        beforeEach(() => {
            cy.visit(['sample', 'bulk_late_days']);
            cy.login('instructor');
            calculateCache();

            cy.visit(['sample', 'gradeable', g_id2, 'update']);
            cy.wait(5000);

            cy.get('.breadcrumb > span').should('have.text', 'Edit Gradeable');

            // Go to Dates tab
            cy.get('a').contains('Dates').click();
        });
        
        it('Changes gradeable due date', () => {
            cy.get('#date_due')
              .clear()
              .type('1972-01-02 11:59:59')
              .click()
            cy.get('#late_days').click(); // Dismiss calender and trigger save

            cy.get('#save_status').should('have.text', 'Saving...');

            cy.get('#save_status', {timeout:10000}).should('have.text', 'All Changes Saved');

            cy.visit(['sample', 'bulk_late_days']);
            cy.wait(5000);

            // Gradeable # of late days used should be empty
            cy.get(`#late-day-table > tbody > tr > [data-before-content="${g_title1}"] ~`)
            .then((cell) => expect(cell.text().trim()).to.equal(''));
            
        });

        it('Changes gradeable due date back', () => {
            cy.get('#date_due')
              .clear()
              .type('1972-01-01 11:59:59')
              .click()
            cy.get('#late_days').click(); // Dismiss calender and trigger save

            cy.get('#save_status').should('have.text', 'Saving...');

            cy.get('#save_status', {timeout:10000}).should('have.text', 'All Changes Saved');

            cy.visit(['sample', 'bulk_late_days']);
            cy.wait(5000);

            cy.get(`#late-day-table > tbody > tr > [data-before-content="${g_title1}"] ~`)
            .then((cell) => expect(cell.text().trim()).to.equal(''));
        });

        it('Disables gradeable due date', () => {
            cy.get('#has_due_date_no').check()

            cy.get('#save_status').should('have.text', 'Saving...');

            cy.get('#save_status', {timeout:10000}).should('have.text', 'All Changes Saved');

            cy.visit(['sample', 'bulk_late_days']);
            cy.wait(5000);

            // Bulk late days should not have gradeable title
            cy.get(`#late-day-table > tbody > tr > [data-before-content="${g_title2}"]`).should('have.length', 0);

            cy.get(`#late-day-table > tbody > tr > [data-before-content="${g_title1}"] ~`)
            .then((cell) => expect(cell.text().trim()).to.equal(''));
        });

        it('Re-enables gradeable due date', () => {
            cy.get('#has_due_date_yes').check()

            cy.get('#save_status').should('have.text', 'Saving...');

            cy.get('#save_status', {timeout:10000}).should('have.text', 'All Changes Saved');

            cy.visit(['sample', 'bulk_late_days']);
            cy.wait(5000);

             // Bulk late days should not have gradeable title
             cy.get(`#late-day-table > tbody > tr > [data-before-content="${g_title2}"]`).should('have.length.gt', 0);

            cy.get(`#late-day-table > tbody > tr > [data-before-content="${g_title1}"] ~`)
            .then((cell) => expect(cell.text().trim()).to.equal(''));
        });

        it('Disables late days', () => {
            cy.get('#no_late_submission').check()

            cy.get('#save_status').should('have.text', 'Saving...');

            cy.get('#save_status', {timeout:10000}).should('have.text', 'All Changes Saved');

            cy.visit(['sample', 'bulk_late_days']);
            cy.wait(5000);

            // Bulk late days should not have gradeable title
            cy.get(`#late-day-table > tbody > tr > [data-before-content="${g_title2}"]`).should('have.length', 0);

            cy.get(`#late-day-table > tbody > tr > [data-before-content="${g_title1}"] ~`)
            .then((cell) => expect(cell.text().trim()).to.equal(''));
        });

        it('Re-enables late days', () => {
            cy.get('#yes_late_submission').check()

            cy.get('#save_status').should('have.text', 'Saving...');

            cy.get('#save_status', {timeout:10000}).should('have.text', 'All Changes Saved');

            cy.visit(['sample', 'bulk_late_days']);
            cy.wait(5000);

            // Bulk late days should not have gradeable title
            cy.get(`#late-day-table > tbody > tr > [data-before-content="${g_title2}"]`).should('have.length.gt', 0);

            cy.get(`#late-day-table > tbody > tr > [data-before-content="${g_title1}"] ~`)
            .then((cell) => expect(cell.text().trim()).to.equal(''));
        });

        it('Changes late days allowed', () => {
            cy.get('#late_days')
              .clear()
              .type('1')
              .click()
            cy.get('#date_due').click(); // Dismiss calender and trigger save

            cy.get('#save_status').should('have.text', 'Saving...');

            cy.get('#save_status', {timeout:10000}).should('have.text', 'All Changes Saved');

            cy.visit(['sample', 'bulk_late_days']);
            cy.wait(5000);

            cy.get(`#late-day-table > tbody > tr > [data-before-content="${g_title1}"] ~`)
            .then((cell) => expect(cell.text().trim()).to.equal(''));
        });
    });

    describe('Test changes to gradeable versions', () => {
        const { g_id: g_id1, g_title: g_title1 } = test_info[0];
        const { g_id: g_id2, g_title: g_title2 } = test_info[1];
        const filename = 'submission.txt';
        before(() => {
            cy.writeFile(`cypress/fixtures/${filename}`, 'test');
        });

        beforeEach(() => {
            cy.visit(['sample', 'bulk_late_days']);
            cy.login('instructor');
            calculateCache();

            cy.visit(['sample', 'gradeable', g_id2]);
            cy.wait(5000);
        });
        
        it('Adds a new submission', () => {
            // Make student submission
            cy.get('#radio-student').check();
            cy.get('#user_id').type('student');

            // attatch file
            cy.get('#input-file1').attachFile(filename);
            cy.get('#submit').click();
            cy.wait(5000);

            // Confirm dialog box
            cy.get('#previous-submission-form')
              .find('input')
              .contains('Submit')
              .click();
            cy.wait(5000);

            // Check cache
            cy.visit(['sample', 'bulk_late_days']);
            cy.wait(5000);
            cy.get(`[data-user-content="student"][data-before-content="${g_title1}"] ~`)
            .then((cell) => expect(cell.text().trim()).to.equal(''));
        });

        it('Cancels submission', () => {
            // Click do not grade
            cy.get('#do_not_grade').click();
            cy.wait(5000);
            // Note: page refresh triggers a late day recalulation for banner text

            // Check cache
            cy.visit(['sample', 'late_table']);
            cy.wait(5000);

            cy.get('td[data-before-content="Event/Assignment"]')
                .contains(g_title2)
                .siblings('td[data-before-content="Status"]')
                .contains('Cancelled Submission')
                .should('exist');
        });

        it('Add gradeable version back', () => {
            // Select gradeable
            cy.get('#submission-version-select').select('1');
            cy.wait(5000);

            // Change the version to grade
            cy.get('#version_change').click();
            cy.wait(5000);
            // Note: page refresh triggers a late day recalulation for banner text

            // Check cache
            cy.visit(['sample', 'late_table']);
            cy.wait(5000);

            cy.get('td[data-before-content="Event/Assignment"]')
                .contains(g_title2)
                .siblings('td[data-before-content="Status"]')
                .should('not.contain', 'Cancelled Submission')
                .should('exist');
        })


    });

    describe('Test gradable creation/deletion', () => {
        const g_id = 'delete_me';
        const g_title = 'Delete Me';
        const prev_g_title = 'Grading Team Homework';

        beforeEach(() => {
            cy.visit(['sample', 'bulk_late_days']);
            cy.login('instructor');
            cy.wait(5000);
            calculateCache();
        });

        it('Creates a gradeable', () => {
            cy.visit(['sample', 'gradeable']);
            cy.wait(5000);

            // Enter gradeable info
            cy.get('#g_title').type(g_title);
            cy.get('#g_id').type(g_id);
            cy.get('#radio_ef_student_upload').check();

            // Collect all responses
            var finished = false;
            cy.intercept({ url: buildUrl(['sample', 'gradeable', g_id, 'build_status']) }, req => {
                req.on('response', (res) => {
                    finished = finished || (JSON.parse(res.body).data == true);
                })
              })

            //Intercept build status get call
            const spy = cy.spy();
            cy.intercept('GET', buildUrl(['sample', 'gradeable', g_id, 'build_status']), spy);

            // Create Gradeable
            cy.get('#create-gradeable-btn').click();
            cy.wait(5000);
        
            // Wait for rebuild response to say complete
            cy.wrap({}, { timeout: 90000 })
            .should(() => {
                expect(finished).to.equal(true);
            });

            // Check that cache is deleted
            cy.visit(['sample', 'bulk_late_days']);
            cy.wait(5000);
            cy.get(`#late-day-table > tbody > tr > [data-before-content="${prev_g_title}"] ~`)
            .then((cell) => expect(cell.text().trim()).to.equal(''));
        });

        it('Deletes a gradeable', () => {
            cy.visit(['sample']);
            cy.get(`#${g_id} > div > a.fa-trash`).click();

            // Confirm delete
            cy.get('form[name="delete-confirmation"]')
              .find('input')
              .contains('Delete')
              .click();
            cy.wait(5000);

            // Check that cache is deleted
            cy.visit(['sample', 'bulk_late_days']);
            cy.wait(5000);
            cy.get(`#late-day-table > tbody > tr > [data-before-content="${prev_g_title}"] ~`)
            .then((cell) => expect(cell.text().trim()).to.equal(''));

        });
    })

    describe('Test changes to initial late days', () => {
        it('Changes default late days', () => {
            cy.visit(['sample', 'config']);
            cy.login('instructor');

            cy.get('#default-student-late-days')
              .clear()
              .type('1');

            // Remove focus to trigger config change
            cy.get('#default-hw-late-days').click();

            cy.visit(['sample', 'bulk_late_days']);
            cy.wait(5000);

            cy.get('[data-before-content="Initial Late Days"]')
            .each((cell) => expect(cell.text().trim()).to.equal('1'));
            cy.get(`#late-day-table > tbody > tr > [data-before-content="Initial Late Days"] ~`)
            .then((cell) => expect(cell.text().trim()).to.equal(''));
        });
    });
});
