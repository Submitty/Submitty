import ForumFilterBar from '../../vue/src/components/forum/ForumFilterBar.vue';

describe('ForumFilterBar', () => {
    const categories = [
        { id: 1, description: 'General Questions', visibleDate: null },
        { id: 2, description: 'Homework Help', visibleDate: null },
        { id: 3, description: 'Quizzes', visibleDate: '2026-06-10T00:00:00Z', diff: 5 },
        { id: 4, description: 'Upcoming Topics', visibleDate: '2026-07-01T00:00:00Z', diff: -5 },
    ];

    describe('rendering', () => {
        it('renders unread button and filters correctly', () => {
            cy.mount(ForumFilterBar, { props: { categories } });
            cy.get('[data-testid="filter-unread-label"]').should('be.visible').and('contain', 'Unread Only');
            cy.get('[data-testid="thread-category-1"]').should('be.visible').and('contain', 'General Questions');
            cy.get('[data-testid="thread-category-4"]').should('not.exist');
            cy.get('[data-testid="thread-status-comment"]').should('be.visible').and('contain', 'Comment');
        });

        it('handles empty categories array', () => {
            cy.mount(ForumFilterBar, { props: { categories: [] } });
            cy.get('[data-testid="forum-filter-bar"]').should('be.visible');
            cy.get('[data-testid="thread-category-filter"]').children().should('have.length', 0);
        });

        it('has accessible titles on all interactive elements', () => {
            cy.mount(ForumFilterBar, { props: { categories } });
            cy.get('[data-testid="filter-unread-label"]').should('have.attr', 'title', 'Toggle unread filter');
            cy.get('[data-testid="thread-category-1"]').should('have.attr', 'title', 'Filter by General Questions');
            cy.get('[data-testid="thread-status-comment"]').should('have.attr', 'title', 'Filter by comment status');
            cy.get('[data-testid="thread-status-unresolved"]').should('have.attr', 'title', 'Filter by unresolved status');
            cy.get('[data-testid="thread-status-resolved"]').should('have.attr', 'title', 'Filter by resolved status');
        });
    });

    describe('category filter toggling', () => {
        it('toggles category button active class and emits update event', () => {
            const onUpdateCategoryIds = cy.stub().as('updateCategoryIds');

            cy.mount(ForumFilterBar, {
                props: {
                    categories,
                    'onUpdate:selectedCategoryIds': onUpdateCategoryIds,
                },
            });

            cy.get('[data-testid="thread-category-1"]').should('have.class', 'filter-inactive').and('have.attr', 'data-btn-selected', 'false');
            cy.get('[data-testid="thread-category-1"]').click();
            cy.get('[data-testid="thread-category-1"]').should('have.class', 'filter-active').and('have.attr', 'data-btn-selected', 'true');
            cy.get('@updateCategoryIds').should('have.been.called');
            cy.get('[data-testid="thread-category-1"]').click();
            cy.get('[data-testid="thread-category-1"]').should('have.class', 'filter-inactive').and('have.attr', 'data-btn-selected', 'false');
        });
    });

    describe('status filter toggling', () => {
        it('toggles a single status button', () => {
            const onUpdateStatuses = cy.stub().as('updateStatuses');

            cy.mount(ForumFilterBar, {
                props: {
                    categories,
                    'onUpdate:selectedThreadStatuses': onUpdateStatuses,
                },
            });

            cy.get('[data-testid="thread-status-unresolved"]').should('have.class', 'filter-inactive');
            cy.get('[data-testid="thread-status-unresolved"]').click();
            cy.get('[data-testid="thread-status-unresolved"]').should('have.class', 'filter-active');
            cy.get('@updateStatuses').should('have.been.called');
            cy.get('[data-testid="thread-status-unresolved"]').click();
            cy.get('[data-testid="thread-status-unresolved"]').should('have.class', 'filter-inactive');
        });

        it('supports multiple statuses selected simultaneously', () => {
            cy.mount(ForumFilterBar, { props: { categories } });
            cy.get('[data-testid="thread-status-comment"]').click();
            cy.get('[data-testid="thread-status-resolved"]').click();
            cy.get('[data-testid="thread-status-comment"]').should('have.class', 'filter-active');
            cy.get('[data-testid="thread-status-resolved"]').should('have.class', 'filter-active');
        });
    });

    describe('unread filter', () => {
        it('toggles button class and emits update event', () => {
            const onUpdateUnread = cy.stub().as('updateUnread');

            cy.mount(ForumFilterBar, {
                props: {
                    categories,
                    'onUpdate:unreadChecked': onUpdateUnread,
                },
            });

            cy.get('[data-testid="filter-unread-label"]').should('have.class', 'filter-inactive');
            cy.get('[data-testid="filter-unread-checkbox"]').should('not.be.checked');
            cy.get('[data-testid="filter-unread-label"]').click();
            cy.get('[data-testid="filter-unread-label"]').should('have.class', 'filter-active');
            cy.get('[data-testid="filter-unread-checkbox"]').should('be.checked');
            cy.get('@updateUnread').should('have.been.called');
        });
    });

    describe('clear filters', () => {
        it('resets all filter state when deactivating all filters via clicking', () => {
            cy.mount(ForumFilterBar, {
                props: {
                    categories,
                },
            });

            // Activate some filters
            cy.get('[data-testid="thread-category-1"]').click();
            cy.get('[data-testid="thread-status-comment"]').click();
            cy.get('[data-testid="filter-unread-label"]').click();

            // Deactivate by clicking active buttons
            cy.get('[data-testid="thread-category-1"]').click();
            cy.get('[data-testid="thread-status-comment"]').click();
            cy.get('[data-testid="filter-unread-label"]').click();

            cy.get('[data-testid="thread-category-1"]').should('have.class', 'filter-inactive').and('have.attr', 'data-btn-selected', 'false');
            cy.get('[data-testid="thread-status-comment"]').should('have.class', 'filter-inactive');
            cy.get('[data-testid="filter-unread-label"]').should('have.class', 'filter-inactive');
            cy.get('[data-testid="filter-unread-checkbox"]').should('not.be.checked');
        });
    });

    describe('initial state from props', () => {
        it('applies initial selected categories from props', () => {
            cy.mount(ForumFilterBar, {
                props: {
                    categories,
                    initialSelectedCategoryIds: [1, 2],
                },
            });
            cy.get('[data-testid="thread-category-1"]').should('have.class', 'filter-active').and('have.attr', 'data-btn-selected', 'true');
            cy.get('[data-testid="thread-category-2"]').should('have.class', 'filter-active').and('have.attr', 'data-btn-selected', 'true');
            cy.get('[data-testid="thread-category-3"]').should('have.class', 'filter-inactive').and('have.attr', 'data-btn-selected', 'false');
        });

        it('applies initial selected statuses from props', () => {
            cy.mount(ForumFilterBar, {
                props: {
                    categories,
                    initialSelectedThreadStatuses: [-1, 1],
                },
            });
            cy.get('[data-testid="thread-status-unresolved"]').should('have.class', 'filter-active').and('have.attr', 'data-btn-selected', 'true');
            cy.get('[data-testid="thread-status-resolved"]').should('have.class', 'filter-active').and('have.attr', 'data-btn-selected', 'true');
            cy.get('[data-testid="thread-status-comment"]').should('have.class', 'filter-inactive').and('have.attr', 'data-btn-selected', 'false');
        });

        it('applies initial unread state from props', () => {
            cy.mount(ForumFilterBar, {
                props: {
                    categories,
                    initialUnreadChecked: true,
                },
            });
            cy.get('[data-testid="filter-unread-label"]').should('have.class', 'filter-active');
            cy.get('[data-testid="filter-unread-checkbox"]').should('be.checked');
        });
    });

    describe('composite events', () => {
        it('emits filter-change with all current state on any toggle', () => {
            const onFilterChange = cy.stub().as('filterChange');

            cy.mount(ForumFilterBar, {
                props: {
                    categories,
                    'onFilter-change': onFilterChange,
                },
            });

            cy.get('[data-testid="thread-category-1"]').click();
            cy.get('@filterChange').should('have.been.calledWith', {
                categories: [1],
                statuses: [],
                unread: false,
            });
        });

        it('emits save-state on every toggle action', () => {
            const onSaveState = cy.stub().as('saveState');

            cy.mount(ForumFilterBar, {
                props: {
                    categories,
                    'onSave-state': onSaveState,
                },
            });

            cy.get('[data-testid="thread-category-1"]').click();
            cy.get('@saveState').should('have.been.called');

            cy.get('[data-testid="thread-status-comment"]').click();
            cy.get('@saveState').should('have.been.calledTwice');

            cy.get('[data-testid="filter-unread-label"]').click();
            cy.get('@saveState').should('have.been.calledThrice');
        });
    });

    describe('edge cases', () => {
        it('handles category with missing diff field', () => {
            cy.mount(ForumFilterBar, {
                props: {
                    categories: [
                        { id: 1, description: 'Exam Review', visibleDate: '2026-06-10T00:00:00Z' },
                    ],
                },
            });
            cy.get('[data-testid="thread-category-1"]').should('be.visible').and('contain', 'Exam Review');
        });

        it('handles category with null visibleDate even with negative diff', () => {
            cy.mount(ForumFilterBar, {
                props: {
                    categories: [
                        { id: 1, description: 'Announcements', visibleDate: null, diff: -10 },
                    ],
                },
            });
            cy.get('[data-testid="thread-category-1"]').should('be.visible').and('contain', 'Announcements');
        });
    });
});
