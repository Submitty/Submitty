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

        // Verify clustering state
        cy.get('button').contains('Exit Clustering Mode').should('be.visible');
        cy.get('[data-testid="clustering-algorithm-select"]').should('be.visible');
        cy.get('[data-testid="clustering-algorithm-select"] option').contains('DummySplit').should('be.visible');

        // Select the algorithm
        cy.intercept('POST', '**/create_clustering').as('createClustering');
        cy.get('[data-testid="clustering-algorithm-select"]').select('dummy_split');
        cy.wait('@createClustering');

        // Page should reload, we verify it by checking that "Cluster A" or "Cluster B" headers exist
        cy.get('.details-info-header', { timeout: 10000 }).contains('Cluster A').should('exist');
        cy.get('.details-info-header').contains('Cluster B').should('exist');

        // test cluster grading logic by grading a student in Cluster A
        cy.get('.details-info-header').contains('Cluster A').parents('tbody.details-info-header').next('tbody.details-content').find('[data-testid="grade-button"]').first().click();
        
        // check we are now on the grading page with cluster_mode=1
        cy.url().should('include', 'cluster_mode=1');
        cy.get('[data-testid="grading-rubric"]').should('contain', 'Grading Rubric');
        cy.get('[data-testid="component-container"]').eq(0).should('be.visible').click(20, 25);
        
        // assign a score
        cy.get('body').type('{0}');
        // Save the grade
        cy.get('[data-testid="save-tools-save"]').first().click();
        
        // Verify saving finishes
        cy.get('[data-testid="save-tools-save"]').first().should('contain', 'Save');
        cy.go('back');
        
        // we should still see clusters
        cy.get('.details-info-header').contains('Cluster A').should('exist');
        cy.get('button').contains('Exit Clustering Mode').click();

        // Verify exit
        cy.url().should('not.include', 'cluster_mode=1');
        cy.get('button').contains('Go to Clustering Mode').should('be.visible');
        cy.get('.details-info-header').contains('Cluster A').should('not.exist');
    });
});
