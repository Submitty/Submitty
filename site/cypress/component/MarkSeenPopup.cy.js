import MarkSeenPopup from '../../vue/src/components/MarkSeenPopup.vue';
import { mountWithEmitSpy } from '../support/component_test_utils';

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
        cy.get('[data-testid="mark-seen-trigger-button"]').click();

        // Popup should be visible with course counts
        cy.get('[data-testid="popup-window"]').should('be.visible');
        cy.get('[data-testid="course-name"]').should('contain.text', 'Sample Course');
        cy.get('[data-testid="course-name"]').should('contain.text', 'Math Course');

        // Select All button selects all checkboxes
        cy.get('[data-testid="select-all-button"]').click();
        cy.get('[data-testid="course-count-checkbox"]').should('be.checked');

        // Clear Selection unchecks all
        cy.get('[data-testid="clear-all-button"]').click();
        cy.get('[data-testid="course-count-checkbox"]').should('not.be.checked');
    });

    it('emits mark-all when courses are selected and saved', () => {
        mountWithEmitSpy(MarkSeenPopup, 'mark-all', {}, 'markAllStub');

        // Open popup
        cy.get('[data-testid="mark-seen-trigger-button"]').click();

        // Select all courses and save
        cy.get('[data-testid="select-all-button"]').click();
        cy.get('[data-testid="popup-save-button"]').click();

        cy.get('@markAllStub').should('have.been.calledWith', {
            courses: [
                { term: 'f26', course: 'sample', count: 2 },
                { term: 'f26', course: 'math', count: 3 },
            ],
        });
    });

    it('closes popup without emitting when no courses are selected and save is clicked', () => {
        mountWithEmitSpy(MarkSeenPopup, 'mark-all', {}, 'markAllStub');

        // Open popup
        cy.get('[data-testid="mark-seen-trigger-button"]').click();

        // Save with none selected
        cy.get('[data-testid="popup-save-button"]').click();

        cy.get('@markAllStub').should('not.have.been.called');
    });
});
