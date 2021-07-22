import {buildUrl} from '../support/utils.js';

describe('Test cases involving the late days allowed page', () => {
    describe('Test accessing page as a student', () => {
        it('should not allow access', () => {
            cy.visit(['sample', 'late_days']);
            cy.login('student');
            cy.get('.content').contains("You don't have access to this page");
        });
    });

    describe('Test accessing page as an instructor', () => {
        beforeEach(() => {
            cy.visit(['sample', 'late_days']);
            cy.login('instructor');
        });

        it('make sure the form is blank', () => {
            cy.get('#user_id').should('be.empty');
            cy.get('#datestamp').should('be.empty');
            cy.get('#late_days').should('be.empty');
            cy.get('#csv_option_overwrite_all').should('be.checked');
            cy.get('#empty-table').contains('No late days are currently entered');
        });

        it('check invalid user ID', () => {
            cy.get('#user_id').type('nonexistentuser123');
            cy.get('input[type=submit]').click();
            cy.get('#error-0 > span').should('be.visible');
        });

        it('check invalid datestamp', () => {
            cy.get('#user_id').type('bitdiddle');
            cy.get('#datestamp').type("this/isn't/a/date");
            cy.get('input[type=submit]').click();
            cy.get('#error-0 > span').should('be.visible');
        });

        it('blank late days are invalid', () => {
            cy.get('#user_id').type('bitdiddle');
            cy.get('#datestamp').type('2021-01-01');
            cy.get('input[type=submit]').click();
            cy.get('#error-0 > span').should('be.visible');
        });

        it('negative late days are invalid', () => {
            cy.get('#user_id').type('bitdiddle');
            cy.get('#datestamp').type('2021-01-01');
            cy.get('#user_id').click(); // dismiss the calendar view
            cy.get('#late_days').type('-1');
            cy.get('input[type=submit]').click();
            cy.get('#error-0 > span').should('be.visible');
        });

        it('decimal late days are invalid', () => {
            cy.get('#user_id').type('bitdiddle');
            cy.get('#datestamp').type('2021-01-01');
            cy.get('#user_id').click(); // dismiss the calendar view
            cy.get('#late_days').type('5.5');
            cy.get('input[type=submit]').click();
            cy.get('#error-0 > span').should('be.visible');
        });

        it('correctly add a late day and then update it', () => {
            // add some late days for bitdiddle
            cy.get('#user_id').type('bitdiddle');
            cy.get('#datestamp').type('2021-01-01');
            cy.get('#user_id').click(); // dismiss the calendar view
            cy.get('#late_days').type('3');
            cy.get('input[type=submit]').click();
            cy.wait(1000); // make sure the late day registered

            // make sure table has right values
            cy.get('#late-day-table > tbody > tr > :nth-child(1)').contains('bitdiddle');
            cy.get('#late-day-table > tbody > tr > :nth-child(2)').contains('Ben');
            cy.get('#late-day-table > tbody > tr > :nth-child(3)').contains('Bitdiddle');
            cy.get('#late-day-table > tbody > tr > :nth-child(4)').contains('3');
            cy.get('#late-day-table > tbody > tr > :nth-child(5)').contains('01/01/2021');

            // login as bitdiddle and check that they have proper number of late days
            cy.logout();
            cy.login('bitdiddle');
            cy.visit(['sample', 'late_table']);
            cy.get('#late-day-history').contains('01/01/2021: Earned 3 late day(s)');
            cy.get('.content').contains('Total late days remaining for future assignments: 3');

            // logout and log back in as the instructor
            cy.logout();
            cy.login('instructor');
            cy.visit(['sample', 'late_days']);

            // update the number of late days
            cy.get('#user_id').type('bitdiddle');
            cy.get('#datestamp').type('2021-01-01');
            cy.get('#user_id').click(); // dismiss the calendar view
            cy.get('#late_days').clear();
            cy.get('#late_days').type('5');
            cy.get('input[type=submit]').click();
            cy.wait(1000); // make sure the late day registered

            // make sure the table has the updated values
            cy.get('#late-day-table > tbody > tr > :nth-child(1)').contains('bitdiddle');
            cy.get('#late-day-table > tbody > tr > :nth-child(2)').contains('Ben');
            cy.get('#late-day-table > tbody > tr > :nth-child(3)').contains('Bitdiddle');
            cy.get('#late-day-table > tbody > tr > :nth-child(4)').contains('5');
            cy.get('#late-day-table > tbody > tr > :nth-child(5)').contains('01/01/2021');

            // logout and log back in as bitdiddle to make sure the number of late days has been updated correctly
            cy.logout();
            cy.login('bitdiddle');
            cy.visit(['sample', 'late_table']);
            cy.get('#late-day-history').contains('01/01/2021: Earned 5 late day(s)');
            cy.get('.content').contains('Total late days remaining for future assignments: 5');

            // logout and log back in as the instructor
            cy.logout();
            cy.login('instructor');
            cy.visit(['sample', 'late_days']);

            // delete the late day
            cy.get('#late-day-table > tbody > tr > :nth-child(6)').click();
            cy.wait(1000); // make sure the late day removal was registered

            // assert that the table is now empty
            cy.get('#empty-table').contains('No late days are currently entered');

            // logout and log back in as bitdiddle to make sure there are no remaining late days
            cy.logout();
            cy.login('bitdiddle');
            cy.visit(['sample', 'late_table']);
            cy.wait(1000); // just a quick pause to make sure the page has loaded fully
            cy.get('.content').contains('Total late days remaining for future assignments: 0');
        });
    });
});
