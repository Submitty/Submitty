import { buildUrl } from '../../support/utils.js';

describe('Cluster Grading', () => {
    it('allows opening create modal and toggling cluster view', () => {
        cy.login();
        // Enable clustering at course level
        cy.visit(['sample', 'config']);
        cy.get('[data-testid="submission-clustering-enabled"]').check();
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);
        cy.get('[data-testid="view-sections"]').click();

        // Verify initial state
        cy.get('button').contains('Create Clusters').should('be.visible');
        cy.get('[data-testid="group-by-clusters-checkbox"]').should('not.exist');

        // Modal popup opens on click
        cy.get('button').contains('Create Clusters').click();
        cy.get('.popup-window').should('be.visible');
        cy.get('.form-title').contains('Create Clusters');
        cy.get('[data-testid="clustering-algorithm-select"]').should('be.visible');
        cy.get('[data-testid="clustering-algorithm-select"] option').contains('DummySplit').should('be.visible');

        // Close modal
        cy.get('.form-title .close-button:visible').click();
        cy.get('.popup-window:visible').should('not.exist');
    });
    it('hides clustering options when clustering is disabled', () => {
        cy.login();

        // Enable clustering at course level
        cy.visit(['sample', 'config']);
        cy.get('[data-testid="submission-clustering-enabled"]').check();

        // Verify clustering features are visible
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);
        cy.get('button').contains('Create Clusters').should('be.visible');

        // Disable clustering at course level
        cy.visit(['sample', 'config']);
        cy.get('[data-testid="submission-clustering-enabled"]').uncheck();

        // Check if buttons are hidden on the grading page
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);
        cy.get('button').contains('Create Clusters').should('not.exist');
        cy.get('[data-testid="group-by-clusters-checkbox"]').should('not.exist');
    });
    it('hides clustering toggle icon on rubric panel when no clusters exist', () => {
        cy.login();
        cy.visit(['sample', 'config']);
        cy.get('[data-testid="submission-clustering-enabled"]').check();
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=Oith0AebfRyC8xK&sort=id&direction=ASC']);

        // The clustering toggle icon should not exist since clusters are not formed
        cy.get('#toggle-cluster-mode').should('not.exist');
    });
});
