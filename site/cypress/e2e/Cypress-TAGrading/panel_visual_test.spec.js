describe('Toggle the two panel mode', () => {
    it('Visual Testing', () => {
        cy.login();
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=hG1b13ljpDjKu32&sort=id&direction=ASC']);
        cy.get('[data-testid="sidebar"]').contains('Collapse Sidebar').click();
        cy.get('[data-testid="two-panel-mode-btn"]').click();
        cy.get('#layout-option-2').contains('Apply').eq(0).click();
        cy.compareSnapshot('panel-mode-2', 0.02, {
            capture: 'viewport',
        });
        cy.get('[data-testid="two-panel-mode-btn"]').click();
        cy.get('#layout-option-2').contains('Apply').eq(1).click();
        cy.compareSnapshot('panel-mode-2', 0.02, {
            capture: 'viewport',
        });
        cy.get('[data-testid="two-panel-mode-btn"]').click();
        cy.get('#layout-option-3').contains('Apply').eq(0).click();
        cy.compareSnapshot('panel-mode-2', 0.02, {
            capture: 'viewport',
        });
        cy.get('[data-testid="two-panel-mode-btn"]').click();
        cy.get('#layout-option-3').contains('Apply').eq(0).click();
        cy.compareSnapshot('panel-mode-2', 0.02, {
            capture: 'viewport',
        });
        cy.get('[data-testid="two-panel-mode-btn"]').click();
        cy.get('#layout-option-3').contains('Apply').eq(2).click();
        cy.compareSnapshot('panel-mode-2', 0.02, {
            capture: 'viewport',
        });
        cy.get('[data-testid="two-panel-mode-btn"]').click();
        cy.get('#layout-option-4').contains('Apply').eq(0).click();
        cy.compareSnapshot('panel-mode-2', 0.02, {
            capture: 'viewport',
        });
        cy.get('[data-testid="two-panel-mode-btn"]').click();
        cy.get('#layout-option-1').contains('Apply').click();
        cy.compareSnapshot('panel-mode-2', 0.02, {
            capture: 'viewport',
        });
    });
});
