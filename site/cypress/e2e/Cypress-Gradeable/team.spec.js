describe('Manage team page repository status', () => {
    it('Should not show repository messaging for non-VCS team assignments', () => {
        cy.login('aphacker');
        cy.visit(['sample', 'gradeable', 'open_team_homework', 'team']);

        cy.get('h1').contains('Manage Team For: Open Team Homework').should('be.visible');
        cy.contains('h3', 'Your Team:').should('be.visible');
        cy.contains('Your repository does not exist.').should('not.exist');
        cy.contains('Create and Manage Authentication Tokens').should('not.exist');
    });
});
