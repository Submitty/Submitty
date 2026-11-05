import SingleNotification from '../../vue/src/components/Notification.vue';
import { mountWithEmitSpy } from '../support/component_test_utils';

describe('Notification', () => {
    beforeEach(() => {
        document.body.dataset.baseUrl = '/';
        document.body.dataset.courseUrl = '/sample';
        cy.window().then((win) => {
            win.csrfToken = 'test_token';
            if (win.$) {
                win.$.ajax = (options) => {
                    if (options.success) {
                        options.success({ status: 'success' });
                    }
                };
            }
        });
    });

    it('renders notification content, icon, and handles mark-seen on course page', () => {
        const notification = {
            id: 1,
            component: 'forum',
            metadata: '',
            content: 'Forum announcement',
            seen: false,
            elapsed_time: 100,
            created_at: '2026-09-28 10:00:00',
            notify_time: '1 hour ago',
            term: 'f26',
            course: 'sample',
            course_name: 'Sample Course',
            url: 'http://localhost/sample/forum',
        };

        mountWithEmitSpy(SingleNotification, 'mark-individual', {
            notification,
            course: true,
        }, 'markStub');

        cy.get('[data-testid="notification-icon-forum"]').should('exist');
        cy.get('[data-testid="notification-text"]').should('contain.text', 'Forum announcement');
        cy.get('[data-testid="notification-seen-button"]').click();

        cy.get('@markStub').should('have.been.calledWith', { id: 1, course: 'sample' });
    });

    it('renders grading and team icons and handles enter key on mark-seen', () => {
        const notification = {
            id: 2,
            component: 'grading',
            metadata: '',
            content: 'Grading notification',
            seen: false,
            elapsed_time: 200,
            created_at: '2026-09-28 11:00:00',
            notify_time: '2 hours ago',
            term: 'f26',
            course: 'sample',
            course_name: 'Sample Course',
            url: 'http://localhost/sample/gradeable',
        };

        mountWithEmitSpy(SingleNotification, 'mark-individual', {
            notification,
            course: true,
        }, 'markStub');

        cy.get('[data-testid="notification-icon-grading"]').should('exist');
        cy.get('[data-testid="notification-seen-button"]').trigger('keydown', { key: 'Enter' });
        cy.get('@markStub').should('have.been.calledWith', { id: 2, course: 'sample' });
    });

    it('handles home page mode with course link and home mark_seen ajax', () => {
        const notification = {
            id: 3,
            component: 'team',
            metadata: '',
            content: 'Team notification',
            seen: false,
            elapsed_time: 300,
            created_at: '2026-09-28 12:00:00',
            notify_time: '3 hours ago',
            term: 'f26',
            course: 'sample',
            course_name: 'Sample Course',
            url: 'http://localhost/sample/team',
        };

        mountWithEmitSpy(SingleNotification, 'mark-individual', {
            notification,
            course: false,
        }, 'markStub');

        cy.get('[data-testid="notification-icon-team"]').should('exist');
        cy.get('[data-testid="course-notification-link"]').should('contain.text', 'Sample Course');

        // Click course link to test goToCourseNotifications
        cy.window().then((win) => {
            cy.stub(win.HTMLFormElement.prototype, 'submit').as('formSubmit');
        });
        cy.get('[data-testid="course-notification-link"]').click();
        cy.get('@formSubmit').should('have.been.called');

        // Click mark seen in home mode
        cy.get('[data-testid="notification-seen-button"]').click();
        cy.get('@markStub').should('have.been.calledWith', { id: 3, course: 'sample' });
    });

    it('handles ajax error on individual mark seen', () => {
        const notification = {
            id: 4,
            component: 'student',
            metadata: '',
            content: 'Error test notification',
            seen: false,
            elapsed_time: 50,
            created_at: '2026-09-28 12:00:00',
            notify_time: 'Just now',
            term: 'f26',
            course: 'sample',
            course_name: 'Sample Course',
            url: 'http://localhost/sample/forum',
        };

        cy.window().then((win) => {
            win.$.ajax = (options) => {
                if (options.error) {
                    options.error(new Error('Course individual mark seen error'));
                }
            };
        });

        mountWithEmitSpy(SingleNotification, 'mark-individual', {
            notification,
            course: true,
        }, 'markStub');

        cy.get('[data-testid="notification-icon-student"]').should('exist');
        cy.get('[data-testid="notification-seen-button"]').click();
        cy.get('@markStub').should('not.have.been.called');
    });
});
