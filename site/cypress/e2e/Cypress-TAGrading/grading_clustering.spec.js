describe('Grading Clustering Mode', () => {
    it('allows toggling clustering mode and generating clusters', () => {
        cy.login();
        cy.setCookie('view', 'all');
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);

        // Verify initial state
        cy.get('button').contains('Go to Clustering Mode').should('be.visible');
        cy.get('[data-testid="clustering-algorithm-select"]').should('not.exist');

        cy.get('button').contains('Go to Clustering Mode').click();

        // Warning popup should appear
        cy.get('#clustering-warning-popup').should('be.visible');
        cy.get('#clustering-warning-popup').contains('In clustering mode, any student you grade within a cluster');

        cy.get('[data-testid="clustering-agree-popup-btn"]').click();

        // The URL should now have ?cluster_mode=1 and page reloads
        cy.url().should('include', 'cluster_mode=1');
        cy.get('button').contains('Exit Clustering Mode').should('be.visible');
        cy.get('[data-testid="clustering-algorithm-select"]').should('be.visible');
        cy.get('[data-testid="clustering-algorithm-select"] option').contains('DummySplit').should('be.visible');

    });
});
