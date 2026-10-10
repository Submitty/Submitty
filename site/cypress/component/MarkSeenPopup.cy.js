import MarkSeenPopup from '../../vue/src/components/MarkSeenPopup.vue';

describe('MarkSeenPopup', () => {
    beforeEach(() => {
        document.body.dataset.baseUrl = '/';
        cy.window().then((win) => {
            win.csrfToken = 'test_token';
            if (win.$) {
                win.$.ajax = (options) => {
                    if (options.url.includes('get_unseen_counts') && options.success) {
                        options.success({
                            status: 'success',
                            data: [
                                {
                                    term: 'f26',
                                    title: 'sample',
                                    name: 'Sample Course',
                                    count: 2,
                                },
                                {
                                    term: 'f26',
                                    title: 'math',
                                    name: 'Math Course',
                                    count: 3,
                                },
                            ],
                        });
                    }
                    else if (options.url.includes('mark_all_seen') && options.success) {
                        options.success({ status: 'success' });
                    }
                };
            }
        });
    });

    it('opens popup, loads course unseen counts, and allows selecting/clearing', () => {
        cy.mount(MarkSeenPopup);

        // Click trigger button
        cy.contains('button', 'Mark as seen').click();

        // Popup should be visible with course counts
        cy.get('[data-testid="popup-window"]').should('be.visible');
        cy.contains('Sample Course').should('exist');
        cy.contains('Math Course').should('exist');

        // Select All button selects all checkboxes
        cy.contains('a', 'Select All').click();
        cy.get('.course-count-grid input[type="checkbox"]').should('be.checked');

        // Clear Selection unchecks all
        cy.contains('a', 'Clear Selection').click();
        cy.get('.course-count-grid input[type="checkbox"]').should('not.be.checked');
    });

    it('emits mark-all when courses are selected and saved', () => {
        const onMarkAll = cy.stub().as('markAllStub');

        cy.mount(MarkSeenPopup, {
            props: {
                onMarkAll,
            },
        });

        // Open popup
        cy.contains('button', 'Mark as seen').click();

        // Select all courses and save
        cy.contains('a', 'Select All').click();
        cy.get('[data-testid="popup-save-button"]').click();

        cy.get('@markAllStub').should('have.been.calledWith', {
            courses: [
                { term: 'f26', course: 'sample', count: 2 },
                { term: 'f26', course: 'math', count: 3 },
            ],
        });
    });

    it('closes popup without emitting when no courses are selected and save is clicked', () => {
        const onMarkAll = cy.stub().as('markAllStub');

        cy.mount(MarkSeenPopup, {
            props: {
                onMarkAll,
            },
        });

        // Open popup
        cy.contains('button', 'Mark as seen').click();

        // Save with none selected
        cy.get('[data-testid="popup-save-button"]').click();

        cy.get('@markAllStub').should('not.have.been.called');
    });
});
