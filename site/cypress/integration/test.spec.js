describe('Test cases revolving around course material uploading and access control', () => {
    it('should test', () => {
        cy.visit('/');
        cy.login();
        cy.get('#f21_sample').click();
        cy.get('#grades_released_homework > :nth-child(3) > .btn').click();
        cy.get('.flex-row > .btn').click();

        cy.get('.content').should(() => {
            expect(true).to.equal(false);
        });
    });
});
