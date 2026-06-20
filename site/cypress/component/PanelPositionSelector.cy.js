import PanelPositionSelector from '../../vue/src/components/PanelPositionSelector.vue';

describe('PanelPositionSelector', () => {
    it('renders 4 options in 4-panel mode', () => {
        cy.mount(PanelPositionSelector, {
            props: {
                panelId: 'autograding_results',
                currentPosition: null,
                numOfPanels: 4,
                dividedColName: 'LEFT',
            },
        });

        cy.get('[data-testid="panel-position-select"]').should('be.visible');
        cy.get('[data-testid="panel-position-select"] option').should('have.length', 4);
        cy.get('[data-testid="panel-position-leftTop"]').should('contain', 'Open as top left panel');
        cy.get('[data-testid="panel-position-leftBottom"]').should('contain', 'Open as bottom left panel');
        cy.get('[data-testid="panel-position-rightTop"]').should('contain', 'Open as top right panel');
        cy.get('[data-testid="panel-position-rightBottom"]').should('contain', 'Open as bottom right panel');
    });

    it('renders 2 options in 2-panel mode', () => {
        cy.mount(PanelPositionSelector, {
            props: {
                panelId: 'autograding_results',
                currentPosition: null,
                numOfPanels: 2,
                dividedColName: 'LEFT',
            },
        });

        cy.get('[data-testid="panel-position-select"] option').should('have.length', 2);
        cy.get('[data-testid="panel-position-leftTop"]').should('contain', 'Open as left panel');
        cy.get('[data-testid="panel-position-rightTop"]').should('contain', 'Open as right panel');
    });

    it('renders 3 options in 3-panel LEFT mode', () => {
        cy.mount(PanelPositionSelector, {
            props: {
                panelId: 'autograding_results',
                currentPosition: null,
                numOfPanels: 3,
                dividedColName: 'LEFT',
            },
        });

        cy.get('[data-testid="panel-position-select"] option').should('have.length', 3);
        cy.get('[data-testid="panel-position-leftTop"]').should('contain', 'Open as top left panel');
        cy.get('[data-testid="panel-position-leftBottom"]').should('contain', 'Open as bottom left panel');
        cy.get('[data-testid="panel-position-rightTop"]').should('contain', 'Open as right panel');
    });

    it('renders 3 options in 3-panel RIGHT mode', () => {
        cy.mount(PanelPositionSelector, {
            props: {
                panelId: 'autograding_results',
                currentPosition: null,
                numOfPanels: 3,
                dividedColName: 'RIGHT',
            },
        });

        cy.get('[data-testid="panel-position-select"] option').should('have.length', 3);
        cy.get('[data-testid="panel-position-leftTop"]').should('contain', 'Open as left panel');
        cy.get('[data-testid="panel-position-rightTop"]').should('contain', 'Open as top right panel');
        cy.get('[data-testid="panel-position-rightBottom"]').should('contain', 'Open as bottom right panel');
    });

    it('renders no options in 1-panel mode', () => {
        cy.mount(PanelPositionSelector, {
            props: {
                panelId: 'autograding_results',
                currentPosition: null,
                numOfPanels: 1,
            },
        });

        cy.get('[data-testid="panel-position-select"] option').should('have.length', 0);
    });

    it('updates options when panel-layout-changed event fires', () => {
        cy.mount(PanelPositionSelector, {
            props: {
                panelId: 'autograding_results',
                currentPosition: null,
                numOfPanels: 2,
            },
        });

        cy.get('[data-testid="panel-position-select"] option').should('have.length', 2);

        cy.window().then((win) => {
            win.dispatchEvent(new CustomEvent('panel-layout-changed', {
                detail: { numOfPanelsEnabled: 4, dividedColName: 'LEFT' },
            }));
        });

        cy.get('[data-testid="panel-position-select"] option').should('have.length', 4);
    });

    it('emits position-change with correct payload on selection', () => {
        const onChange = cy.stub().as('position-change');

        cy.mount(PanelPositionSelector, {
            props: {
                panelId: 'grading_rubric',
                currentPosition: null,
                numOfPanels: 4,
                dividedColName: 'LEFT',
                'onPosition-change': onChange,
            },
        });

        cy.get('[data-testid="panel-position-select"]').then(($select) => {
            const select = $select[0];
            select.value = 'leftTop';
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });
        cy.get('@position-change').should('have.been.calledWith', {
            panelId: 'grading_rubric',
            position: 'leftTop',
        });
    });

    it('sets the select id from panelId', () => {
        cy.mount(PanelPositionSelector, {
            props: {
                panelId: 'student_info',
                currentPosition: null,
                numOfPanels: 4,
            },
        });

        cy.get('#student_info_select').should('be.visible');
    });

    it('has accessible data-testid on the select element', () => {
        cy.mount(PanelPositionSelector, {
            props: {
                panelId: 'solution_ta_notes',
                currentPosition: null,
                numOfPanels: 4,
            },
        });

        cy.get('[data-testid="panel-position-select"]')
            .should('have.attr', 'id', 'solution_ta_notes_select');
    });
});
