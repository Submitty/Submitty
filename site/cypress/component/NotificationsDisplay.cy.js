import NotificationsDisplay from '../../vue/src/components/NotificationsDisplay.vue';

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
    let sidebarEl;
    let mobileSidebarEl;
    let menuBtnEl;

    beforeEach(() => {
        // Clear local storage notification preference to default
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

        // Clean up previous DOM badge elements if any
        document.querySelectorAll('#nav-sidebar-notifications, #mobile-nav-sidebar-notifications, #menu-button')
            .forEach((el) => el.remove());

        // Setup DOM sidebar badge elements
        sidebarEl = document.createElement('div');
        sidebarEl.id = 'nav-sidebar-notifications';
        sidebarEl.innerHTML = '<span class="notification-badge">2</span>';
        document.body.appendChild(sidebarEl);

        mobileSidebarEl = document.createElement('div');
        mobileSidebarEl.id = 'mobile-nav-sidebar-notifications';
        mobileSidebarEl.innerHTML = '<span class="notification-badge">2</span>';
        document.body.appendChild(mobileSidebarEl);

        menuBtnEl = document.createElement('button');
        menuBtnEl.id = 'menu-button';
        menuBtnEl.innerHTML = '<span class="notification-badge">2</span>';
        document.body.appendChild(menuBtnEl);
    });

    afterEach(() => {
        [sidebarEl, mobileSidebarEl, menuBtnEl].forEach((el) => {
            if (el && el.parentNode) {
                el.remove();
            }
        });
    });

    it('removes sidebar badge and marks notifications as seen when markSeen request succeeds', () => {
        cy.mount(NotificationsDisplay, {
            props: {
                notifications: getSampleNotifications(),
                unseenCount: 2,
                course: true,
            },
        });

        // Initially sidebar badge exists and 2 unseen mark buttons exist
        cy.get('#nav-sidebar-notifications .notification-badge').should('exist').and('have.text', '2');
        cy.get('.notification-seen').should('have.length', 2);

        // Click Mark as seen button
        cy.contains('button', 'Mark as seen').click();

        // Badge should be removed on success and no unseen buttons remain
        cy.get('#nav-sidebar-notifications .notification-badge').should('not.exist');
        cy.get('#mobile-nav-sidebar-notifications .notification-badge').should('not.exist');
        cy.get('#menu-button .notification-badge').should('not.exist');
        cy.get('.notification-seen').should('have.length', 0);
    });

    it('retains sidebar badge and unseen status when markSeen request fails', () => {
        cy.mount(NotificationsDisplay, {
            props: {
                notifications: getSampleNotifications(),
                unseenCount: 2,
                course: true,
            },
        });

        cy.window().then((win) => {
            win.$.ajax = (options) => {
                if (options.error) {
                    options.error(new Error('Network error'));
                }
            };
        });

        // Initially sidebar badge exists and 2 unseen buttons exist
        cy.get('#nav-sidebar-notifications .notification-badge').should('exist').and('have.text', '2');
        cy.get('.notification-seen').should('have.length', 2);

        // Click Mark as seen button
        cy.contains('button', 'Mark as seen').click();

        // Badge should NOT be removed on failure and unseen buttons should remain
        cy.get('#nav-sidebar-notifications .notification-badge').should('exist').and('have.text', '2');
        cy.get('.notification-seen').should('have.length', 2);
    });

    it('updates sidebar badge count on individual notification mark seen success', () => {
        cy.mount(NotificationsDisplay, {
            props: {
                notifications: getSampleNotifications(),
                unseenCount: 2,
                course: true,
            },
        });

        cy.get('#nav-sidebar-notifications .notification-badge').should('have.text', '2');
        cy.get('.notification-seen').should('have.length', 2);

        // Click mark seen on first individual notification
        cy.get('.notification-seen').first().click();

        // Count should decrease to 1 and only 1 unseen button remains
        cy.get('#nav-sidebar-notifications .notification-badge').should('have.text', '1');
        cy.get('#mobile-nav-sidebar-notifications .notification-badge').should('have.text', '1');
        cy.get('.notification-seen').should('have.length', 1);
    });

    it('derives unseen count when unseenCount is -1, updates badge to 1 on individual seen, and removes badge on mark all seen', () => {
        const notifications = getSampleNotifications();

        // Step 1: Mount with unseenCount: -1 (as CourseNotificationsPage passes when not derived)
        cy.mount(NotificationsDisplay, {
            props: {
                notifications,
                unseenCount: -1,
                course: true,
            },
        });

        // The derived count is 2; badge should show 2
        cy.get('#nav-sidebar-notifications .notification-badge').should('have.text', '2');
        cy.get('.notification-seen').should('have.length', 2);

        // Step 2: Mark one notification individually
        cy.get('.notification-seen').first().click();

        // Badge must show 1 (not 0!) and one unseen notification remains
        cy.get('#nav-sidebar-notifications .notification-badge').should('have.text', '1');
        cy.get('.notification-seen').should('have.length', 1);

        // Step 3: Simulate navigating away and returning (mount again with 1 remaining unseen notification)
        const updatedNotifications = [
            { ...notifications[0], seen: true },
            { ...notifications[1], seen: false },
        ];

        cy.mount(NotificationsDisplay, {
            props: {
                notifications: updatedNotifications,
                unseenCount: -1,
                course: true,
            },
        });

        // Returning to course page syncs badge to 1
        cy.get('#nav-sidebar-notifications .notification-badge').should('have.text', '1');
        cy.get('.notification-seen').should('have.length', 1);

        // Step 4: Click Mark as seen to clear all
        cy.contains('button', 'Mark as seen').click();

        // Badge should be completely removed and no unseen items remain
        cy.get('#nav-sidebar-notifications .notification-badge').should('not.exist');
        cy.get('.notification-seen').should('have.length', 0);
    });

    it('removes stale sidebar badge on mount if course page has zero unseen notifications', () => {
        const allSeenNotifications = getSampleNotifications().map((n) => ({ ...n, seen: true }));

        cy.mount(NotificationsDisplay, {
            props: {
                notifications: allSeenNotifications,
                unseenCount: 0,
                course: true,
            },
        });

        // DOM badge should have been cleaned up on mount
        cy.get('#nav-sidebar-notifications .notification-badge').should('not.exist');
        cy.contains('No unseen notifications.').should('exist');
    });

    it('toggles between Show All and Show Unseen Only and respects local storage preference', () => {
        const notifications = [
            { ...getSampleNotifications()[0], seen: true },
            { ...getSampleNotifications()[1], seen: false },
        ];

        cy.mount(NotificationsDisplay, {
            props: {
                notifications,
                unseenCount: 1,
                course: true,
            },
        });

        // Default is Show All button visible, showing unseen notifications (length 1)
        cy.get('.notification-text').should('have.length', 1);
        cy.contains('button', 'Show All').click();

        // Now showing all notifications (length 2), button says Show Unseen Only
        cy.get('.notification-text').should('have.length', 2);
        cy.contains('button', 'Show Unseen Only').should('exist');
        cy.window().then((win) => {
            expect(win.localStorage.getItem('notification-preference')).to.equal('all');
        });

        // Toggle back to unseen only
        cy.contains('button', 'Show Unseen Only').click();
        cy.get('.notification-text').should('have.length', 1);
        cy.window().then((win) => {
            expect(win.localStorage.getItem('notification-preference')).to.equal('unseen');
        });
    });

    it('renders settings button on course page', () => {
        cy.mount(NotificationsDisplay, {
            props: {
                notifications: getSampleNotifications(),
                unseenCount: 2,
                course: true,
            },
        });

        cy.get('[data-testid="notification-settings-button"]').should('exist').and('be.visible');
    });

    it('renders empty message when no notifications exist', () => {
        cy.mount(NotificationsDisplay, {
            props: {
                notifications: [],
                unseenCount: 0,
                course: true,
            },
        });

        cy.get('#no-recent-notifications').should('contain.text', 'No notifications to view.');
        cy.contains('button', 'Mark as seen').should('not.exist');
    });

    it('renders additional unseen notification count message when count exceeds 10', () => {
        const manyNotifications = [];
        for (let i = 1; i <= 12; i++) {
            manyNotifications.push({
                id: i,
                component: 'team',
                metadata: '',
                content: `Notification ${i}`,
                seen: false,
                elapsed_time: i * 10,
                created_at: '2026-09-28 10:00:00',
                notify_time: '1 hour ago',
                term: 'f26',
                course: 'sample',
                course_name: 'Sample Course',
                url: 'http://localhost/sample',
            });
        }

        cy.mount(NotificationsDisplay, {
            props: {
                notifications: manyNotifications,
                unseenCount: 15,
                course: false,
            },
        });

        cy.get('.unseen-count-p').should('exist').and('contain.text', 'additional unseen');
    });

    it('renders MarkSeenPopup on home page and handles markAllSeen callback', () => {
        document.body.dataset.baseUrl = '/';
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

        cy.mount(NotificationsDisplay, {
            props: {
                notifications: getSampleNotifications(),
                unseenCount: 2,
                course: false,
            },
        });

        // Initially badge is 2
        cy.get('#nav-sidebar-notifications .notification-badge').should('have.text', '2');

        // Button should exist for Mark as seen (triggering popup on home page)
        cy.contains('button', 'Mark as seen').click();
        cy.get('[data-testid="popup-window"]').should('be.visible');

        // Select all and save -> triggers mark-all event -> calls markAllSeen
        cy.contains('a', 'Select All').click();
        cy.get('[data-testid="popup-save-button"]').click();

        // Badge should be removed after markAllSeen
        cy.get('#nav-sidebar-notifications .notification-badge').should('not.exist');
    });

    it('respects initial all preference in localStorage on mount', () => {
        cy.window().then((win) => {
            win.localStorage.setItem('notification-preference', 'all');
        });

        cy.mount(NotificationsDisplay, {
            props: {
                notifications: getSampleNotifications(),
                unseenCount: 2,
                course: true,
            },
        });

        // Show Unseen Only button should be visible because preference was all
        cy.contains('button', 'Show Unseen Only').should('exist');
    });
});
