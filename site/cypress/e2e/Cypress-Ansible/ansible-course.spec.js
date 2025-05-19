describe('Testing website when created by ansible scripts', () => {
    it('Should be able to login and see the course', () => {
        cy.login('instructor');
        cy.visit('term', 'course');
    });
});
