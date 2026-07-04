import PanelPositionSelector from '../../vue/src/components/PanelPositionSelector.vue';
import { mountWithEmitSpy } from '../support/component_test_utils.js';

function setLayout(numOfPanels, dividedColName) {
    localStorage.setItem('taLayoutDetails', JSON.stringify({
        numOfPanelsEnabled: numOfPanels,
        dividedColName: dividedColName,
    }));
}

function mountWithLayout(numOfPanels, dividedColName, panelId = 'test_panel') {
    setLayout(numOfPanels, dividedColName);
    return cy.mount(PanelPositionSelector, {
        props: { panelId },
    });
}

describe('PanelPositionSelector', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    describe('option rendering', () => {
        it('renders all 4 positions with full labels in 4-panel LEFT mode', () => {
            mountWithLayout(4, 'LEFT');
            cy.get('[data-testid="panel-position-select"] option').should('have.length', 4);
            cy.get('[data-testid="panel-position-leftTop"]').should('have.text', 'Open as top left panel');
            cy.get('[data-testid="panel-position-leftBottom"]').should('have.text', 'Open as bottom left panel');
            cy.get('[data-testid="panel-position-rightTop"]').should('have.text', 'Open as top right panel');
            cy.get('[data-testid="panel-position-rightBottom"]').should('have.text', 'Open as bottom right panel');
        });

        it('renders 2 simplified positions in 2-panel mode', () => {
            mountWithLayout(2, 'LEFT');
            cy.get('[data-testid="panel-position-select"] option').should('have.length', 2);
            cy.get('[data-testid="panel-position-leftTop"]').should('have.text', 'Open as left panel');
            cy.get('[data-testid="panel-position-rightTop"]').should('have.text', 'Open as right panel');
            cy.get('[data-testid="panel-position-leftBottom"]').should('not.exist');
            cy.get('[data-testid="panel-position-rightBottom"]').should('not.exist');
        });

        it('renders left-divided 3 positions in 3-panel LEFT mode', () => {
            mountWithLayout(3, 'LEFT');
            cy.get('[data-testid="panel-position-select"] option').should('have.length', 3);
            cy.get('[data-testid="panel-position-leftTop"]').should('have.text', 'Open as top left panel');
            cy.get('[data-testid="panel-position-leftBottom"]').should('exist');
            cy.get('[data-testid="panel-position-rightTop"]').should('have.text', 'Open as right panel');
            cy.get('[data-testid="panel-position-rightBottom"]').should('not.exist');
        });

        it('renders right-divided 3 positions in 3-panel RIGHT mode', () => {
            mountWithLayout(3, 'RIGHT');
            cy.get('[data-testid="panel-position-select"] option').should('have.length', 3);
            cy.get('[data-testid="panel-position-leftTop"]').should('have.text', 'Open as left panel');
            cy.get('[data-testid="panel-position-leftBottom"]').should('not.exist');
            cy.get('[data-testid="panel-position-rightTop"]').should('have.text', 'Open as top right panel');
            cy.get('[data-testid="panel-position-rightBottom"]').should('exist');
        });

        it('renders no options in 1-panel mode', () => {
            mountWithLayout(1, 'LEFT');
            cy.get('[data-testid="panel-position-select"] option').should('have.length', 0);
        });

        it('renders no options when localStorage is empty (defaults to 1-panel)', () => {
            cy.mount(PanelPositionSelector, { props: { panelId: 'test' } });
            cy.get('[data-testid="panel-position-select"] option').should('have.length', 0);
        });

        it('treats 0 panels as single panel', () => {
            mountWithLayout(0, 'LEFT');
            cy.get('[data-testid="panel-position-select"] option').should('have.length', 0);
        });

        it('renders no options with malformed localStorage', () => {
            localStorage.setItem('taLayoutDetails', 'not-json');
            cy.mount(PanelPositionSelector, { props: { panelId: 'test' } });
            cy.get('[data-testid="panel-position-select"] option').should('have.length', 0);
        });
    });

    describe('events', () => {
        it('emits position-change with panelId and position on selection', () => {
            mountWithLayout(4, 'LEFT');
            mountWithEmitSpy(PanelPositionSelector, 'positionChange', { panelId: 'grading_rubric' }, 'onChange');
            cy.get('[data-testid="panel-position-select"]').then(($sel) => {
                $sel[0].value = 'leftTop';
                $sel[0].dispatchEvent(new Event('change', { bubbles: true }));
            });
            cy.get('@onChange')
                .should('have.been.calledOnce')
                .and('have.been.calledWith', { panelId: 'grading_rubric', position: 'leftTop' });
        });

        it('does not emit position-change on mount', () => {
            mountWithLayout(4, 'LEFT');
            mountWithEmitSpy(PanelPositionSelector, 'positionChange', { panelId: 'test' }, 'onChange');
            cy.get('@onChange').should('not.have.been.called');
        });
    });
});
