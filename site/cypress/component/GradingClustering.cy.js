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
        cy.get('[data-testid="toggle-clustering-mode-btn"]').should('contain', 'Go to Clustering Mode');
        cy.get('[data-testid="clustering-algorithm-select"]').should('not.exist');
    });

    it('emits toggle-clustering-mode with correct payload on button click', () => {
        mountWithEmitSpy(GradingClustering, 'toggle-clustering-mode', defaultProps, 'toggleClusteringStub');
        cy.get('[data-testid="toggle-clustering-mode-btn"]').click();
        cy.get('@toggleClusteringStub').should('have.been.calledWith', {
            isClusteringMode: false,
            gradeableId: 'test_gradeable'
        });
    });


    it('renders "Exit Clustering Mode" and dropdown when in clustering mode', () => {
        cy.mount(GradingClustering, {
            props: {
                ...defaultProps,
                isClusteringMode: true,
            },
        });
        cy.get('[data-testid="toggle-clustering-mode-btn"]').should('contain', 'Exit Clustering Mode');
        cy.get('[data-testid="clustering-algorithm-select"]').should('exist');
        cy.get('[data-testid="clustering-algorithm-select"] option').should('have.length', 2); // 'Select an algorithm...' + 'DummySplit'
        cy.get('[data-testid="clustering-algorithm-select"] option').eq(1).should('contain', 'DummySplit');
    });

    it('does not render dropdown if no algorithms are available', () => {
        cy.mount(GradingClustering, {
            props: {
                ...defaultProps,
                isClusteringMode: true,
                algorithms: {},
            },
        });
        cy.get('[data-testid="clustering-algorithm-select"]').should('not.exist');
    });

    it('does not render dropdown if user cannot create clustering', () => {
        cy.mount(GradingClustering, {
            props: {
                ...defaultProps,
                isClusteringMode: true,
                canCreateClustering: false,
            },
        });
        cy.get('[data-testid="clustering-algorithm-select"]').should('not.exist');
    });

    it('initializes dropdown with currentAlgorithm if provided', () => {
        cy.mount(GradingClustering, {
            props: {
                ...defaultProps,
                isClusteringMode: true,
                currentAlgorithm: 'dummy_split',
            },
        });
        cy.get('[data-testid="clustering-algorithm-select"]').should('have.value', 'dummy_split');
    });

    it('sends correct FormData payload when algorithm is selected', () => {
        cy.intercept('POST', '/test/clustering').as('createClustering');
        cy.mount(GradingClustering, {
            props: {
                ...defaultProps,
                isClusteringMode: true,
            },
        });
        
        cy.get('[data-testid="clustering-algorithm-select"]').select('dummy_split');
        
        cy.wait('@createClustering').then((interception) => {
            expect(interception.request.body).to.include('name="csrf_token"');
            expect(interception.request.body).to.include(defaultProps.csrfToken);
            expect(interception.request.body).to.include('name="algorithm"');
            expect(interception.request.body).to.include('dummy_split');
        });
    });

    it('alerts on failure, reverts selection, and emits done status', () => {
        cy.intercept('POST', '/test/clustering', {
            statusCode: 200,
            body: { status: 'fail', message: 'Custom error message' },
        }).as('createClusteringFail');

        const onClusteringStatus = cy.stub().as('clusteringStatusStub');
        const onClusteringError = cy.stub().as('clusteringErrorStub');

        cy.mount(GradingClustering, {
            props: {
                ...defaultProps,
                isClusteringMode: true,
                currentAlgorithm: 'other_algo',
                algorithms: {
                    ...defaultProps.algorithms,
                    other_algo: 'Other Algo'
                },
                onClusteringStatus: onClusteringStatus,
                onClusteringError: onClusteringError,
            }
        });

        cy.get('[data-testid="clustering-algorithm-select"]').should('have.value', 'other_algo');

        cy.get('[data-testid="clustering-algorithm-select"]').select('dummy_split');
        cy.wait('@createClusteringFail');
        
        cy.get('@clusteringErrorStub').should('have.been.calledWith', 'Custom error message');
        cy.get('@clusteringStatusStub').should('have.been.calledWith', 'done');
        cy.get('[data-testid="clustering-algorithm-select"]').should('have.value', 'other_algo');
    });

    it('emits clustering-error on network failure during creation', () => {
        cy.intercept('POST', '/test/clustering', { forceNetworkError: true }).as('createNetworkError');
        mountWithEmitSpy(GradingClustering, 'clusteringError', {
            ...defaultProps,
            isClusteringMode: true,
        }, 'clusteringErrorStub');

        cy.get('[data-testid="clustering-algorithm-select"]').select('dummy_split');
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

        cy.get('[data-testid="clustering-algorithm-select"]').select('dummy_split');
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
            }
            else {
                req.reply({ statusCode: 200, body: { status: 'success', data: { status: 'done' } } });
            }
        }).as('checkClusteringStatus');

        mountWithEmitSpy(GradingClustering, 'clusteringDone', {
            ...defaultProps,
            isClusteringMode: true,
        }, 'clusteringDoneStub');

        cy.get('[data-testid="clustering-algorithm-select"]').select('dummy_split');
        cy.wait('@createClusteringSuccess');
        cy.wait('@checkClusteringStatus'); // First poll (processing)
        cy.wait('@checkClusteringStatus'); // Second poll (done)
        cy.get('@clusteringDoneStub').should('have.been.called');
    });

    it('handles backend failure gracefully during polling', () => {
        cy.intercept('POST', '/test/clustering', {
            statusCode: 200,
            body: { status: 'success' },
        }).as('createClusteringSuccess');

        cy.intercept('GET', '/test/clustering_status', {
            statusCode: 200,
            body: { status: 'fail', message: 'Daemon crashed while calculating' },
        }).as('pollNetworkFail');

        const onClusteringError = cy.stub().as('clusteringErrorStub');

        cy.mount(GradingClustering, {
            props: {
                ...defaultProps,
                isClusteringMode: true,
                currentAlgorithm: 'other_algo',
                algorithms: {
                    ...defaultProps.algorithms,
                    other_algo: 'Other Algo'
                },
                onClusteringError: onClusteringError,
            },
        });

        cy.get('[data-testid="clustering-algorithm-select"]').select('dummy_split');
        cy.wait('@createClusteringSuccess');
        cy.wait('@pollNetworkFail');

        cy.get('@clusteringErrorStub').should('have.been.calledWith', 'Daemon crashed while calculating');
        cy.get('[data-testid="clustering-algorithm-select"]').should('have.value', 'other_algo');
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
        const onClusteringDone = cy.stub().as('clusteringDoneStub');

        cy.mount(GradingClustering, {
            props: {
                ...defaultProps,
                isClusteringMode: true,
                onClusteringStatus: onClusteringStatus,
                onClusteringDone: onClusteringDone,
            },
        });

        cy.get('[data-testid="clustering-algorithm-select"]').select('dummy_split');
        cy.wait('@createClusteringSuccess');
        cy.wait('@checkClusteringStatus');

        cy.get('@clusteringStatusStub').should('have.been.calledWith', 'fetching');
        cy.get('@clusteringStatusStub').should('have.been.calledWith', 'done');
        cy.get('@clusteringDoneStub').should('have.been.called');
    });
});
