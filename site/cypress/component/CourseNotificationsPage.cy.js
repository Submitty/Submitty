import CourseNotificationsPage from '../../vue/src/pages/CourseNotificationsPage.vue';
import { mountWithEmitSpy } from '../support/component_test_utils';

const mockNotifications = [
    {
        id: 1,
        component: 'forum',
        metadata: '',
        content: 'Test notification 1',
        seen: false,
        elapsed_time: 100,
        created_at: '2026-09-28 10:00:00',
        notify_time: '1 hour ago',
        term: 'f26',
        course: 'sample',
        course_name: 'Sample Course',
        url: 'http://localhost/sample/forum',
    },
    {
        id: 2,
        component: 'grading',
        metadata: '',
        content: 'Test notification 2',
        seen: false,
        elapsed_time: 200,
        created_at: '2026-09-28 11:00:00',
        notify_time: '30 mins ago',
        term: 'f26',
        course: 'sample',
        course_name: 'Sample Course',
        url: 'http://localhost/sample/gradeable',
    },
    {
        id: 3,
        component: 'team',
        metadata: '',
        content: 'Test notification 3',
        seen: true,
        elapsed_time: 300,
        created_at: '2026-09-28 12:00:00',
        notify_time: '10 mins ago',
        term: 'f26',
        course: 'sample',
        course_name: 'Sample Course',
        url: 'http://localhost/sample/team',
    },
];

describe('CourseNotificationsPage', () => {
    it('mounts and passes computed unseen count to NotificationsDisplay and forwards update-badge-dom', () => {
        mountWithEmitSpy(CourseNotificationsPage, 'update-badge-dom', {
            notifications: mockNotifications,
        }, 'badgeSpy');

        cy.get('[data-testid="course-notifications-page"]').should('exist');
        cy.get('[data-testid="notifications-header"]').should('contain.text', 'Notifications');
        cy.get('[data-testid="notification-text"]').should('have.length', 2);
        cy.get('@badgeSpy').should('have.been.calledWith', 2);
    });

    it('computes unseen count as 0 and emits update-badge-dom with 0 when all notifications are seen', () => {
        const allSeenNotifications = mockNotifications.map((n) => ({ ...n, seen: true }));

        mountWithEmitSpy(CourseNotificationsPage, 'update-badge-dom', {
            notifications: allSeenNotifications,
        }, 'badgeSpy');

        cy.get('[data-testid="course-notifications-page"]').should('exist');
        cy.get('[data-testid="no-unseen-notifications-message"]').should('exist');
        cy.get('@badgeSpy').should('have.been.calledWith', 0);
    });
});
