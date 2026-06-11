describe('Test grabbing the given name for aphacker from user table', () => {
	cy.task("connectDB", "SELECT user_givenname FROM users WHERE user_id = 'aphacker';").then(cy.should('have.text', "Alyssa P"));
});

