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

        // Select the algorithm
        cy.intercept('POST', '**/create_clustering').as('createClustering');
        cy.get('[data-testid="clustering-algorithm-select"]').select('dummy_split');
        cy.wait('@createClustering');

        // page should reload, we verify it by checking that "Cluster A" or "Cluster B" headers exist
        cy.contains('.details-info-header', 'Cluster A', { timeout: 15000 }).should('exist');
        cy.contains('.details-info-header', 'Cluster B').should('exist');

        // test cluster grading logic by grading a student in Cluster A
        cy.get('.details-info-header').contains('Cluster A').parents('tbody.details-info-header').next('tbody.details-content').find('[data-testid="grade-button"]').first().click();

        // check we are now on the grading page with cluster_mode=1
        cy.url().should('include', 'cluster_mode=1');
        cy.get('body').type('{G}');
        cy.get('[data-testid="grading-rubric"]').should('contain', 'Grading Rubric');
        cy.get('[data-testid="component-container"]', { timeout: 10000 }).eq(0).should('be.visible').click(20, 25);

        // wait for component to fully load
        cy.get('[data-testid="save-tools-save"]', { timeout: 10000 }).first().should('be.visible');

        // assign a score
        cy.get('body').type('{0}');
        cy.get('[data-testid="save-tools-save"]').first().click();

        // Verify saving finishes
        cy.get('[data-testid="save-tools-save"]').first().should('contain', 'Save');
        cy.setCookie('view', 'all');
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details?cluster_mode=1']);

        // we should still see clusters
        cy.contains('.details-info-header', 'Cluster A', { timeout: 15000 }).should('exist');
        cy.get('button').contains('Exit Clustering Mode').click();
        cy.url().should('not.include', 'cluster_mode=1');
        cy.get('button').contains('Go to Clustering Mode').should('be.visible');
        cy.get('.details-info-header').contains('Cluster A').should('not.exist');
    });
});
