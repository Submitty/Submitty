describe('Grading Clustering Mode', () => {
    it('allows toggling clustering mode and generating clusters', () => {
        // Login as instructor (default)
        cy.login();
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);

        // Verify initial state
        cy.get('button').contains('Go to Clustering Mode').should('be.visible');
        cy.get('select').contains('Dummy Split').should('not.exist');

        // Click to enter clustering mode
        cy.get('button').contains('Go to Clustering Mode').click();

        // Warning popup should appear
        cy.get('#clustering-warning-popup').should('be.visible');
        cy.get('#clustering-warning-popup').contains('Are you sure you want to enter Clustering Mode?');
        
        // Agree to the warning
        cy.get('.popup-window .btn-primary').contains('Yes, enter clustering mode').click();

        // The URL should now have ?cluster_mode=1 and page reloads
        cy.url().should('include', 'cluster_mode=1');

        // Verify clustering state
        cy.get('button').contains('Exit Clustering Mode').should('be.visible');
        cy.get('select').should('be.visible');
        cy.get('select option').contains('Dummy Split').should('be.visible');

        // Select the algorithm
        cy.get('select').select('DummySplit');

        // Page should reload, we verify it by checking that "Cluster A" or "Cluster B" headers exist
        cy.get('.section-heading').contains('Cluster A').should('exist');
        cy.get('.section-heading').contains('Cluster B').should('exist');

        // Exit clustering mode
        cy.get('button').contains('Exit Clustering Mode').click();

        // Verify exit
        cy.url().should('not.include', 'cluster_mode=1');
        cy.get('button').contains('Go to Clustering Mode').should('be.visible');
        cy.get('.section-heading').contains('Cluster A').should('not.exist');
    });
});
