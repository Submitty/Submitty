/// <reference types="Cypress" />
describe('Visual testing', () => {
    it('autograding markdown testing', () => {
        cy.viewport(1100, 1050);
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=jKjodWaRdEV9pBb&sort=id&direction=ASC']);
        cy.get('#tc_expand_all').find('.loading-tools').click();
        cy.get('.box-block').scrollIntoView().compareSnapshot('autograding_code',  1.0, {
            capture: 'viewport',
            clip: { x: 0, y: 0, width: 1100, height: 1050 },
        });
    });
});
