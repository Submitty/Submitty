import {skipOn} from '@cypress/skip-test';

const autograding_status_path = 'autograding_status';

// Assumes autograding shipper has been restarted
skipOn(Cypress.env('run_area') === 'CI', () => {
    describe('Pre autograding test', () => {
        before(() => {
            cy.visit('/');
            cy.login();
            cy.wait(500);
            cy.visit(autograding_status_path);
        });

        it('should show the gradeables being grading', () => {
            cy.get('#machine-table tbody tr').eq(3).should('exist');
            cy.get('#machine-table tbody tr td').should('contain', 'future_no_tas_homework');
            cy.get('#machine-table tbody tr td').should('contain', 'closed_homework');
        });
    });
});
