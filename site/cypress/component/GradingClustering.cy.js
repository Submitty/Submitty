import GradingClustering from '../../vue/src/components/GradingClustering.vue';
import { mountWithEmitSpy } from '../support/component_test_utils';

describe('GradingClustering', () => {
    const defaultProps = {
        algorithms: {
            dummy_split: 'DummySplit',
        },
        currentAlgorithm: '',
        createClusteringUrl: '/test/clustering',
        checkClusteringStatusUrl: '/test/clustering_status',
        csrfToken: 'test-token',
        canCreateClustering: true,
        gradeableId: 'test_gradeable',
    };

    it('renders "Create Clusters" button if user can create clustering', () => {
        cy.mount(GradingClustering, { props: defaultProps });
        cy.get('[data-testid="create-clusters-btn"]').should('contain', 'Create Clusters');
        cy.get('.popup-form').should('not.exist');
    });

    it('does not render "Create Clusters" button if user cannot create clustering', () => {
        cy.mount(GradingClustering, {
            props: {
                ...defaultProps,
                canCreateClustering: false,
            },
        });
        cy.get('[data-testid="create-clusters-btn"]').should('not.exist');
    });

    it('opens modal on button click', () => {
        cy.mount(GradingClustering, { props: defaultProps });
        cy.get('[data-testid="create-clusters-btn"]').click();
        cy.get('.popup-form').should('be.visible');
        cy.get('.form-title').should('contain', 'Create Clusters');
        cy.get('[data-testid="clustering-algorithm-select"]').should('exist');
    });

    it('closes modal on cancel click', () => {
        cy.mount(GradingClustering, { props: defaultProps });
        cy.get('[data-testid="create-clusters-btn"]').click();
        cy.get('.popup-form').should('be.visible');
        cy.get('.close-button').first().click();
        cy.get('.popup-form').should('not.exist');
    });

    it('does not render dropdown if no algorithms are available', () => {
        cy.mount(GradingClustering, {
            props: {
                ...defaultProps,
                algorithms: {},
            },
        });
        cy.get('[data-testid="create-clusters-btn"]').click();
        cy.get('.popup-form').should('be.visible');
        cy.get('[data-testid="clustering-algorithm-select"]').should('not.exist');
        cy.get('.form-body').should('contain', 'No clustering algorithms available.');
    });

    it('Submit button is disabled until algorithm is selected', () => {
        cy.mount(GradingClustering, { props: defaultProps });
        cy.get('[data-testid="create-clusters-btn"]').click();
        cy.get('.popup-form').should('be.visible');
        
        // Button should be disabled initially (empty selection)
        cy.get('button').contains('Submit').should('be.disabled');
        
        // Select an algorithm
        cy.get('[data-testid="clustering-algorithm-select"]').select('dummy_split');
        cy.get('button').contains('Submit').should('not.be.disabled');
    });

    it('sends correct FormData payload when Submit is clicked', () => {
        cy.intercept('POST', '/test/clustering', { statusCode: 200, body: { status: 'success' } }).as('createClustering');
        cy.intercept('GET', '/test/clustering_status', { statusCode: 200, body: { status: 'success', data: { status: 'done' } } }).as('checkClusteringStatus');
        
        cy.mount(GradingClustering, { props: defaultProps });

        cy.get('[data-testid="create-clusters-btn"]').click();
        cy.get('[data-testid="clustering-algorithm-select"]').select('dummy_split');
        cy.get('button').contains('Submit').click();

        cy.wait('@createClustering').then((interception) => {
            expect(interception.request.body).to.include('name="csrf_token"');
            expect(interception.request.body).to.include(defaultProps.csrfToken);
            expect(interception.request.body).to.include('name="algorithm"');
            expect(interception.request.body).to.include('dummy_split');
        });
    });
});
