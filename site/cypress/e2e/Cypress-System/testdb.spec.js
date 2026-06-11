describe('Test functionality of accessing db from cypress', () => {
    it('Test grabbing the given name for aphacker from user table', () => {
        cy.task("connectDB", { database: "submitty_s26_sample", query: "SELECT user_givenname FROM users WHERE user_id = 'aphacker';" })
          .then((rows) => {
              expect(rows).to.have.length(1);
              expect(rows[0].user_givenname).to.equal('Alyssa P');
          });
    });
});