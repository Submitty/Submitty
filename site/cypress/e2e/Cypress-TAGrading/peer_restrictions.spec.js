describe('Test that grading restrictions on peer graders work', () => {
    it('Should save and apply peer grading panel view options', () => {
        const panelOptions = [
            {
                setting: '[data-testid="peer-autograding-panel-option"]',
                gradingButton: '[data-testid="autograding-results-btn"]',
            },
            {
                setting: '[data-testid="peer-rubric-panel-option"]',
                gradingButton: '[data-testid="grading-rubric-btn"]',
            },
            {
                setting: '[data-testid="peer-files-panel-option"]',
                gradingButton: '[data-testid="submission-browser-btn"]',
            },
            {
                setting: '[data-testid="peer-solutions-panel-option"]',
                gradingButton: '[data-testid="solution-ta-notes-btn"]',
            },
        ];
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'grading_pdf_peer_homework', 'update?nav_tab=3']);
        panelOptions.forEach(({ setting }) => {
            cy.get(setting, { timeout: 10000 }).should('be.checked').uncheck();
        });
        cy.get('[data-testid="save-status"]', { timeout: 10000 }).should('contain.text', 'All Changes Saved');
        cy.reload();
        panelOptions.forEach(({ setting }) => {
            cy.get(setting, { timeout: 10000 }).should('not.be.checked');
        });
        cy.visit('/');
        cy.logout();
        cy.login('bitdiddle');
        cy.visit([
            'sample',
            'gradeable',
            'grading_pdf_peer_homework',
            'grading',
            'details',
        ]);
        cy.get('[data-testid="agree-popup-btn"]').click();
        cy.get('[data-testid="grade-button"]', { timeout: 10000 }).filter(':visible').first().click();
        panelOptions.forEach(({ gradingButton }) => {
            cy.get(gradingButton, { timeout: 10000 }).should('not.exist');
        });
        cy.visit('/');
        cy.logout();
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'grading_pdf_peer_homework', 'update?nav_tab=3']);
        panelOptions.forEach(({ setting }) => {
            cy.get(setting, { timeout: 10000 }).should('not.be.checked').check();
        });
        cy.get('[data-testid="save-status"]', { timeout: 10000 }).should('contain.text', 'All Changes Saved');
        cy.reload();
        panelOptions.forEach(({ setting }) => {
            cy.get(setting, { timeout: 10000 }).should('be.checked');
        });
    });
});
