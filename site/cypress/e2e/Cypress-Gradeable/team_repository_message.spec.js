describe('Test repository error message on manage team page', () => {
    it('Should NOT show repository message for non-VCS team gradeable', () => {
        // Login as a student who is already on a team for open_team_homework
        cy.login('student');
        cy.visit(['sample', 'gradeable', 'open_team_homework', 'team']);
        cy.get('h1').should('contain', 'Manage Team For: Open Team Homework');
        cy.contains('Your Team:').should('be.visible');
        // error message should not appear
        cy.contains('Your repository does not exist').should('not.exist');
        // The repository access instructions should also NOT appear
        cy.contains('To access your Team Repository').should('not.exist');
    });

    it('Should NOT show repository message for non-VCS submission page', () => {
        // Login as a student
        cy.login('student');
        cy.visit(['sample', 'gradeable', 'open_team_homework']);
        // error message should not appear
        cy.contains('Your repository does not exist').should('not.exist');
    });

    it('Should show repository message on VCS submission page', () => {
        // Login as a student
        cy.login('student');
        cy.visit(['sample', 'gradeable', 'open_vcs_homework']);
        cy.get('body').then(($body) => {
            const hasRepoInstructions = $body.text().includes('To access your Repository');
            const hasRepoError = $body.text().includes('Your repository does not exist');
            expect(hasRepoInstructions || hasRepoError).to.be.true;
        });
    });
});
