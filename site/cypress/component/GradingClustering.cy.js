import GradingClustering from '../../vue/src/components/GradingClustering.vue';
import { mountWithEmitSpy } from '../support/component_test_utils';

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

        mountWithEmitSpy(GradingClustering, 'clusteringError', {
            ...defaultProps,
            isClusteringMode: true,
        }, 'clusteringErrorStub');

        cy.get('select').select('dummy_split');
        cy.wait('@createClusteringFail');
        cy.get('@clusteringErrorStub').should('have.been.calledWith', 'Custom error message');
    });

    it('emits clustering-error on network failure during creation', () => {
        cy.intercept('POST', '/test/clustering', { forceNetworkError: true }).as('createNetworkError');
        
        mountWithEmitSpy(GradingClustering, 'clusteringError', {
            ...defaultProps,
            isClusteringMode: true,
        }, 'clusteringErrorStub');

        cy.get('select').select('dummy_split');
        cy.wait('@createNetworkError');
        cy.get('@clusteringErrorStub').should('have.been.calledWith', 'Failed to connect to the server.');
    });

    it('emits clustering-error on network failure during status poll', () => {
        cy.intercept('POST', '/test/clustering', {
            statusCode: 200,
            body: { status: 'success' },
        }).as('createClusteringSuccess');

        cy.intercept('GET', '/test/clustering_status', { forceNetworkError: true }).as('pollNetworkError');

        mountWithEmitSpy(GradingClustering, 'clusteringError', {
            ...defaultProps,
            isClusteringMode: true,
        }, 'clusteringErrorStub');

        cy.get('select').select('dummy_split');
        cy.wait('@createClusteringSuccess');
        cy.wait('@pollNetworkError');
        cy.get('@clusteringErrorStub').should('have.been.calledWith', 'Error checking clustering status.');
    });

    it('polls multiple times before finishing', () => {
        cy.intercept('POST', '/test/clustering', {
            statusCode: 200,
            body: { status: 'success' },
        }).as('createClusteringSuccess');

        let pollCount = 0;
        cy.intercept('GET', '/test/clustering_status', (req) => {
            pollCount++;
            if (pollCount === 1) {
                req.reply({ statusCode: 200, body: { status: 'success', data: { status: 'processing' } } });
            } else {
                req.reply({ statusCode: 200, body: { status: 'success', data: { status: 'done' } } });
            }
        }).as('checkClusteringStatus');

        mountWithEmitSpy(GradingClustering, 'clusteringDone', {
            ...defaultProps,
            isClusteringMode: true,
        }, 'clusteringDoneStub');

        cy.get('select').select('dummy_split');
        cy.wait('@createClusteringSuccess');
        cy.wait('@checkClusteringStatus'); // First poll (processing)
        cy.wait('@checkClusteringStatus'); // Second poll (done)
        
        cy.get('@clusteringDoneStub').should('have.been.called');
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

        // Note: For multiple stubs on the same component mount, we stick to standard cy.mount
        // instead of mountWithEmitSpy, because mountWithEmitSpy only stubs a single event.
        const onClusteringStatus = cy.stub().as('clusteringStatusStub');
        const onClusteringDone = cy.stub().as('clusteringDoneStub');

        cy.mount(GradingClustering, {
            props: {
                ...defaultProps,
                isClusteringMode: true,
                onClusteringStatus: onClusteringStatus,
                onClusteringDone: onClusteringDone,
            },
        });

        cy.get('select').select('dummy_split');
        cy.wait('@createClusteringSuccess');
        cy.wait('@checkClusteringStatus');

        cy.get('@clusteringStatusStub').should('have.been.calledWith', 'fetching');
        cy.get('@clusteringStatusStub').should('have.been.calledWith', 'done');
        cy.get('@clusteringDoneStub').should('have.been.called');
    });
});
