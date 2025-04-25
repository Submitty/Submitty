describe('Tests leaderboard access', () => {
    before(() => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'leaderboard', 'update']);
        cy.get('#page_5_nav').click();
        cy.get('[data-testid="submission-open-date"]').type('2100-01-15 23:59:59');
    });

    it('Should check if leaderboard is accessible to users', () => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'leaderboard']);
        cy.get('[data-testid="invalid-gradeable"]').should('not.exist');
        cy.visit(['sample', 'gradeable', 'leaderboard']);
        cy.get('[data-testid="invalid-gradeable"]').should('not.exist');
        cy.logout();

        cy.login('ta');
        cy.visit(['sample', 'gradeable', 'leaderboard']);
        cy.get('[data-testid="invalid-gradeable"]').should('not.exist');
        cy.visit(['sample', 'gradeable', 'leaderboard']);
        cy.get('[data-testid="invalid-gradeable"]').should('not.exist');
        cy.logout();

        cy.login('grader');
        cy.visit(['sample', 'gradeable', 'leaderboard']);
        cy.get('[data-testid="invalid-gradeable"]').should('not.exist');

        cy.visit(['sample', 'gradeable', 'leaderboard']);
        cy.get('[data-testid="invalid-gradeable"]').should('not.exist');

        cy.logout();
        cy.login('student');
        cy.visit(['sample', 'gradeable', 'leaderboard']);
        cy.get('[data-testid="invalid-gradeable"]').should('exist');

        cy.visit(['sample', 'gradeable', 'leaderboard']);
        cy.get('[data-testid="invalid-gradeable"]').should('exist');
    });
});
