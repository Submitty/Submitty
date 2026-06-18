import { skipOn } from '@cypress/skip-test';

const autograding_status_path = 'autograding_status';

// Assumes autograding shipper has been restarted
skipOn(Cypress.env('run_area') === 'CI', () => {
    describe('Pre autograding test', () => {
        before(() => {
            cy.login();
            cy.visit(autograding_status_path);
        });

        it('should show the gradeables being grading', () => {
            cy.viewport(1920, 1200);
            cy.get('#machine-table tbody tr').eq(4).should('exist');
        });
    });
});
