

describe('Testing functionality of Autograder Results', () => {
    beforeEach(() => {
        cy.visit(['sample','gradeable','closed_team_homework']);
        cy.login('student');

    });

    it('Should display confetti', () => {
        cy.get('#confetti_canvas[style="display: block;"]').should('not.exist');
        cy.get('div[onclick="addConfetti();"]').click();
        cy.get('#confetti_canvas[style="display: block;"]').should('exist');
    });

    it('Should show and hide details', () => {
        //Open
        cy.get('#testcase_1').should('have.css', 'display').and('match', /none/);
        cy.get('.loading-tools-show').eq(1).click();
        cy.get('#testcase_1').should('have.css', 'display').and('not.match', /none/);
        //Close
        cy.get('.loading-tools-hide').first().click();
        cy.get('#testcase_1').should('have.css', 'display').and('match', /none/);
    });

    it('Should expand and collapse all test cases', () => {
        //Open all
        cy.get('.loading-tools-show').first().click();
        cy.get('#testcase_1').should('have.css', 'display').and('not.match', /none/);
        cy.get('#testcase_2').should('have.css', 'display').and('not.match', /none/);
        cy.get('#testcase_3').should('have.css', 'display').and('not.match', /none/);
        cy.get('#testcase_6').should('have.css', 'display').and('not.match', /none/);
        cy.get('#testcase_8').should('have.css', 'display').and('not.match', /none/);
        //Close all
        cy.get('.loading-tools-hide').first().click();
        cy.get('#testcase_1').should('have.css', 'display').and('match', /none/);
        cy.get('#testcase_2').should('have.css', 'display').and('match', /none/);
        cy.get('#testcase_3').should('have.css', 'display').and('match', /none/);
        cy.get('#testcase_6').should('have.css', 'display').and('match', /none/);
        cy.get('#testcase_8').should('have.css', 'display').and('match', /none/);
    });

    it('Should cancel loading', () => {
        //Open
        cy.get('#testcase_1').should('have.css', 'display').and('match', /none/);
        cy.get('.loading-tools-show').eq(1).click();
        cy.get('.loading-tools-in-progress').first().click(); //cancel
        cy.get('#testcase_1').should('have.css', 'display').and('match', /none/);

    });

})