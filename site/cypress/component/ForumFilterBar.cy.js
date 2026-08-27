import ForumFilterBar from '../../vue/src/components/forum/ForumFilterBar.vue';
import { mountWithEmitSpy } from '../support/component_test_utils.js';

describe('ForumFilterBar', () => {
    const categories = [
        { id: 1, description: 'General Questions', visibleDate: null },
        { id: 2, description: 'Homework Help', visibleDate: null },
        { id: 3, description: 'Quizzes', visibleDate: '2026-06-10T00:00:00Z', diff: 5 },
        { id: 4, description: 'Upcoming Topics', visibleDate: '2026-07-01T00:00:00Z', diff: -5 },
    ];

    const categoryTestId = (category) => `[data-testid="thread-category-${category.id}"]`;

    describe('rendering', () => {
        it('renders the unread toggle and the visible category/status filters', () => {
            cy.mount(ForumFilterBar, { props: { categories } });
            cy.get('[data-testid="filter-unread-label"]').should('be.visible').and('contain', 'Unread Only');
            cy.get(categoryTestId(categories[0])).should('be.visible').and('contain', categories[0].description);
            cy.get(categoryTestId(categories[1])).should('be.visible').and('contain', categories[1].description);
            cy.get(categoryTestId(categories[3])).should('not.exist');
            cy.get('[data-testid="thread-status-comment"]').should('be.visible').and('contain', 'Comment');
        });

        it('handles an empty categories array', () => {
            cy.mount(ForumFilterBar, { props: { categories: [] } });
            cy.get('[data-testid="forum-filter-bar"]').should('be.visible');
            cy.get('[data-testid="thread-category-filter"]').children().should('have.length', 0);
        });

        it('exposes accessible titles on all interactive elements', () => {
            cy.mount(ForumFilterBar, { props: { categories } });
            cy.get('[data-testid="filter-unread-label"]').should('have.attr', 'title', 'Toggle unread filter');
            cy.get(categoryTestId(categories[0])).should('have.attr', 'title', `Filter by ${categories[0].description}`);
            cy.get('[data-testid="thread-status-comment"]').should('have.attr', 'title', 'Filter by comment status');
            cy.get('[data-testid="thread-status-unresolved"]').should('have.attr', 'title', 'Filter by unresolved status');
            cy.get('[data-testid="thread-status-resolved"]').should('have.attr', 'title', 'Filter by resolved status');
        });
    });

    describe('category filter toggling', () => {
        it('toggles a category on and off, emitting filter-change each time', () => {
            mountWithEmitSpy(ForumFilterBar, 'filter-change', { categories }, 'filterHandler');

            cy.get(categoryTestId(categories[0])).should('have.class', 'filter-inactive').and('have.attr', 'data-btn-selected', 'false');
            cy.get(categoryTestId(categories[0])).click();
            cy.get(categoryTestId(categories[0])).should('have.class', 'filter-active').and('have.attr', 'data-btn-selected', 'true');
            cy.get('@filterHandler').should('have.been.calledWith', { categories: [categories[0].id], statuses: [], unread: false });

            cy.get(categoryTestId(categories[0])).click();
            cy.get(categoryTestId(categories[0])).should('have.class', 'filter-inactive').and('have.attr', 'data-btn-selected', 'false');
            cy.get('@filterHandler').should('have.been.calledWith', { categories: [], statuses: [], unread: false });
        });
    });

    describe('status filter toggling', () => {
        it('toggles a status on and off, emitting filter-change each time', () => {
            mountWithEmitSpy(ForumFilterBar, 'filter-change', { categories }, 'filterHandler');

            cy.get('[data-testid="thread-status-unresolved"]').should('have.class', 'filter-inactive');
            cy.get('[data-testid="thread-status-unresolved"]').click();
            cy.get('[data-testid="thread-status-unresolved"]').should('have.class', 'filter-active');
            cy.get('@filterHandler').should('have.been.calledWith', { categories: [], statuses: [-1], unread: false });

            cy.get('[data-testid="thread-status-unresolved"]').click();
            cy.get('[data-testid="thread-status-unresolved"]').should('have.class', 'filter-inactive');
            cy.get('@filterHandler').should('have.been.calledWith', { categories: [], statuses: [], unread: false });
        });

        it('allows multiple statuses selected simultaneously', () => {
            cy.mount(ForumFilterBar, { props: { categories } });
            cy.get('[data-testid="thread-status-comment"]').click();
            cy.get('[data-testid="thread-status-resolved"]').click();
            cy.get('[data-testid="thread-status-comment"]').should('have.class', 'filter-active');
            cy.get('[data-testid="thread-status-resolved"]').should('have.class', 'filter-active');
        });
    });

    describe('unread filter', () => {
        it('toggles unread on and off, emitting filter-change each time', () => {
            mountWithEmitSpy(ForumFilterBar, 'filter-change', { categories }, 'filterHandler');

            cy.get('[data-testid="filter-unread-label"]').should('have.class', 'filter-inactive');
            cy.get('[data-testid="filter-unread-checkbox"]').should('not.be.checked');
            cy.get('[data-testid="filter-unread-label"]').click();
            cy.get('[data-testid="filter-unread-label"]').should('have.class', 'filter-active');
            cy.get('[data-testid="filter-unread-checkbox"]').should('be.checked');
            cy.get('@filterHandler').should('have.been.calledWith', { categories: [], statuses: [], unread: true });

            cy.get('[data-testid="filter-unread-label"]').click();
            cy.get('[data-testid="filter-unread-label"]').should('have.class', 'filter-inactive');
            cy.get('[data-testid="filter-unread-checkbox"]').should('not.be.checked');
            cy.get('@filterHandler').should('have.been.calledWith', { categories: [], statuses: [], unread: false });
        });
    });

    describe('initial state from props', () => {
        it('applies initial selected categories from props', () => {
            cy.mount(ForumFilterBar, {
                props: {
                    categories,
                    initialSelectedCategoryIds: [categories[0].id, categories[1].id],
                },
            });
            cy.get(categoryTestId(categories[0])).should('have.class', 'filter-active').and('have.attr', 'data-btn-selected', 'true');
            cy.get(categoryTestId(categories[1])).should('have.class', 'filter-active').and('have.attr', 'data-btn-selected', 'true');
            cy.get(categoryTestId(categories[2])).should('have.class', 'filter-inactive').and('have.attr', 'data-btn-selected', 'false');
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

        it('coerces string seed ids to numbers', () => {
            cy.mount(ForumFilterBar, {
                props: {
                    categories,
                    initialSelectedCategoryIds: [String(categories[0].id), String(categories[1].id)],
                    initialSelectedThreadStatuses: ['0'],
                },
            });
            cy.get(categoryTestId(categories[0])).should('have.class', 'filter-active');
            cy.get(categoryTestId(categories[1])).should('have.class', 'filter-active');
            cy.get('[data-testid="thread-status-comment"]').should('have.class', 'filter-active');
        });
    });

    describe('composite payload', () => {
        it('emits a single filter-change reflecting all active filters together', () => {
            mountWithEmitSpy(ForumFilterBar, 'filter-change', { categories }, 'filterHandler');

            cy.get(categoryTestId(categories[0])).click();
            cy.get('[data-testid="thread-status-comment"]').click();
            cy.get('[data-testid="filter-unread-label"]').click();

            cy.get('@filterHandler').should('have.been.calledWith', {
                categories: [categories[0].id],
                statuses: [0],
                unread: true,
            });
        });
    });

    describe('edge cases', () => {
        it('renders a category with a visible date but no diff field', () => {
            cy.mount(ForumFilterBar, {
                props: {
                    categories: [
                        { id: 1, description: 'Exam Review', visibleDate: '2026-06-10T00:00:00Z' },
                    ],
                },
            });
            cy.get(categoryTestId({ id: 1 })).should('be.visible').and('contain', 'Exam Review');
        });

        it('renders a category with a null visibleDate even with a negative diff', () => {
            cy.mount(ForumFilterBar, {
                props: {
                    categories: [
                        { id: 1, description: 'Announcements', visibleDate: null, diff: -10 },
                    ],
                },
            });
            cy.get(categoryTestId({ id: 1 })).should('be.visible').and('contain', 'Announcements');
        });
    });
});
