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
        cy.get('[data-testid="create-clusters-btn"]').should('be.visible');
        cy.get('[data-testid="group-by-clusters-checkbox"]').should('not.exist');

        // Modal popup opens on click
        cy.get('[data-testid="create-clusters-btn"]').click();
        cy.get('[data-testid="create-clusters-modal"]').should('be.visible');
        cy.get('[data-testid="create-clusters-form-title"]').contains('Create Clusters');
        cy.get('[data-testid="clustering-algorithm-select"]').should('be.visible');
        cy.get('[data-testid="clustering-algorithm-select"] option').contains('DummySplit').should('be.visible');

        // Close modal
        cy.get('[data-testid="close-button"]:visible').click();
        cy.get('[data-testid="create-clusters-modal"]:visible').should('not.exist');
    });
    it('hides clustering options when clustering is disabled', () => {
        cy.login();

        // Enable clustering at course level
        cy.visit(['sample', 'config']);
        cy.get('[data-testid="submission-clustering-enabled"]').check();

        // Verify clustering features are visible
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);
        cy.get('[data-testid="create-clusters-btn"]').should('be.visible');

        // Disable clustering at course level
        cy.visit(['sample', 'config']);
        cy.get('[data-testid="submission-clustering-enabled"]').uncheck();

        // Check if buttons are hidden on the grading page
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);
        cy.get('[data-testid="create-clusters-btn"]').should('not.exist');
        cy.get('[data-testid="group-by-clusters-checkbox"]').should('not.exist');
    });
    it('hides clustering toggle icon on rubric panel when no clusters exist', () => {
        cy.login();
        cy.visit(['sample', 'config']);
        cy.get('[data-testid="submission-clustering-enabled"]').check();
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=Oith0AebfRyC8xK&sort=id&direction=ASC']);

        // The clustering toggle icon should not exist since clusters are not formed
        cy.get('[data-testid="toggle-cluster-mode"]').should('not.exist');
    });
});
