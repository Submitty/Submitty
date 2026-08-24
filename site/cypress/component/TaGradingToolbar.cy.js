import TaGradingToolbar from '../../vue/src/components/ta_grading/TaGradingToolbar.vue';
import { mountWithEmitSpy } from '../support/component_test_utils';
import * as panelsModule from '../../ts/ta-grading-panels';

const defaultProps = {
    homeUrl: '/courses/s25/grade/g1',
    prevStudentUrl: '/courses/s25/grade/g1?student_id=prev123',
    nextStudentUrl: '/courses/s25/grade/g1?student_id=next456',
    progress: 42,
};

describe('TaGradingToolbar', () => {
    beforeEach(() => {
        localStorage.clear();
        panelsModule.taLayoutDet.isFullScreenMode = false;
        cy.document().then((doc) => {
            if (!doc.querySelector('main#main')) {
                const main = doc.createElement('main');
                main.id = 'main';
                doc.body.appendChild(main);
            }
            doc.querySelector('main#main').classList.remove('full-screen-mode');
        });
    });

    describe('rendering', () => {
        it('renders all navigation buttons with correct titles and hrefs', () => {
            cy.mount(TaGradingToolbar, { props: defaultProps });

            cy.get('[data-testid="main-page"]')
                .should('have.attr', 'title', 'Go to the main page')
                .and('have.attr', 'data-href', defaultProps.homeUrl);
            cy.get('[data-testid="prev-student"]')
                .should('have.attr', 'title', 'Previous Student')
                .and('have.attr', 'data-href', defaultProps.prevStudentUrl);
            cy.get('[data-testid="next-student"]')
                .should('have.attr', 'title', 'Next Student')
                .and('have.attr', 'data-href', defaultProps.nextStudentUrl);
            cy.get('[data-testid="fullscreen-btn"]')
                .should('have.attr', 'title', 'Toggle the full screen mode');
            cy.get('[data-testid="two-panel-exchange-button"]')
                .should('have.attr', 'title', 'Exchange the panel positions');
            cy.get('[data-testid="grading-setting-btn"]')
                .should('have.attr', 'title', 'Show Grading Settings');
        });

        it('renders panel selector toggle and progress bar', () => {
            cy.mount(TaGradingToolbar, { props: defaultProps });

            cy.get('[data-testid="panel-selector-toggle"]').should('exist');
            cy.get('[data-testid="progress-bar"]').within(() => {
                cy.get('b').should('have.text', `${defaultProps.progress}%`);
                cy.get('progress')
                    .should('have.attr', 'max', '100')
                    .and('have.attr', 'value', String(defaultProps.progress));
            });
        });
    });

    describe('fullscreen toggle', () => {
        it('shows expand icon when not in fullscreen mode', () => {
            cy.mount(TaGradingToolbar, { props: defaultProps });

            cy.get('[data-testid="fullscreen-btn"] i')
                .should('have.class', 'fa-expand')
                .and('not.have.class', 'fa-compress');
        });

        it('shows compress icon when in fullscreen mode', () => {
            localStorage.setItem('taLayoutDetails', JSON.stringify({ isFullScreenMode: true }));
            cy.mount(TaGradingToolbar, { props: defaultProps });

            cy.get('[data-testid="fullscreen-btn"] i')
                .should('have.class', 'fa-compress')
                .and('not.have.class', 'fa-expand');
        });
    });

    describe('fullscreen button click', () => {
        it('toggles icon from expand to compress on click', () => {
            cy.mount(TaGradingToolbar, { props: defaultProps });

            cy.get('[data-testid="fullscreen-btn"] i').should('have.class', 'fa-expand');
            cy.get('[data-testid="fullscreen-btn"]').click();
            cy.get('[data-testid="fullscreen-btn"] i').should('have.class', 'fa-compress');
        });
    });

    describe('cluster mode', () => {
        it('does not render cluster button when clusteringEnabled is false', () => {
            cy.mount(TaGradingToolbar, {
                props: { ...defaultProps, clusteringEnabled: false, clustersExist: true },
            });
            cy.get('[data-testid="toggle-cluster-mode"]').should('not.exist');
        });

        it('does not render cluster button when clustersExist is false', () => {
            cy.mount(TaGradingToolbar, {
                props: { ...defaultProps, clusteringEnabled: true, clustersExist: false },
            });
            cy.get('[data-testid="toggle-cluster-mode"]').should('not.exist');
        });

        it('renders cluster button when clusteringEnabled and clustersExist are true', () => {
            cy.mount(TaGradingToolbar, {
                props: { ...defaultProps, clusteringEnabled: true, clustersExist: true, taGradingClusterMode: false },
            });
            cy.get('[data-testid="toggle-cluster-mode"]')
                .should('be.visible')
                .and('have.attr', 'title', 'Cluster Grading: OFF (Click to enable)');
        });

        it('shows ON label when taGradingClusterMode is true', () => {
            cy.mount(TaGradingToolbar, {
                props: { ...defaultProps, clusteringEnabled: true, clustersExist: true, taGradingClusterMode: true },
            });
            cy.get('[data-testid="toggle-cluster-mode"]')
                .should('have.attr', 'title', 'Cluster Grading: ON (Click to disable)');
        });

        it('emits toggle-cluster-mode when clicked with clusters', () => {
            mountWithEmitSpy(TaGradingToolbar, 'toggleClusterMode', {
                ...defaultProps,
                clusteringEnabled: true,
                clustersExist: true,
            }, 'clusterHandler');
            cy.get('[data-testid="toggle-cluster-mode"]').click();
            cy.get('@clusterHandler').should('have.been.calledOnce');
        });

        it('does not emit when clicked without clusters', () => {
            mountWithEmitSpy(TaGradingToolbar, 'toggleClusterMode', {
                ...defaultProps,
                clusteringEnabled: true,
                clustersExist: false,
            }, 'clusterHandler');
            cy.get('[data-testid="toggle-cluster-mode"]').should('not.exist');
            cy.get('@clusterHandler').should('not.have.been.called');
        });
    });

    describe('select-layout event', () => {
        it('forwards select-layout from PanelSelectorModal to parent', () => {
            mountWithEmitSpy(TaGradingToolbar, 'selectLayout', defaultProps, 'layoutHandler');

            cy.get('[data-testid="panel-selector-toggle"]').click();
            cy.get('[data-testid="layout-single-panel-apply"]').click();
            cy.get('@layoutHandler').should('have.been.calledOnceWith', {
                panels: 1,
                isLeftTaller: false,
                twoInRight: false,
            });
        });
    });
});
