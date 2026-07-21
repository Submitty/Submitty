describe('Test repository error message on manage team page', () => {
    it('Should NOT show repository message for non-VCS team gradeable', () => {
        // Login as a student who is already on a team for open_team_homework
        cy.login('student');
        cy.visit(['sample', 'gradeable', 'open_team_homework', 'team']);
        cy.get('h1').should('contain', 'Manage Team For: Open Team Homework');
        cy.get('[data-testid="your-team-header"]').should('be.visible');
        // error message should not appear
        cy.get('[data-testid="vcs-repository-error"]').should('not.exist');
        // The repository access instructions should also NOT appear
        cy.get('[data-testid="vcs-repository-info"]').should('not.exist');
    });

    it('Should NOT show repository message for non-VCS submission page', () => {
        // Login as a student
        cy.login('student');
        cy.visit(['sample', 'gradeable', 'open_team_homework']);
        // error message should not appear
        cy.get('[data-testid="vcs-repository-error"]').should('not.exist');
    });

    it('Should show repository message on VCS submission page', () => {
        // Login as a student
        cy.login('student');
        cy.visit(['sample', 'gradeable', 'open_vcs_homework']);
        cy.get('body').then(($body) => {
            const hasRepoInstructions = $body.find('[data-testid="vcs-repository-info"]').length > 0;
            const hasRepoError = $body.find('[data-testid="vcs-repository-error"]').length > 0;
            expect(hasRepoInstructions || hasRepoError).to.be.true;
        });
    });
});
