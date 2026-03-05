describe('Legal name privacy test', () => {

  it('should not display legal names in UI when preferred names exist', () => {

    const legalFirst = 'LEGAL_FIRST_8435_TOKEN';
    const legalLast = 'LEGAL_LAST_8435_TOKEN';

    cy.login();

    cy.visit('/user_profile');

    cy.document().then((doc) => {
      const html = doc.documentElement.innerHTML;

      // Ensure legal names never appear in UI
      expect(html).to.not.contain(legalFirst);
      expect(html).to.not.contain(legalLast);
    });

    // Also check visible name fields
    cy.get('body').should('not.contain', legalFirst);
    cy.get('body').should('not.contain', legalLast);

  });

});