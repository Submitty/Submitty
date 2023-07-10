

describe('Testing functionality of Autograder Results', () => {
    beforeEach(() => {
        //cy.visit(['sample','gradeable','closed_team_homework']);
        cy.visit('http://localhost:1511/courses/s23/sample/gradeable/closed_team_homework');
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
        cy.get('.loading-tools-hide').eq(0).click();
        cy.get('#testcase_1').should('have.css', 'display').and('match', /none/);
    });

})