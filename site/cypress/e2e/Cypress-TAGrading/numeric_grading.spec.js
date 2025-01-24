describe('Test that in the sample numeric grading test that', () => {
    beforeEach(() => {
        cy.visit(['sample', 'gradeable', 'grading_test', 'grading']);
        cy.login('ta');
    });

    it('double click can activate typing mode', () => {
        cy.get('#cell-1-0-0').dblclick();
        cy.get('input#cell-1-0-0[data-id="96"]').should('exist');
    });

    it('double click can simulate typing within a cell', () => {
        cy.get('#cell-1-0-0').dblclick();
        cy.get('#cell-1-0-0').type('2');
        cy.get('#cell-1-0-1').dblclick();
        cy.get('#cell-1-0-1').type('2.5');
        cy.get('#total-1-0').click();
        cy.get('#total-1-0').should('contain.text', '4.5');
    });
});

describe('Test that in typing mode', () => {
    before(() => {
        cy.visit(['sample', 'gradeable', 'grading_test', 'grading']);
        cy.login('ta');
        // reset values
        cy.get('#cell-1-0-0').clear();
        cy.get('#cell-1-0-0').type('4');
        cy.get('#cell-1-0-1').clear();
        cy.get('#cell-1-0-1').type('2');
        cy.get('#total-1-0').click();
        cy.logout();
    });

    it('arrow keys can move the cursor within the cell', () => {
        cy.visit(['sample', 'gradeable', 'grading_test', 'grading']);
        cy.login('ta');
        cy.get('#cell-1-0-0').dblclick();
        cy.get('#cell-1-0-0').type('{leftarrow}');
        cy.get('#cell-1-0-0').type('0');
        cy.get('#cell-1-0-1').dblclick();
        cy.get('#cell-1-0-1').type('{rightarrow}');
        cy.get('#cell-1-0-1').type('.5');
        cy.get('#total-1-0').click();
        cy.get('#total-1-0').should('contain.text', '6.5');
    });
});
