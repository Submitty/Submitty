import DropdownButton from '../../vue/src/components/DropdownButton.vue';
import { mountWithEmitSpy } from '../support/component_test_utils.js';

describe('DropdownButton', () => {
    // Real items from ForumThreadView.php $more_data + Twig construction
    const defaultProps = {
        items: [
            { id: 'merge_thread', displayText: 'Show Merged Threads', title: 'Show Merged Threads on Forum', link: '#' },
            { id: 'delete', displayText: 'Show Deleted Threads', title: 'Show Deleted Threads on Forum', link: '#' },
            { id: 'forum_stats', displayText: 'Stats', title: 'Forum Statistics', link: '/forum/stats' },
        ],
    };

    function openDropdown() {
        cy.get('[data-testid="dropdown-trigger"]').click();
    }

    describe('rendering', () => {
        it('renders the trigger button with default label', () => {
            cy.mount(DropdownButton, { props: defaultProps });
            cy.get('[data-testid="dropdown-trigger"]')
                .should('be.visible')
                .and('contain', 'Dropdown');
        });

        it('renders the trigger button with custom label', () => {
            cy.mount(DropdownButton, { props: { ...defaultProps, label: 'Actions' } });
            cy.get('[data-testid="dropdown-trigger"]')
                .should('be.visible')
                .and('contain', 'Actions');
        });

        it('renders all items when open', () => {
            cy.mount(DropdownButton, { props: defaultProps });
            openDropdown();
            defaultProps.items.forEach((item) => {
                cy.get(`[data-testid="${item.id}"]`)
                    .should('be.visible')
                    .and('contain', item.displayText);
            });
        });
    });

    describe('toggle and alignment', () => {
        it('shows menu when trigger is clicked', () => {
            cy.mount(DropdownButton, { props: defaultProps });
            cy.get('[data-testid="dropdown-menu"]').should('not.have.class', 'show');
            openDropdown();
            cy.get('[data-testid="dropdown-menu"]').should('have.class', 'show');
        });

        it('aligns menu to the right by default', () => {
            cy.mount(DropdownButton, { props: defaultProps });
            openDropdown();
            cy.get('[data-testid="dropdown-menu"]').should('have.class', 'dropdown-menu-right');
        });

        it('aligns menu to the left when align="left"', () => {
            cy.mount(DropdownButton, { props: { ...defaultProps, align: 'left' } });
            openDropdown();
            cy.get('[data-testid="dropdown-menu"]').should('have.class', 'dropdown-menu-left');
        });
    });

    describe('outside-click and Escape', () => {
        it('closes on backdrop click', () => {
            cy.mount(DropdownButton, { props: defaultProps });
            openDropdown();
            cy.get('[data-testid="dropdown-menu"]').should('have.class', 'show');
            cy.get('[data-testid="dropdown-backdrop"]').click({ force: true });
            cy.get('[data-testid="dropdown-menu"]').should('not.have.class', 'show');
        });

        it('does not render backdrop when closed', () => {
            cy.mount(DropdownButton, { props: defaultProps });
            cy.get('[data-testid="dropdown-backdrop"]').should('not.exist');
        });

        it('closes on Escape key', () => {
            cy.mount(DropdownButton, { props: defaultProps });
            openDropdown();
            cy.get('[data-testid="dropdown-trigger"]').focus();
            cy.get('[data-testid="dropdown-trigger"]').type('{esc}');
            cy.get('[data-testid="dropdown-menu"]').should('not.have.class', 'show');
        });
    });

    describe('select emit', () => {
        it('emits select with item id when item has no link', () => {
            const mergeItem = defaultProps.items.find((i) => i.id === 'merge_thread');
            mountWithEmitSpy(DropdownButton, 'select', defaultProps, 'onSelect');
            openDropdown();
            cy.get(`[data-testid="${mergeItem.id}"]`).click({ force: true });
            cy.get('@onSelect').should('have.been.calledWith', mergeItem.id);
        });

        it('emits select when item link is "#"', () => {
            mountWithEmitSpy(DropdownButton, 'select', defaultProps, 'onSelect');
            openDropdown();
            cy.get('[data-testid="delete"]').click({ force: true });
            cy.get('@onSelect').should('have.been.calledWith', 'delete');
        });
    });

    describe('navigate emit', () => {
        it('emits navigate with URL when item has a link', () => {
            const statsItem = defaultProps.items.find((i) => i.id === 'forum_stats');
            mountWithEmitSpy(DropdownButton, 'navigate', defaultProps, 'onNavigate');
            openDropdown();
            cy.get(`[data-testid="${statsItem.id}"]`).click({ force: true });
            cy.get('@onNavigate').should('have.been.calledWith', statsItem.link);
        });
    });

    describe('badge', () => {
        it('renders badge when badgeText is present', () => {
            const items = [
                { id: 'toggle-attachments', displayText: 'Show Attachments', title: 'Toggle', badgeText: '12' },
            ];
            cy.mount(DropdownButton, { props: { items } });
            openDropdown();
            cy.get('[data-testid="attachment-badge"]').should('have.text', '12');
        });

        it('does not render badge when badgeText is absent', () => {
            cy.mount(DropdownButton, { props: defaultProps });
            openDropdown();
            cy.get('[data-testid="attachment-badge"]').should('not.exist');
        });
    });

    describe('toggle-attachments display text', () => {
        const attachmentItem = {
            id: 'toggle-attachments',
            displayText: 'Show Attachments',
            title: 'Click to toggle all post attachments',
            badgeText: '3',
        };

        it('shows initial displayText', () => {
            cy.mount(DropdownButton, { props: { items: [attachmentItem] } });
            openDropdown();
            cy.get(`[data-testid="${attachmentItem.id}"]`).should('contain', attachmentItem.displayText);
        });

        it('toggles to "Hide Attachments" on click', () => {
            cy.mount(DropdownButton, { props: { items: [attachmentItem] } });
            openDropdown();
            cy.get(`[data-testid="${attachmentItem.id}"]`).click({ force: true });
            openDropdown();
            cy.get(`[data-testid="${attachmentItem.id}"]`).should('contain', 'Hide Attachments');
        });

        it('emits select with item id on click', () => {
            mountWithEmitSpy(DropdownButton, 'select', { items: [attachmentItem] }, 'onSelect');
            openDropdown();
            cy.get(`[data-testid="${attachmentItem.id}"]`).click({ force: true });
            cy.get('@onSelect').should('have.been.calledWith', attachmentItem.id);
        });
    });

    describe('hidden items', () => {
        it('does not render items with hidden: true', () => {
            const items = [
                { id: 'mark-unread', displayText: 'Unread Thread', title: 'Mark Thread Unread' },
                { id: 'merge_thread', displayText: 'Show Merged Threads', title: 'Show Merged Threads on Forum', hidden: true },
            ];
            cy.mount(DropdownButton, { props: { items } });
            openDropdown();
            cy.get('[data-testid="mark-unread"]').should('be.visible');
            cy.get('[data-testid="merge_thread"]').should('not.exist');
        });
    });

    describe('empty items', () => {
        it('renders trigger with no menu items', () => {
            cy.mount(DropdownButton, { props: { items: [] } });
            cy.get('[data-testid="dropdown-trigger"]').should('be.visible');
            openDropdown();
            cy.get('[data-testid="dropdown-menu"]').should('have.class', 'show');
            cy.get('[data-testid="dropdown-menu"] .dropdown-item').should('have.length', 0);
        });
    });
});
