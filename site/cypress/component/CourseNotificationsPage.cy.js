import CourseNotificationsPage from '../../vue/src/pages/CourseNotificationsPage.vue';
import { mountWithEmitSpy } from '../support/component_test_utils';

describe('CourseNotificationsPage', () => {
    it('mounts and passes computed unseen count to NotificationsDisplay', () => {
        const notifications = [
            {
                id: 1,
                component: 'forum',
                metadata: '',
                content: 'Test notification',
                seen: false,
                elapsed_time: 100,
                created_at: '2026-09-28 10:00:00',
                notify_time: '1 hour ago',
                term: 'f26',
                course: 'sample',
                course_name: 'Sample Course',
                url: 'http://localhost/sample/forum',
            },
        ];

        cy.mount(CourseNotificationsPage, {
            props: {
                notifications,
            },
        });

        cy.get('[data-testid="course-notifications-page"]').should('exist');
        cy.get('[data-testid="notifications-header"]').should('contain.text', 'Notifications');
        cy.get('[data-testid="notification-text"]').should('have.length', 1);
    });

    it('forwards update-badge-dom event to parent listener', () => {
        const notifications = [
            {
                id: 1,
                component: 'forum',
                metadata: '',
                content: 'Test notification',
                seen: false,
                elapsed_time: 100,
                created_at: '2026-09-28 10:00:00',
                notify_time: '1 hour ago',
                term: 'f26',
                course: 'sample',
                course_name: 'Sample Course',
                url: 'http://localhost/sample/forum',
            },
        ];

        mountWithEmitSpy(CourseNotificationsPage, 'update-badge-dom', {
            notifications,
        }, 'badgeSpy');

        cy.get('@badgeSpy').should('have.been.calledWith', 1);
    });
});
