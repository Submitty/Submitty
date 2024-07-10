describe('TA grading hotkey testing', () => {
    it('toggle keyboard shortcut', () => {
        cy.login();
        cy.visit(['testing', 'gradeable', 'open_homework', 'grading']);
    });
});
