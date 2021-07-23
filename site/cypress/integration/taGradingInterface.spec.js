import {buildUrl} from '../support/utils.js';

// describe('Test cases relating to the grading index page', () => {
//     beforeEach(() => {
//         cy.visit(buildUrl(['sample', 'gradeable', 'grading_homework', 'grading', 'details'], true) + '?view=all');
//     });
//
//     it('users should not be visible in anonymous mode', () => {
//         cy.login('instructor')
//         cy.get('#details-table').contains('aphacker');
//         cy.get('#details-table').contains('Alyssa P');
//         cy.get('#details-table').contains('Hacker');
//
//         cy.get('#toggle-anon-button').click();
//
//         cy.get('#details-table').should('not.contain', 'aphacker');
//         cy.get('#details-table').should('not.contain', 'Alyssa P');
//         cy.get('#details-table').should('not.contain', 'Hacker');
//
//         cy.get('#toggle-anon-button').click();
//
//         cy.get('#details-table').contains('aphacker');
//         cy.get('#details-table').contains('Alyssa P');
//         cy.get('#details-table').contains('Hacker');
//     });
//
//     it('the instructor should have no assigned sections', () => {
//         cy.login('instructor')
//         cy.get('.content').contains('View Your Sections').click();
//         cy.get('.info > td').contains('No Grading To Be Done! :)');
//     });
//
//     it('ta2 should be assigned to section 1', () => {
//         cy.login('ta2');
//
//         cy.get('#details-table').contains('Section 1');
//         cy.get('#details-table').contains('Section 2');
//
//         cy.get('.content').contains('View Your Sections').click();
//
//         cy.get('#details-table').contains('Section 1');
//         cy.get('#details-table').should('not.contain', 'Section 2');
//
//         cy.get('.content').contains('View All').click();
//
//         cy.get('#details-table').contains('Section 1');
//         cy.get('#details-table').contains('Section 2');
//     });
//
//     it('grader should only be able to see their assigned sections', () => {
//         cy.login('grader')
//
//         cy.get('#details-table').should('not.contain', 'Section 1');
//         cy.get('#details-table').should('not.contain', 'Section 2');
//         cy.get('#details-table').contains('Section 4');
//         cy.get('#details-table').contains('Section 5');
//     });
//
//     it('students should not be able to view the grading index', () => {
//         cy.login('student');
//
//         cy.get('#error-0').contains("You do not have permission to grade Grading Homework");
//         cy.url().should('eq', `${Cypress.config('baseUrl')}/${buildUrl(['sample'])}`);
//     });
// });


describe('Test cases relating to the grading of an assignment', () => {
    beforeEach(() => {
        cy.visit(buildUrl(['sample', 'gradeable', 'grading_homework', 'grading', 'details'], true) + '?view=all');
        cy.login('instructor')
        cy.get('#details-table').contains('aphacker').siblings(':nth-child(8)').click()
    });

    describe('Test cases relating to the functionality of the control bar', () => {
        it('Test that home button returns to the grading index page', () => {
            cy.get('#main-page').click()
            cy.url().should('include', buildUrl(['sample', 'gradeable', 'grading_homework', 'grading', 'details'], true))
        });

        it('Test that home button returns to the grading index page', () => {
        });
    });

    // describe('Test cases relating to the "files" pane', () => {
    //     // it would be ideal to click on other types of folders too but unfortunately no autograding has been done at this point
    //     it('try clicking on the submissions folder and see that it expands', () => {
    //         cy.get('#submission_browser_btn').click()
    //         cy.get('#submission_browser').contains('.submit.timestamp').should('not.be.visible')
    //         cy.get('#submissions').click()
    //         cy.get('#submission_browser').contains('.submit.timestamp').should('be.visible')
    //     });
    //
    //     it('try clicking a filename to make sure a code box appears with the right contents', () => {
    //         cy.get('#submission_browser_btn').click()
    //         cy.get('#submissions').click()
    //         cy.get(':nth-child(1) > .file-viewer > .openAllFilesubmissions').click()
    //         cy.get('#file_viewer_sd1f1_iframe').should('be.visible')
    //     });
    // });
});
