import { buildUrl } from '../../support/utils.js';

describe('Grading Clustering Mode', () => {
    it('allows opening create modal and toggling cluster view', () => {
        cy.login();
        // Enable clustering at course level
        cy.window().then((window) => {
            cy.request({
                method: 'POST',
                url: buildUrl(['sample', 'config'], true),
                body: {
                    csrf_token: window.csrfToken,
                    name: 'submission_clustering_enabled',
                    entry: 'true'
                },
                form: true
            });
        });
        cy.setCookie('view', 'all');
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);

        // Verify initial state
        cy.get('button').contains('Create Clusters').should('be.visible');
        cy.get('[data-testid="group-by-clusters-checkbox"]').should('not.be.checked');

        // Toggle the group by clusters view
        cy.get('[data-testid="group-by-clusters-checkbox"]').check();
        cy.getCookie('group_by_clusters').should('have.property', 'value', 'true');
        cy.get('[data-testid="group-by-clusters-checkbox"]').should('be.checked');

        // Modal popup opens on click
        cy.get('button').contains('Create Clusters').click();
        cy.get('.popup-window').should('be.visible');
        cy.get('.form-title').contains('Create Clusters');
        cy.get('[data-testid="clustering-algorithm-select"]').should('be.visible');
        cy.get('[data-testid="clustering-algorithm-select"] option').contains('DummySplit').should('be.visible');

        // Close modal
        cy.get('.form-title .close-button:visible').click();
        cy.get('.popup-window:visible').should('not.exist');

        // Uncheck the group by clusters view
        cy.get('[data-testid="group-by-clusters-checkbox"]').uncheck();
        cy.getCookie('group_by_clusters').should('have.property', 'value', 'false');
    });
    it('hides clustering options when clustering is disabled', () => {
        cy.login();

        // Enable clustering at course level
        cy.window().then((window) => {
            cy.request({
                method: 'POST',
                url: buildUrl(['sample', 'config'], true),
                body: {
                    csrf_token: window.csrfToken,
                    name: 'submission_clustering_enabled',
                    entry: 'true'
                },
                form: true
            });
        });

        // Verify clustering features are visible
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);
        cy.get('button').contains('Create Clusters').should('be.visible');

        // Disable clustering at course level
        cy.window().then((window) => {
            cy.request({
                method: 'POST',
                url: buildUrl(['sample', 'config'], true),
                body: {
                    csrf_token: window.csrfToken,
                    name: 'submission_clustering_enabled',
                    entry: 'false'
                },
                form: true
            });
        });

        // Check if buttons are hidden on the grading page
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);
        cy.get('button').contains('Create Clusters').should('not.exist');
        cy.get('[data-testid="group-by-clusters-checkbox"]').should('not.exist');
    });
});
