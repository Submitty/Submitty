import PanelPositionSelector from '../../vue/src/components/PanelPositionSelector.vue';

function mount(overrides = {}) {
    return cy.mount(PanelPositionSelector, {
        props: {
            panelId: 'test_panel',
            numOfPanels: 4,
            dividedColName: 'LEFT',
            ...overrides,
        },
    });
}

function triggerChange(value) {
    cy.get('[data-testid="panel-position-select"]').then(($sel) => {
        $sel[0].value = value;
        $sel[0].dispatchEvent(new Event('change', { bubbles: true }));
    });
}

describe('PanelPositionSelector', () => {
    it('renders all 4 positions in 4-panel mode', () => {
        mount({ numOfPanels: 4 });
        cy.get('[data-testid="panel-position-select"] option').should('have.length', 4);
        cy.get('[data-testid="panel-position-leftTop"]').should('have.text', 'Open as top left panel');
        cy.get('[data-testid="panel-position-leftBottom"]').should('have.text', 'Open as bottom left panel');
        cy.get('[data-testid="panel-position-rightTop"]').should('have.text', 'Open as top right panel');
        cy.get('[data-testid="panel-position-rightBottom"]').should('have.text', 'Open as bottom right panel');
    });

    it('renders 2 positions with simplified labels in 2-panel mode', () => {
        mount({ numOfPanels: 2 });
        cy.get('[data-testid="panel-position-select"] option').should('have.length', 2);
        cy.get('[data-testid="panel-position-leftTop"]').should('have.text', 'Open as left panel');
        cy.get('[data-testid="panel-position-rightTop"]').should('have.text', 'Open as right panel');
        cy.get('[data-testid="panel-position-leftBottom"]').should('not.exist');
        cy.get('[data-testid="panel-position-rightBottom"]').should('not.exist');
    });

    it('renders left-divided 3 positions in 3-panel LEFT mode', () => {
        mount({ numOfPanels: 3, dividedColName: 'LEFT' });
        cy.get('[data-testid="panel-position-select"] option').should('have.length', 3);
        cy.get('[data-testid="panel-position-leftTop"]').should('have.text', 'Open as top left panel');
        cy.get('[data-testid="panel-position-leftBottom"]').should('exist');
        cy.get('[data-testid="panel-position-rightTop"]').should('have.text', 'Open as right panel');
        cy.get('[data-testid="panel-position-rightBottom"]').should('not.exist');
    });

    it('renders right-divided 3 positions in 3-panel RIGHT mode', () => {
        mount({ numOfPanels: 3, dividedColName: 'RIGHT' });
        cy.get('[data-testid="panel-position-select"] option').should('have.length', 3);
        cy.get('[data-testid="panel-position-leftTop"]').should('have.text', 'Open as left panel');
        cy.get('[data-testid="panel-position-leftBottom"]').should('not.exist');
        cy.get('[data-testid="panel-position-rightTop"]').should('have.text', 'Open as top right panel');
        cy.get('[data-testid="panel-position-rightBottom"]').should('exist');
    });

    it('renders no options in 1-panel mode', () => {
        mount({ numOfPanels: 1 });
        cy.get('[data-testid="panel-position-select"] option').should('have.length', 0);
    });

    it('defaults to 1 panel when numOfPanels not provided', () => {
        cy.mount(PanelPositionSelector, { props: { panelId: 'test' } });
        cy.get('[data-testid="panel-position-select"] option').should('have.length', 0);
    });

    it('updates options when panel-layout-changed event fires', () => {
        mount({ numOfPanels: 2 });
        cy.get('[data-testid="panel-position-select"] option').should('have.length', 2);
        cy.window().then((win) => {
            win.dispatchEvent(new CustomEvent('panel-layout-changed', {
                detail: { numOfPanelsEnabled: 4, dividedColName: 'LEFT' },
            }));
        });
        cy.get('[data-testid="panel-position-select"] option').should('have.length', 4);
    });

    it('updates divided column when panel-layout-changed event fires', () => {
        mount({ numOfPanels: 3, dividedColName: 'LEFT' });
        cy.get('[data-testid="panel-position-leftBottom"]').should('exist');
        cy.get('[data-testid="panel-position-rightBottom"]').should('not.exist');
        cy.window().then((win) => {
            win.dispatchEvent(new CustomEvent('panel-layout-changed', {
                detail: { numOfPanelsEnabled: 3, dividedColName: 'RIGHT' },
            }));
        });
        cy.get('[data-testid="panel-position-leftBottom"]').should('not.exist');
        cy.get('[data-testid="panel-position-rightBottom"]').should('exist');
    });

    it('does not crash on panel-layout-changed with undefined detail', () => {
        mount({ numOfPanels: 4 });
        cy.window().then((win) => {
            win.dispatchEvent(new CustomEvent('panel-layout-changed', { detail: undefined }));
        });
        cy.get('[data-testid="panel-position-select"] option').should('have.length', 4);
    });

    it('emits position-change with correct panelId and position', () => {
        const spy = cy.spy().as('onChange');
        mount({ panelId: 'grading_rubric', numOfPanels: 4, 'onPosition-change': spy });
        triggerChange('leftTop');
        cy.get('@onChange')
            .should('have.been.calledOnce')
            .and('have.been.calledWith', { panelId: 'grading_rubric', position: 'leftTop' });
    });

    it('does not emit position-change on mount', () => {
        const spy = cy.spy().as('onChange');
        mount({ numOfPanels: 4, 'onPosition-change': spy });
        cy.get('@onChange').should('not.have.been.called');
    });

    it('derives select id from panelId', () => {
        mount({ panelId: 'autograding_results' });
        cy.get('#autograding_results_select').should('exist');
        mount({ panelId: 'notebook-view' });
        cy.get('#notebook-view_select').should('exist');
    });

    it('sets :size to match option count', () => {
        mount({ numOfPanels: 4 });
        cy.get('[data-testid="panel-position-select"]').should('have.attr', 'size', '4');
        mount({ numOfPanels: 2 });
        cy.get('[data-testid="panel-position-select"]').should('have.attr', 'size', '2');
        mount({ numOfPanels: 1 });
        cy.get('[data-testid="panel-position-select"]').should('have.attr', 'size', '0');
    });

    it('treats numOfPanels=0 as single panel', () => {
        mount({ numOfPanels: 0 });
        cy.get('[data-testid="panel-position-select"] option').should('have.length', 0);
    });
});
