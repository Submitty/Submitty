import {buildUrl} from '../support/utils.js';

describe('Test cases relating to the grading index page', () => {
    beforeEach(() => {
        cy.visit(buildUrl(['sample', 'gradeable', 'grading_homework', 'grading', 'details'], true) + '?view=all');
    });

    it('users should not be visible in anonymous mode', () => {
        cy.login('instructor')
        cy.get('#details-table').contains('aphacker');
        cy.get('#details-table').contains('Alyssa P');
        cy.get('#details-table').contains('Hacker');

        cy.get('#toggle-anon-button').click();

        cy.get('#details-table').should('not.contain', 'aphacker');
        cy.get('#details-table').should('not.contain', 'Alyssa P');
        cy.get('#details-table').should('not.contain', 'Hacker');

        cy.get('#toggle-anon-button').click();

        cy.get('#details-table').contains('aphacker');
        cy.get('#details-table').contains('Alyssa P');
        cy.get('#details-table').contains('Hacker');
    });

    it('the instructor should have no assigned sections', () => {
        cy.login('instructor')
        cy.get('.content').contains('View Your Sections').click();
        cy.get('.info > td').contains('No Grading To Be Done! :)');
    });

    it('ta2 should be assigned to section 1', () => {
        cy.login('ta2');

        cy.get('#details-table').contains('Section 1');
        cy.get('#details-table').contains('Section 2');

        cy.get('.content').contains('View Your Sections').click();

        cy.get('#details-table').contains('Section 1');
        cy.get('#details-table').should('not.contain', 'Section 2');

        cy.get('.content').contains('View All').click();

        cy.get('#details-table').contains('Section 1');
        cy.get('#details-table').contains('Section 2');
    });

    it('grader should only be able to see their assigned sections', () => {
        cy.login('grader')

        cy.get('#details-table').should('not.contain', 'Section 1');
        cy.get('#details-table').should('not.contain', 'Section 2');
        cy.get('#details-table').contains('Section 4');
        cy.get('#details-table').contains('Section 5');
    });

    it('students should not be able to view the grading index', () => {
        cy.login('student');

        cy.get('#error-0').contains("You do not have permission to grade Grading Homework");
        cy.url().should('eq', `${Cypress.config('baseUrl')}/${buildUrl(['sample'])}`);
    });

    it('check that the grading index page has the correct table layout', () => {
        cy.login('instructor')

        // check an ungraded student
        cy.get('#details-table').contains('aphacker').within((tr) => {
            cy.get(tr).siblings(':nth-child(1)').should('contain', '1')
            cy.get(tr).siblings(':nth-child(2)').should('contain', '1')
            // tr == the 3rd child already
            cy.get(tr).siblings(':nth-child(4)').should('contain', 'Alyssa P')
            cy.get(tr).siblings(':nth-child(5)').should('contain', 'Hacker')
            cy.get(tr).siblings(':nth-child(6)').should('contain', '0 / 0')
            cy.get(tr).siblings(':nth-child(7)').find('.grader-NULL').should('have.length', 4)
            cy.get(tr).siblings(':nth-child(8)').children('.btn').should('contain', 'Grade')
            cy.get(tr).siblings(':nth-child(9)').should('contain', ' ')
            cy.get(tr).siblings(':nth-child(10)').should('contain', '1')
        });

        // check a graded student
        cy.get('#details-table').contains('hammef').within((tr) => {
            cy.get(tr).siblings(':nth-child(1)').should('contain', '3')
            cy.get(tr).siblings(':nth-child(2)').should('contain', '1')
            // tr == the 3rd child already
            cy.get(tr).siblings(':nth-child(4)').should('contain', 'Felicity')
            cy.get(tr).siblings(':nth-child(5)').should('contain', 'Hammes')
            cy.get(tr).siblings(':nth-child(6)').should('contain', '0 / 0')
            cy.get(tr).siblings(':nth-child(7)').find('.grader-1').should('have.length', 4)
            cy.get(tr).siblings(':nth-child(8)').children('.btn').should('contain', '7 / 12')
            cy.get(tr).siblings(':nth-child(9)').should('contain', '7 / 12')
            cy.get(tr).siblings(':nth-child(10)').should('contain', '1')
        });
    })

    it('check that the grading index page has the correct table layout in anonymous mode', () => {
        cy.login('instructor')
        cy.get('#toggle-anon-button').click()

        // check an ungraded student
        cy.get('#details-table').contains('DF654JKL45QER34').within((tr) => {
            cy.get(tr).siblings(':nth-child(1)').should('contain', '1')
            // tr == the 2rd child already
            cy.get(tr).siblings(':nth-child(3)').find('.grader-NULL').should('have.length', 4)
            cy.get(tr).siblings(':nth-child(4)').children('.btn').should('contain', 'Grade')
            cy.get(tr).siblings(':nth-child(5)').should('contain', '1')
        });

        // check a graded student
        cy.get('#details-table').contains('6bbp1cQ8JdKN3Cf').within((tr) => {
            cy.get(tr).siblings(':nth-child(1)').should('contain', '3')
            // tr == the 2rd child already
            cy.get(tr).siblings(':nth-child(3)').find('.grader-1').should('have.length', 4)
            cy.get(tr).siblings(':nth-child(4)').children('.btn').should('contain', 'Grade')
            cy.get(tr).siblings(':nth-child(5)').should('contain', '1')
        });
    })
});
