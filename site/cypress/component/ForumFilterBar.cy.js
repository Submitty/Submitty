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
            cy.get('button[data-testid="thread-category-1"]').should('be.visible').and('contain', 'General Questions');
            cy.get('button[data-testid="thread-category-4"]').should('not.exist');
            cy.get('[data-testid="thread-status-comment"]').should('be.visible').and('contain', 'Comment');
        });

        it('handles empty categories array', () => {
            cy.mount(ForumFilterBar, { props: { categories: [] } });
            cy.get('[data-testid="forum-filter-bar"]').should('be.visible');
            cy.get('button[data-testid^="thread-category-"]').should('not.exist');
        });
    });

    describe('category filter toggling', () => {
        beforeEach(() => {
            cy.mount(ForumFilterBar, { props: { categories } });
        });

        it('toggles category button active class and data attribute', () => {
            cy.get('button[data-testid="thread-category-1"]').should('have.class', 'filter-inactive').and('have.attr', 'data-btn-selected', 'false');
            cy.get('button[data-testid="thread-category-1"]').trigger('mousedown');
            cy.get('button[data-testid="thread-category-1"]').should('have.class', 'filter-active').and('have.attr', 'data-btn-selected', 'true');
            cy.get('button[data-testid="thread-category-1"]').trigger('mousedown');
            cy.get('button[data-testid="thread-category-1"]').should('have.class', 'filter-inactive').and('have.attr', 'data-btn-selected', 'false');
        });
    });

    describe('status filter toggling', () => {
        beforeEach(() => {
            cy.mount(ForumFilterBar, { props: { categories } });
        });

        it('toggles a single status button', () => {
            cy.get('[data-testid="thread-status-unresolved"]').should('have.class', 'filter-inactive');
            cy.get('[data-testid="thread-status-unresolved"]').trigger('mousedown');
            cy.get('[data-testid="thread-status-unresolved"]').should('have.class', 'filter-active');
            cy.get('[data-testid="thread-status-unresolved"]').trigger('mousedown');
            cy.get('[data-testid="thread-status-unresolved"]').should('have.class', 'filter-inactive');
        });

        it('supports multiple statuses selected simultaneously', () => {
            cy.get('[data-testid="thread-status-comment"]').trigger('mousedown');
            cy.get('[data-testid="thread-status-resolved"]').trigger('mousedown');
            cy.get('[data-testid="thread-status-comment"]').should('have.class', 'filter-active');
            cy.get('[data-testid="thread-status-resolved"]').should('have.class', 'filter-active');
        });
    });

    describe('unread filter', () => {
        beforeEach(() => {
            cy.mount(ForumFilterBar, { props: { categories } });
        });

        it('toggles button class and hidden checkbox', () => {
            cy.get('[data-testid="filter-unread-label"]').should('have.class', 'filter-inactive');
            cy.get('[data-testid="filter-unread-checkbox"]').should('not.be.checked');
            cy.get('[data-testid="filter-unread-label"]').click();
            cy.get('[data-testid="filter-unread-label"]').should('have.class', 'filter-active');
            cy.get('[data-testid="filter-unread-checkbox"]').should('be.checked');
        });

        it('shows clear button when unread is active', () => {
            let clearBtn;
            cy.document().then((doc) => {
                clearBtn = doc.createElement('div');
                clearBtn.id = 'clear_filter_button';
                doc.body.appendChild(clearBtn);
            });
            cy.get('[data-testid="filter-unread-label"]').click();
            cy.document().then((doc) => {
                expect(doc.getElementById('clear_filter_button').style.visibility).to.equal('visible');
                clearBtn.remove();
            });
        });
    });

    describe('clearForumFilter', () => {
        it('resets all filter state, classes, and DOM', () => {
            let searchInput;
            cy.document().then((doc) => {
                searchInput = doc.createElement('input');
                searchInput.id = 'search-content';
                searchInput.value = 'test query';
                doc.body.appendChild(searchInput);
            });
            cy.mount(ForumFilterBar, { props: { categories } });

            cy.get('button[data-testid="thread-category-1"]').trigger('mousedown');
            cy.get('[data-testid="thread-status-comment"]').trigger('mousedown');
            cy.get('[data-testid="filter-unread-label"]').click();

            cy.wrap(null).then(() => {
                window.clearForumFilter();
                expect(window.selectedCategoryIds.value).to.deep.equal([]);
                expect(window.selectedThreadStatuses.value).to.deep.equal([]);
                expect(window.selectedUnreadChecked.value).to.equal(false);
            });
            cy.document().then((doc) => {
                expect(doc.getElementById('search-content').value).to.equal('');
                searchInput.remove();
            });
            cy.get('button[data-testid="thread-category-1"]').should('have.class', 'filter-inactive').and('have.attr', 'data-btn-selected', 'false');
            cy.get('[data-testid="thread-status-comment"]').should('have.class', 'filter-inactive');
            cy.get('[data-testid="filter-unread-label"]').should('have.class', 'filter-inactive');
            cy.get('[data-testid="filter-unread-checkbox"]').should('not.be.checked');
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
            cy.get('button[data-testid="thread-category-1"]').should('be.visible').and('contain', 'Exam Review');
        });

        it('handles category with null visibleDate even with negative diff', () => {
            cy.mount(ForumFilterBar, {
                props: {
                    categories: [
                        { id: 1, description: 'Announcements', visibleDate: null, diff: -10 },
                    ],
                },
            });
            cy.get('button[data-testid="thread-category-1"]').should('be.visible').and('contain', 'Announcements');
        });
    });
});
