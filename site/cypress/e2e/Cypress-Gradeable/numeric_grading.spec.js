describe('Test that in the sample numeric grading test that', () => {
  beforeEach(() => {
    cy.visit(['sample', 'gradeable', 'grading_test', 'grading']);
    cy.login('ta');
  });

  it('double click can activate typing mode', () => {
    cy.get('#cell-1-0-0').dblclick();
    cy.get('input#cell-1-0-0.option-small-box.cell-grade').should('exist');
  });

  it('double click can simulate typing within a cell', () => {
    cy.get('#cell-1-0-0').dblclick().type('2');
    cy.get('#cell-1-0-1').dblclick().type('2.5');
    cy.get('#total-1-0').click().should('contain.text', '4.5');
  });
});

describe('Test that in typing mode', () => {
  before(() => {
    cy.visit(['sample', 'gradeable', 'grading_test', 'grading']);
    cy.login('ta');
    // reset values
    cy.get('#cell-1-0-0').clear().type('4');
    cy.get('#cell-1-0-1').clear().type('2');
    cy.get('#total-1-0').click();
    cy.logout();
  });

  it('arrow keys can move the cursor within the cell', () => {
    cy.visit(['sample', 'gradeable', 'grading_test', 'grading']);
    cy.login('ta');
    cy.get('#cell-1-0-0').dblclick();
    cy.get('#cell-1-0-0').type('{leftarrow}').type('0');
    cy.get('#cell-1-0-1').dblclick()
    cy.get('#cell-1-0-1').type('{rightarrow}').type('.5');
    cy.get('#total-1-0').click().should('contain.text', '6.5');
  });
});
