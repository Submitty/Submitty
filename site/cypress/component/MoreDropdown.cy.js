import MoreDropdown from '../../vue/src/components/forum/MoreDropdown.vue';
import { mountWithEmitSpy } from '../support/component_test_utils.js';

describe('MoreDropdown', () => {
    const defaultItems = [
        { id: 'mark-unread', displayText: 'Unread Thread', title: 'Mark Thread Unread' },
        { id: 'merge_thread', displayText: 'Merge', title: 'Merge Thread' },
        { id: 'delete', displayText: 'Delete', title: 'Delete Thread' },
        { id: 'forum_stats', displayText: 'Stats', title: 'Forum Statistics' },
    ];

    const defaultProps = {
        items: defaultItems,
        currentDisplayOption: 'tree',
        threadExists: true,
        isFullThreadsPage: false,
    };

    function openDropdown() {
        cy.get('[data-testid="more-dropdown-trigger"]').click();
    }

    describe('rendering', () => {
        it('renders each item display text when open', () => {
            cy.mount(MoreDropdown, { props: defaultProps });
            openDropdown();
            cy.contains('Unread Thread').should('be.visible');
            cy.contains('Merge').should('be.visible');
            cy.contains('Delete').should('be.visible');
            cy.contains('Stats').should('be.visible');
        });

        it('shows badge and status span when badgeText is present', () => {
            const items = [
                { id: 'toggle-attachments', displayText: 'Show Attachments', title: 'Toggle', badgeText: '2' },
            ];
            cy.mount(MoreDropdown, { props: { ...defaultProps, items } });
            openDropdown();
            cy.get('[data-testid="attachment-badge"]').should('have.text', '2');
            cy.get('[data-testid="attachment-status"]').should('exist');
        });

        it('hides badge and status when badgeText is absent', () => {
            cy.mount(MoreDropdown, { props: defaultProps });
            openDropdown();
            cy.get('[data-testid="attachment-badge"]').should('not.exist');
        });

        it('renders divider before item when dividerBefore is set', () => {
            const items = [
                { id: 'test', displayText: 'Test', title: 'Test', dividerBefore: true },
            ];
            cy.mount(MoreDropdown, { props: { ...defaultProps, items } });
            openDropdown();
            cy.get('[data-testid="dropdown-divider"]').should('exist');
        });

        it('renders divider after item when dividerAfter is set', () => {
            const items = [
                { id: 'test', displayText: 'Test', title: 'Test', dividerAfter: true },
            ];
            cy.mount(MoreDropdown, { props: { ...defaultProps, items } });
            openDropdown();
            cy.get('[data-testid="dropdown-divider"]').should('exist');
        });

        it('renders only the conditional end divider when neither dividerBefore nor dividerAfter is set', () => {
            const items = [
                { id: 'test', displayText: 'Test', title: 'Test' },
            ];
            cy.mount(MoreDropdown, { props: { ...defaultProps, items } });
            openDropdown();
            cy.get('[data-testid="dropdown-divider"]').should('have.length', 1);
        });

        it('hides display options on fullThreadsPage', () => {
            cy.mount(MoreDropdown, { props: { ...defaultProps, isFullThreadsPage: true } });
            openDropdown();
            cy.contains('Hierarchical Post Order').should('not.exist');
        });

        it('hides display options when threadExists is false', () => {
            cy.mount(MoreDropdown, { props: { ...defaultProps, threadExists: false } });
            openDropdown();
            cy.contains('Hierarchical Post Order').should('not.exist');
        });
    });

    describe('item clicks', () => {
        it('emits toggle-merged when merge_thread clicked', () => {
            mountWithEmitSpy(MoreDropdown, 'toggle-merged', defaultProps, 'onToggleMerged');
            openDropdown();
            cy.get('[data-testid="merge_thread"]').click({ force: true });
            cy.get('@onToggleMerged').should('have.callCount', 1);
        });

        it('emits toggle-deleted when delete clicked', () => {
            mountWithEmitSpy(MoreDropdown, 'toggle-deleted', defaultProps, 'onToggleDeleted');
            openDropdown();
            cy.get('[data-testid="delete"]').click({ force: true });
            cy.get('@onToggleDeleted').should('have.callCount', 1);
        });

        it('emits navigate-stats when forum_stats clicked', () => {
            mountWithEmitSpy(MoreDropdown, 'navigate-stats', defaultProps, 'onNavigateStats');
            openDropdown();
            cy.get('[data-testid="forum_stats"]').click({ force: true });
            cy.get('@onNavigateStats').should('have.callCount', 1);
        });

        it('emits navigate with URL for items with link', () => {
            const items = [
                { id: 'goto', displayText: 'Go', title: 'Go Somewhere', link: '/some/url' },
            ];
            mountWithEmitSpy(MoreDropdown, 'navigate', { ...defaultProps, items }, 'onNavigate');
            openDropdown();
            cy.get('[data-testid="goto"]').click({ force: true });
            cy.get('@onNavigate').should('have.been.calledWith', '/some/url');
        });

        it('emits item-click with id for items without link', () => {
            const items = [
                { id: 'mark-unread', displayText: 'Unread', title: 'Unread Thread' },
            ];
            mountWithEmitSpy(MoreDropdown, 'item-click', { ...defaultProps, items }, 'onItemClick');
            openDropdown();
            cy.get('[data-testid="mark-unread"]').click({ force: true });
            cy.get('@onItemClick').should('have.been.calledWith', 'mark-unread');
        });
    });

    describe('toggle-attachments', () => {
        const attrs = { id: 'toggle-attachments', displayText: 'Show Attachments', title: 'Toggle Attachments', badgeText: '3' };

        it('emits toggle-attachments on click', () => {
            mountWithEmitSpy(MoreDropdown, 'toggle-attachments', {
                ...defaultProps, items: [attrs],
            }, 'onToggleAttachments');
            openDropdown();
            cy.get('[data-testid="toggle-attachments"]').click({ force: true });
            cy.get('@onToggleAttachments').should('have.callCount', 1);
        });

        it('toggles display text between Show and Hide on successive clicks', () => {
            cy.mount(MoreDropdown, { props: { ...defaultProps, items: [attrs] } });
            openDropdown();
            cy.get('[data-testid="toggle-attachments"]').should('contain', 'Show Attachments');
            cy.get('[data-testid="toggle-attachments"]').click({ force: true });
            cy.get('[data-testid="toggle-attachments"]').should('contain', 'Hide Attachments');
            cy.get('[data-testid="toggle-attachments"]').click({ force: true });
            cy.get('[data-testid="toggle-attachments"]').should('contain', 'Show Attachments');
        });
    });

    describe('display options', () => {
        it('emits display-option-change when a display option is clicked', () => {
            mountWithEmitSpy(MoreDropdown, 'display-option-change', {
                ...defaultProps, currentDisplayOption: 'time',
            }, 'onChange');
            openDropdown();
            cy.get('[data-testid="display-option-alpha"]').click({ force: true });
            cy.get('@onChange').should('have.been.calledWith', 'alpha');
        });
    });
});
