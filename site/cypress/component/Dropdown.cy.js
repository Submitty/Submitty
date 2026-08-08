import Dropdown from '../../vue/src/components/Dropdown.vue';

describe('Dropdown', () => {
    describe('rendering', () => {
        it('renders the trigger button with default label', () => {
            cy.mount(Dropdown);
            cy.get('[data-testid="dropdown-trigger"]')
                .should('be.visible')
                .and('contain', 'Dropdown');
        });

        it('renders the trigger button with custom label', () => {
            cy.mount(Dropdown, { props: { label: 'Actions' } });
            cy.get('[data-testid="dropdown-trigger"]')
                .should('be.visible')
                .and('contain', 'Actions');
        });

        it('renders slot content inside the menu', () => {
            cy.mount(Dropdown, {
                slots: {
                    default: '<a data-testid="menu-item" class="dropdown-item" href="#">Item 1</a>',
                },
            });
            cy.get('[data-testid="dropdown-trigger"]').click();
            cy.get('[data-testid="menu-item"]').should('be.visible');
        });

        it('shows menu when trigger is clicked', () => {
            cy.mount(Dropdown, {
                slots: {
                    default: '<a class="dropdown-item" href="#">Item</a>',
                },
            });
            cy.get('[data-testid="dropdown-menu"]').should('not.have.class', 'show');
            cy.get('[data-testid="dropdown-trigger"]').click();
            cy.get('[data-testid="dropdown-menu"]').should('have.class', 'show');
        });

        it('closes menu when trigger is clicked again', () => {
            cy.mount(Dropdown, {
                slots: {
                    default: '<a class="dropdown-item" href="#">Item</a>',
                },
            });
            cy.get('[data-testid="dropdown-trigger"]').click();
            cy.get('[data-testid="dropdown-menu"]').should('have.class', 'show');
            cy.get('[data-testid="dropdown-trigger"]').click();
            cy.get('[data-testid="dropdown-menu"]').should('not.have.class', 'show');
        });

        it('aligns menu to the right by default', () => {
            cy.mount(Dropdown, {
                slots: { default: '<a class="dropdown-item" href="#">Item</a>' },
            });
            cy.get('[data-testid="dropdown-trigger"]').click();
            cy.get('[data-testid="dropdown-menu"]').should('have.class', 'dropdown-menu-right');
        });

        it('aligns menu to the left when align="left"', () => {
            cy.mount(Dropdown, {
                props: { align: 'left' },
                slots: { default: '<a class="dropdown-item" href="#">Item</a>' },
            });
            cy.get('[data-testid="dropdown-trigger"]').click();
            cy.get('[data-testid="dropdown-menu"]').should('have.class', 'dropdown-menu-left');
        });
    });

    describe('outside-click and Escape', () => {
        it('closes on backdrop click', () => {
            cy.mount(Dropdown, {
                slots: { default: '<a class="dropdown-item" href="#">Item</a>' },
            });
            cy.get('[data-testid="dropdown-trigger"]').click();
            cy.get('[data-testid="dropdown-menu"]').should('have.class', 'show');
            cy.get('[data-testid="dropdown-backdrop"]').click({ force: true });
            cy.get('[data-testid="dropdown-menu"]').should('not.have.class', 'show');
        });

        it('does not render backdrop when closed', () => {
            cy.mount(Dropdown, {
                slots: { default: '<a class="dropdown-item" href="#">Item</a>' },
            });
            cy.get('[data-testid="dropdown-backdrop"]').should('not.exist');
        });

        it('closes on Escape key', () => {
            cy.mount(Dropdown, {
                slots: { default: '<a class="dropdown-item" href="#">Item</a>' },
            });
            cy.get('[data-testid="dropdown-trigger"]').click();
            cy.get('[data-testid="dropdown-menu"]').should('have.class', 'show');
            cy.get('[data-testid="dropdown-trigger"]').focus();
            cy.get('[data-testid="dropdown-trigger"]').type('{esc}');
            cy.get('[data-testid="dropdown-menu"]').should('not.have.class', 'show');
        });
    });

    describe('custom trigger slot', () => {
        it('renders custom trigger content', () => {
            cy.mount(Dropdown, {
                slots: {
                    trigger: '<button data-testid="custom-trigger" type="button">Custom</button>',
                    default: '<a class="dropdown-item" href="#">Item</a>',
                },
            });
            cy.get('[data-testid="custom-trigger"]').should('be.visible').and('contain', 'Custom');
        });

        it('toggles via custom trigger toggle slot prop', () => {
            cy.mount(Dropdown, {
                slots: {
                    trigger: '<button data-testid="custom-trigger" type="button" @click="toggle">Custom</button>',
                    default: '<a class="dropdown-item" href="#">Item</a>',
                },
            });
            cy.get('[data-testid="custom-trigger"]').click();
            cy.get('[data-testid="dropdown-menu"]').should('have.class', 'show');
        });
    });

    describe('close slot prop', () => {
        it('closes the dropdown when close() is called from default slot', () => {
            cy.mount(Dropdown, {
                slots: {
                    default: '<button data-testid="close-btn" @click="close">Close</button>',
                },
            });
            cy.get('[data-testid="dropdown-trigger"]').click();
            cy.get('[data-testid="dropdown-menu"]').should('have.class', 'show');
            cy.get('[data-testid="close-btn"]').click();
            cy.get('[data-testid="dropdown-menu"]').should('not.have.class', 'show');
        });
    });
});
