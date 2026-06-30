import GradingClustering from '../../vue/src/components/GradingClustering.vue';

describe('GradingClustering', () => {
    const defaultProps = {
        isClusteringMode: false,
        algorithms: {
            'DummySplit': 'Dummy Split',
        },
        currentAlgorithm: '',
        createClusteringUrl: '/test/clustering',
        csrfToken: 'test-token',
        canCreateClustering: true,
        gradeableId: 'test_gradeable'
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
                isClusteringMode: true
            }
        });
        cy.get('button').should('contain', 'Exit Clustering Mode');
        cy.get('select').should('exist');
        cy.get('select option').should('have.length', 2); // 'Select an algorithm...' + 'Dummy Split'
        cy.get('select option').eq(1).should('contain', 'Dummy Split');
    });

    it('does not render dropdown if no algorithms are available', () => {
        cy.mount(GradingClustering, {
            props: {
                ...defaultProps,
                isClusteringMode: true,
                algorithms: {}
            }
        });
        cy.get('select').should('not.exist');
    });

    it('does not render dropdown if user cannot create clustering', () => {
        cy.mount(GradingClustering, {
            props: {
                ...defaultProps,
                isClusteringMode: true,
                canCreateClustering: false
            }
        });
        cy.get('select').should('not.exist');
    });

    it('clicking "Exit Clustering Mode" manipulates URL', () => {
        cy.mount(GradingClustering, {
            props: {
                ...defaultProps,
                isClusteringMode: true
            }
        });

        cy.get('button').click();
    });

    it('calls warning message when entering clustering mode if not accepted', () => {
        cy.mount(GradingClustering, { props: defaultProps });

        cy.window().then((win) => {
            win.sessionStorage.setItem('clusteringWarningAccepted_test_gradeable', 'false');
            win.showClusteringWarningMessage = cy.stub().as('warningMsg');
        });

        cy.get('button').click();
        cy.get('@warningMsg').should('have.been.calledOnce');
    });

    it('skips warning message and manipulates URL if already accepted', () => {
        cy.mount(GradingClustering, { props: defaultProps });

        cy.window().then((win) => {
            win.sessionStorage.setItem('clusteringWarningAccepted_test_gradeable', 'true');
            win.showClusteringWarningMessage = cy.stub().as('warningMsg');
        });

        cy.get('button').click();
        cy.get('@warningMsg').should('not.have.been.called');
    });

    it('sends POST request when algorithm is changed', () => {
        cy.intercept('POST', '/test/clustering', {
            statusCode: 200,
            body: { status: 'success' }
        }).as('createClustering');

        cy.mount(GradingClustering, {
            props: {
                ...defaultProps,
                isClusteringMode: true
            }
        });

        cy.get('select').select('DummySplit');
        cy.wait('@createClustering').then((interception) => {
            expect(interception.request.body).to.include('DummySplit');
            expect(interception.request.body).to.include('test-token');
        });
    });

    it('alerts on failure when algorithm is changed', () => {
        cy.intercept('POST', '/test/clustering', {
            statusCode: 200,
            body: { status: 'fail', message: 'Custom error message' }
        }).as('createClusteringFail');

        cy.mount(GradingClustering, {
            props: {
                ...defaultProps,
                isClusteringMode: true
            }
        });

        const alertStub = cy.stub();
        cy.on('window:alert', alertStub);

        cy.get('select').select('DummySplit');
        cy.wait('@createClusteringFail').then(() => {
            expect(alertStub).to.have.been.calledWith('Custom error message');
        });
    });
});
