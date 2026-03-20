import { skipOn } from '@cypress/skip-test';

skipOn(Cypress.env('run_area') === 'CI', () => {
    describe('Testing functionality of Autograder Results', () => {
        beforeEach(() => {
            cy.visit(['sample', 'gradeable', 'closed_team_homework']);
            cy.login('student');
        });

        it('Should display confetti', () => {
            cy.get('#confetti_canvas').should('have.css', 'display').and('match', /none/);
            cy.get('.box-title-total').first().click();
            cy.get('#confetti_canvas').should('have.css', 'display').and('not.match', /none/);
        });

        it('Should show and hide details', () => {
            cy.scrollTo('bottom');
            //  Open
            cy.get('#testcase_1').scrollIntoView();
            cy.get('#testcase_1').should('not.be.visible');
            cy.get('.loading-tools-show').eq(1).as('loading-tool-show');
            cy.get('@loading-tool-show').scrollIntoView();
            cy.get('@loading-tool-show').click();
            cy.get('#testcase_1').scrollIntoView();
            cy.get('#testcase_1').should('be.visible');
            //  Close
            cy.get('.loading-tools-hide').eq(1).as('loading-tool-hide');
            cy.get('@loading-tool-hide').scrollIntoView();
            cy.get('@loading-tool-hide').click();
            cy.get('#testcase_1').scrollIntoView();
            cy.get('#testcase_1').should('not.be.visible');
        });

        it('Should expand and collapse all test cases', () => {
            cy.scrollTo('bottom');
            // Open all
            cy.get('.loading-tools-show').first().click();
            cy.get('#testcase_1').scrollIntoView();
            cy.get('#testcase_1').should('be.visible');
            cy.get('#testcase_2').scrollIntoView();
            cy.get('#testcase_2').should('be.visible');
            cy.get('#testcase_3').scrollIntoView();
            cy.get('#testcase_3').should('be.visible');
            cy.get('#testcase_6').scrollIntoView();
            cy.get('#testcase_6').should('be.visible');
            cy.get('#testcase_8').scrollIntoView();
            cy.get('#testcase_8').should('be.visible');
            //  Close all
            cy.get('.loading-tools-hide').first().click();
            cy.get('#testcase_1').scrollIntoView();
            cy.get('#testcase_1').should('not.be.visible');
            cy.get('#testcase_2').scrollIntoView();
            cy.get('#testcase_2').should('not.be.visible');
            cy.get('#testcase_3').scrollIntoView();
            cy.get('#testcase_3').should('not.be.visible');
            cy.get('#testcase_6').scrollIntoView();
            cy.get('#testcase_6').should('not.be.visible');
            cy.get('#testcase_8').scrollIntoView();
            cy.get('#testcase_8').should('not.be.visible');
        });

        it('Should cancel loading', () => {
            cy.scrollTo('bottom');
            // Open
            cy.get('#testcase_1').should('not.be.visible');
            cy.get('.loading-tools-show').eq(1).click();
            cy.get('.loading-tools-in-progress').first().click(); // cancel
            cy.get('#testcase_1').should('not.be.visible');
        });
    });
});
