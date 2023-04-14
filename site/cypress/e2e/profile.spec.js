describe('Test cases revolving around user profile page', () => {
  it('Should verify the basic info column\'s visibility', () => {
    cy.visit(['sample', 'polls']);
    cy.login();
    cy.get('#username-row').should('be.visible');
    cy.get('#givenname-row').should('be.visible');
    cy.get('#familyname-row').should('be.visible');
    cy.get('#pronouns-row').should('be.visible');
    cy.get('#email-row').should('be.visible');
    cy.get('#secondary-email-row').should('be.visible');
    cy.get('#secondary-email-notify-row').should('be.visible');
  })

  //verify each input form
  it('Should contain forms to modify First Name, \
  Last Name, Pronouns, Primary Email, Secondary Email, \
  and Send Email to Seconday Address', () => {
    // verify that every pop-up form's display originally is none
    cy.get('#edit-username-form').should('not.be.visible');
    cy.get('#edit-pronouns-form').should('not.be.visible');
    cy.get('#edit-secondary-email-form').should('not.be.visible');
    cy.get('#edit-secondary-email-form').should('not.be.visible');
    // verify that every form can be intrigued   
    cy.get('.fa-pencil-alt').should(($icon) => {
      expect($icon).to.have.length(5);
      $icon.each((index, $each) => {
        // special case for first name and last name
        if (index == 1){
          index--;
        }
        cy.wrap($each).click();
        for (let i = 0; i < index; i++) {
          cy.get('.popup-form').eq(i).should('not.be.visible')
        }
        cy.get('.popup-form').eq(index).should('be.visible')
        for (let i = index+1; i < 5; i++) {
          cy.get('.popup-form').eq(i).should('not.be.visible')
        }
      })
    })
  })
})