describe('Visual testing', () => {
    it('autograding markdown testing', () => {
        cy.viewport(1150, 900);
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=jKjodWaRdEV9pBb&sort=id&direction=ASC']);
        cy.get('#nav-sidebar-collapse-sidebar').click();
        cy.get('[data-testid="details-tc-expand-all"]').click();
        cy.get('[data-testid="test-box-block"]').scrollIntoView().compareSnapshot('autograding_code', 1.0, {
            capture: 'viewport',
            clip: { x: 0, y: 0, width: 1150, height: 900 },
        });
    });
});
