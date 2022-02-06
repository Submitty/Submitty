import {getCurrentSemester} from '../support/utils.js';
import {skipOn} from '@cypress/skip-test';

const autograding_status_path = 'autograding_status';

// Assumes autograding workers have been turned off
skipOn(Cypress.env('run_area') === 'CI', () => {
    describe('Pre autograding test', () => {
        before(() => {
            cy.visit('/');
            cy.login();
            cy.wait(500);
            cy.viewport(1920,1200);
            cy.visit(autograding_status_path);
        });

        afterEach(() => {
            cy.logout();
        });

        it('Should start at pause update', () => {
            cy.get('#toggle-btn').should('have.text', 'Pause Update').click().should('have.text', 'Resume Update');
            cy.get('.alert-success').invoke('text').should('contain', 'Update has been stopped');

            // Check that the table isn't gaining new entries
            cy.wait(7500);
            cy.get('#autograding-status-table tbody tr').eq(1).should('not.exist');

            cy.get('#toggle-btn').should('have.text', 'Resume Update').click().should('have.text', 'Pause Update');
            cy.get('.alert-success').invoke('text').should('contain', 'Update has been resumed');

            // Check that the table is gaining new entries
            cy.wait(7500);
            cy.get('#autograding-status-table tbody tr').eq(1).should('exist');

        });

        it('Should show newly added autograding jobs', () => {
            cy.visit('/');
            cy.login();
            cy.wait(500);

            // trigger regrade for a gradeable
            cy.visit(`/courses/${getCurrentSemester()}/sample/gradeable/closed_homework/grading/details`);
            cy.wait(500);
            cy.get('.regrade-btn').click();
            cy.wait(200);
            cy.get('.alert-success').invoke('text').should('contain', '101 submissions added to queue for regrading');
            cy.visit(autograding_status_path);
            cy.get('#toggle-btn').should('have.text', 'Pause Update').click().should('have.text', 'Resume Update');
            cy.get('#course-table tbody tr td').eq(0).then(element => cy.get(element).should('contain', getCurrentSemester()));
            cy.get('#course-table tbody tr td').eq(1).then(element => cy.get(element).should('contain', 'sample'));
            cy.get('#course-table tbody tr td').should('contain', 'closed_homework');
            cy.get('#course-table tbody tr td').eq(3).then(element => cy.get(element).should('contain', ''));
            cy.get('#course-table tbody tr td').eq(4).then(element => cy.get(element).should('contain', '101'));

            cy.wait(500);
            cy.get('#autograding-status-table tbody tr td').eq(1).then(element => cy.get(element).should('contain', ''));
            cy.get('#autograding-status-table tbody tr td').eq(2).then(element => cy.get(element).should('contain', ''));
            cy.get('#autograding-status-table tbody tr td').eq(3).then(element => cy.get(element).should('contain', ''));
            cy.get('#autograding-status-table tbody tr td').eq(4).then(element => cy.get(element).should('contain', '101'));
            cy.get('#autograding-status-table tbody tr td').eq(5).then(element => cy.get(element).should('contain', ''));
            cy.get('#autograding-status-table tbody tr td').eq(6).then(element => cy.get(element).should('contain', ''));
            cy.get('#autograding-status-table tbody tr td').eq(7).then(element => cy.get(element).should('contain', ''));
            cy.get('#autograding-status-table tbody tr td').eq(8).then(element => cy.get(element).should('contain', ''));
            cy.get('#autograding-status-table tbody tr td').eq(9).then(element => cy.get(element).should('contain', ''));
            cy.get('#autograding-status-table tbody tr td').eq(10).then(element => cy.get(element).should('contain', '101'));

            // add a non regrade job and see that the site is updated

            cy.visit(`/courses/${getCurrentSemester()}/sample/gradeable/future_no_tas_homework`);
            cy.wait(500);
            cy.get('.upload-box').attachFile('sample_upload.py', { subjectType: 'drag-n-drop' });
            cy.get('#submit').click();
            cy.wait(500);

            cy.visit(autograding_status_path);

            cy.get('#toggle-btn').should('have.text', 'Pause Update').click().should('have.text', 'Resume Update');
            cy.get('#course-table tbody tr td').eq(0).then(element => cy.get(element).should('contain', getCurrentSemester()));
            cy.get('#course-table tbody tr td').eq(1).then(element => cy.get(element).should('contain', 'sample'));
            cy.get('#course-table tbody tr td').should('contain', 'closed_homework');
            cy.get('#course-table tbody tr td').should('contain', 'future_no_tas_homework');
            cy.get('#course-table tbody tr td').should('contain', '1');

            cy.get('#autograding-status-table tbody tr td').eq(1).then(element => cy.get(element).should('contain', ''));
            cy.get('#autograding-status-table tbody tr td').eq(2).then(element => cy.get(element).should('contain', '1'));
            cy.get('#autograding-status-table tbody tr td').eq(3).then(element => cy.get(element).should('contain', ''));
            cy.get('#autograding-status-table tbody tr td').eq(4).then(element => cy.get(element).should('contain', '101'));
            cy.get('#autograding-status-table tbody tr td').eq(5).then(element => cy.get(element).should('contain', ''));
            cy.get('#autograding-status-table tbody tr td').eq(6).then(element => cy.get(element).should('contain', ''));
            cy.get('#autograding-status-table tbody tr td').eq(7).then(element => cy.get(element).should('contain', ''));
            cy.get('#autograding-status-table tbody tr td').eq(8).then(element => cy.get(element).should('contain', ''));
            cy.get('#autograding-status-table tbody tr td').eq(9).then(element => cy.get(element).should('contain', ''));
            cy.get('#autograding-status-table tbody tr td').eq(10).then(element => cy.get(element).should('contain', '102'));

        });

        it('should only allow instructor level users', () => {
            // attempt to visit page as student
            cy.visit([]);
            cy.login('student');
            cy.visit(autograding_status_path);
            cy.get('#autograding-status-table').should('not.exist');
            cy.visit('/');

            // attempt as a ta (no instructor level access)
            cy.logout();
            cy.login('ta');
            cy.visit(autograding_status_path);
            cy.get('#autograding-status-table').should('not.exist');
            cy.visit('/');

            // attempt as a grader
            cy.logout();
            cy.login('grader');
            cy.visit(autograding_status_path);
            cy.get('#autograding-status-table').should('not.exist');

            cy.visit('/');
        });
    });
});
