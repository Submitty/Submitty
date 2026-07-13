import GradingClustering from '../../vue/src/components/GradingClustering.vue';

describe('GradingClustering', () => {
    const defaultProps = {
        isClusteringMode: false,
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

    it('renders "Go to Clustering Mode" button when not in clustering mode', () => {
        cy.mount(GradingClustering, { props: defaultProps });
        cy.get('button').should('contain', 'Go to Clustering Mode');
        cy.get('select').should('not.exist');
    });

    it('renders "Exit Clustering Mode" and dropdown when in clustering mode', () => {
        cy.mount(GradingClustering, {
            props: {
                ...defaultProps,
                isClusteringMode: true,
            },
        });
        cy.get('button').should('contain', 'Exit Clustering Mode');
        cy.get('select').should('exist');
        cy.get('select option').should('have.length', 2); // 'Select an algorithm...' + 'DummySplit'
        cy.get('select option').eq(1).should('contain', 'DummySplit');
    });

    it('does not render dropdown if no algorithms are available', () => {
        cy.mount(GradingClustering, {
            props: {
                ...defaultProps,
                isClusteringMode: true,
                algorithms: {},
            },
        });
        cy.get('select').should('not.exist');
    });

    it('does not render dropdown if user cannot create clustering', () => {
        cy.mount(GradingClustering, {
            props: {
                ...defaultProps,
                isClusteringMode: true,
                canCreateClustering: false,
            },
        });
        cy.get('select').should('not.exist');
    });

    it('alerts on failure when algorithm is changed', () => {
        cy.intercept('POST', '/test/clustering', {
            statusCode: 200,
            body: { status: 'fail', message: 'Custom error message' },
        }).as('createClusteringFail');

        cy.mount(GradingClustering, {
            props: {
                ...defaultProps,
                isClusteringMode: true,
            },
        });

        const alertStub = cy.stub().as('alertStub');
        cy.on('window:alert', alertStub);

        cy.get('select').select('dummy_split');
        cy.wait('@createClusteringFail');
        cy.get('@alertStub').should('have.been.calledWith', 'Custom error message');
    });

    it('emits clustering-status events during successful algorithm change', () => {
        cy.intercept('POST', '/test/clustering', {
            statusCode: 200,
            body: { status: 'success' },
        }).as('createClusteringSuccess');

        cy.intercept('GET', '/test/clustering_status', {
            statusCode: 200,
            body: { status: 'success', data: { status: 'done' } },
        }).as('checkClusteringStatus');

        const onClusteringStatus = cy.stub().as('clusteringStatusStub');

        cy.mount(GradingClustering, {
            props: {
                ...defaultProps,
                isClusteringMode: true,
                onClusteringStatus: onClusteringStatus,
            },
        });

        cy.get('select').select('dummy_split');
        cy.wait('@createClusteringSuccess');
        cy.wait('@checkClusteringStatus');

        cy.get('@clusteringStatusStub').should('have.been.calledWith', 'fetching');
        cy.get('@clusteringStatusStub').should('have.been.calledWith', 'done');
    });
});
