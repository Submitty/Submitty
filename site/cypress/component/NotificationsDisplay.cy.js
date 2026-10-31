import NotificationsDisplay from '../../vue/src/components/NotificationsDisplay.vue';
import { mountWithEmitSpy } from '../support/component_test_utils';

function getSampleNotifications() {
    return [
        {
            id: 1,
            component: 'forum',
            metadata: '',
            content: 'New announcement posted in Forum',
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
            content: 'Homework 1 has been graded',
            seen: false,
            elapsed_time: 200,
            created_at: '2026-09-28 11:00:00',
            notify_time: '30 mins ago',
            term: 'f26',
            course: 'sample',
            course_name: 'Sample Course',
            url: 'http://localhost/sample/gradeable',
        },
    ];
}

describe('NotificationsDisplay', () => {
    beforeEach(() => {
        cy.window().then((win) => {
            win.localStorage.removeItem('notification-preference');
            win.csrfToken = 'test_csrf_token';
            if (win.$) {
                win.$.ajax = (options) => {
                    if (options.success) {
                        options.success({ status: 'success' });
                    }
                };
            }
        });
    });

    it('emits badge-count-change on mount, on individual seen, and on mark-all-seen', () => {
        mountWithEmitSpy(NotificationsDisplay, 'badge-count-change', {
            notifications: getSampleNotifications(),
            unseenCount: 2,
            course: true,
        }, 'badgeCountSpy');

        // Emitted on mount with initial unseen count
        cy.get('@badgeCountSpy').should('have.been.calledWith', 2);
        cy.get('[data-testid="notification-seen-button"]').should('have.length', 2);

        // Click mark seen on first individual notification
        cy.get('[data-testid="notification-seen-button"]').first().click();
        cy.get('@badgeCountSpy').should('have.been.calledWith', 1);
        cy.get('[data-testid="notification-seen-button"]').should('have.length', 1);

        // Click Mark as seen button to clear all
        cy.get('[data-testid="mark-seen-button"]').click();
        cy.get('@badgeCountSpy').should('have.been.calledWith', 0);
        cy.get('[data-testid="notification-seen-button"]').should('have.length', 0);
        cy.get('[data-testid="no-unseen-notifications-message"]').should('exist');
    });

    it('retains unseen status and does not clear count when mark-seen request fails', () => {
        cy.window().then((win) => {
            win.$.ajax = (options) => {
                if (options.error) {
                    options.error(new Error('Network error'));
                }
            };
        });

        mountWithEmitSpy(NotificationsDisplay, 'badge-count-change', {
            notifications: getSampleNotifications(),
            unseenCount: 2,
            course: true,
        }, 'badgeCountSpy');

        cy.get('[data-testid="notification-seen-button"]').should('have.length', 2);
        cy.get('[data-testid="mark-seen-button"]').click();

        // Items should remain unseen and 0 should NOT be emitted
        cy.get('[data-testid="notification-seen-button"]').should('have.length', 2);
        cy.get('@badgeCountSpy').should('not.have.been.calledWith', 0);
    });

    it('derives unseen count when unseenCount prop is negative', () => {
        mountWithEmitSpy(NotificationsDisplay, 'badge-count-change', {
            notifications: getSampleNotifications(),
            unseenCount: -1,
            course: true,
        }, 'badgeCountSpy');

        // Derives 2 from notifications.filter(!seen).length
        cy.get('@badgeCountSpy').should('have.been.calledWith', 2);
        cy.get('[data-testid="notification-seen-button"]').should('have.length', 2);
    });

    it('toggles between Show All and Show Unseen Only and saves preference to localStorage', () => {
        const notifications = [
            { ...getSampleNotifications()[0], seen: true },
            { ...getSampleNotifications()[1], seen: false },
        ];

        mountWithEmitSpy(NotificationsDisplay, 'badge-count-change', {
            notifications,
            unseenCount: 1,
            course: true,
        });

        // Default: showing unseen notifications only
        cy.get('[data-testid="notification-text"]').should('have.length', 1);
        cy.get('[data-testid="toggle-unseen-button"]').should('contain.text', 'Show All').click();

        // Now showing all notifications, button changes to Show Unseen Only
        cy.get('[data-testid="notification-text"]').should('have.length', 2);
        cy.get('[data-testid="toggle-unseen-button"]').should('contain.text', 'Show Unseen Only');
        cy.window().then((win) => {
            expect(win.localStorage.getItem('notification-preference')).to.equal('all');
        });

        // Toggle back to unseen only
        cy.get('[data-testid="toggle-unseen-button"]').click();
        cy.get('[data-testid="notification-text"]').should('have.length', 1);
        cy.window().then((win) => {
            expect(win.localStorage.getItem('notification-preference')).to.equal('unseen');
        });
    });

    it('respects initial notification preference in localStorage on mount', () => {
        cy.window().then((win) => {
            win.localStorage.setItem('notification-preference', 'all');
        });

        mountWithEmitSpy(NotificationsDisplay, 'badge-count-change', {
            notifications: getSampleNotifications(),
            unseenCount: 2,
            course: true,
        });

        cy.get('[data-testid="toggle-unseen-button"]').should('contain.text', 'Show Unseen Only');
    });

    it('renders settings link on course page and does not emit badge-count-change on mount when course is false', () => {
        document.body.dataset.courseUrl = 'http://localhost/sample';
        mountWithEmitSpy(NotificationsDisplay, 'badge-count-change', {
            notifications: getSampleNotifications(),
            unseenCount: 2,
            course: true,
        });

        cy.get('[data-testid="notification-settings-button"]')
            .should('exist')
            .and('have.attr', 'href', 'http://localhost/sample/notifications/settings');

        // Mount on home page (course: false)
        mountWithEmitSpy(NotificationsDisplay, 'badge-count-change', {
            notifications: getSampleNotifications(),
            unseenCount: 2,
            course: false,
        }, 'homeBadgeSpy');

        cy.get('@homeBadgeSpy').should('not.have.been.called');
        cy.get('[data-testid="notification-settings-button"]').should('not.exist');
    });

    it('renders empty message, older unseen message, and additional unseen count message', () => {
        // Empty state
        mountWithEmitSpy(NotificationsDisplay, 'badge-count-change', {
            notifications: [],
            unseenCount: 0,
            course: true,
        });
        cy.get('[data-testid="no-notifications-message"]').should('contain.text', 'No notifications to view.');
        cy.get('[data-testid="mark-seen-button"]').should('not.exist');

        // Older unseen message
        const allSeen = getSampleNotifications().map((n) => ({ ...n, seen: true }));
        mountWithEmitSpy(NotificationsDisplay, 'badge-count-change', {
            notifications: allSeen,
            unseenCount: 3,
            course: true,
        });
        cy.get('[data-testid="older-unseen-message"]')
            .should('exist')
            .and('contain.text', '3 older unseen notifications');

        // Additional unseen message when unseen items exceed 10
        const manyNotifications = [];
        for (let i = 1; i <= 12; i++) {
            manyNotifications.push({
                ...getSampleNotifications()[0],
                id: i,
                content: `Notification ${i}`,
            });
        }
        mountWithEmitSpy(NotificationsDisplay, 'badge-count-change', {
            notifications: manyNotifications,
            unseenCount: 15,
            course: false,
        });
        cy.get('[data-testid="additional-unseen-message"]').should('exist').and('contain.text', 'additional unseen notifications');
    });

    it('handles markAllSeen on home page via MarkSeenPopup and emits badge-count-change', () => {
        document.body.dataset.baseUrl = '/';
        const notifications = [
            ...getSampleNotifications(),
            {
                ...getSampleNotifications()[0],
                id: 3,
                term: 's26',
                course: 'other_course',
                seen: false,
            },
        ];

        cy.window().then((win) => {
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
                            ],
                        });
                    }
                    else if (options.url.includes('mark_all_seen') && options.success) {
                        options.success({ status: 'success' });
                    }
                };
            }
        });

        mountWithEmitSpy(NotificationsDisplay, 'badge-count-change', {
            notifications,
            unseenCount: 3,
            course: false,
        }, 'badgeCountSpy');

        // Button should exist for Mark as seen (triggering popup on home page)
        cy.get('[data-testid="mark-seen-trigger-button"]').click();
        cy.get('[data-testid="popup-window"]').should('be.visible');

        // Select all and save -> triggers mark-all event -> calls markAllSeen
        cy.get('[data-testid="select-all-button"]').click();
        cy.get('[data-testid="popup-save-button"]').click();

        // 2 sample notifications marked seen, 1 other_course remains unseen -> emits 1
        cy.get('@badgeCountSpy').should('have.been.calledWith', 1);
    });
});
