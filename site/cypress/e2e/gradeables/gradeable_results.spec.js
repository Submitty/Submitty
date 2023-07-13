

describe('Testing functionality of Autograder Results', () => {
    beforeEach(() => {
        cy.visit(['sample','gradeable','closed_team_homework']);
        cy.login('student');

    });

    it('Should display confetti', () => {
        cy.get('#confetti_canvas').should('not.be.visible');
        cy.get('div[onclick="addConfetti();"]').click();
        cy.get('#confetti_canvas').should('be.visible');
    });

    it('Should show and hide details', () => {
        //Open
        cy.get('#testcase_1').should('not.be.visible');
        cy.get('.loading-tools-show').eq(1).click();
        cy.get('#testcase_1').should('be.visible');
        //Close
        cy.get('.loading-tools-hide').first().click();
        cy.get('#testcase_1').should('not.be.visible');
    });

    it('Should expand and collapse all test cases', () => {
        //Open all
        cy.get('.loading-tools-show').first().click();
        cy.get('#testcase_1').should('be.visible');
        cy.get('#testcase_2').should('be.visible');
        cy.get('#testcase_3').should('be.visible');
        cy.get('#testcase_6').should('be.visible');
        cy.get('#testcase_8').should('be.visible');
        //Close all
        cy.get('.loading-tools-hide').first().click();
        cy.get('#testcase_1').should('not.be.visible');
        cy.get('#testcase_2').should('not.be.visible')
        cy.get('#testcase_3').should('not.be.visible')
        cy.get('#testcase_6').should('not.be.visible')
        cy.get('#testcase_8').should('not.be.visible')
    });

    it('Should cancel loading', () => {
        //Open
        cy.get('#testcase_1').should('not.be.visible');
        cy.get('.loading-tools-show').eq(1).click();
        cy.get('.loading-tools-in-progress').first().click(); //cancel
        cy.get('#testcase_1').should('not.be.visible');
    });

});
