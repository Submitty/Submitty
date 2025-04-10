describe('Test course should exist', () => {
    it('Check test course exists', () => {
        cy.login();
        cy.visit(['testing', 'gradeable', 'open_homework', 'grading']);
        // Use this file to create a test based around submissions
    });
});
