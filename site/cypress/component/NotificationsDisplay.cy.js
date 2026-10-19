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

function updateBadgeDOM(count) {
    const badges = document.querySelectorAll(
        '#nav-sidebar-notifications .notification-badge, #mobile-nav-sidebar-notifications .notification-badge, #menu-button .notification-badge',
    );
    if (count <= 0) {
        badges.forEach((el) => el.remove());
    }
    else {
        badges.forEach((el) => {
            el.textContent = `${count}`;
        });
    }
}

function mountNotificationsDisplay(props = {}, extra = {}) {
    const { onUpdateBadgeDom = updateBadgeDOM, ...restProps } = props;
    return cy.mount(NotificationsDisplay, {
        props: {
            onUpdateBadgeDom,
            ...restProps,
        },
        ...extra,
    });
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

    it('emits update-badge-dom on mount, on individual seen, and on markSeen', () => {
        const badgeSpy = cy.stub().as('badgeSpy');

        mountNotificationsDisplay({
            notifications: getSampleNotifications(),
            unseenCount: 2,
            course: true,
            onUpdateBadgeDom: (count) => {
                badgeSpy(count);
                updateBadgeDOM(count);
            },
        });

        // Called on mount with initial unseen count
        cy.get('@badgeSpy').should('have.been.calledWith', 2);

        // Click mark seen on first individual notification
        cy.get('.notification-seen').first().click();
        cy.get('@badgeSpy').should('have.been.calledWith', 1);
        cy.get('#nav-sidebar-notifications .notification-badge').should('have.text', '1');

        // Click Mark as seen button
        cy.contains('button', 'Mark as seen').click();
        cy.get('@badgeSpy').should('have.been.calledWith', 0);
        cy.get('#nav-sidebar-notifications .notification-badge').should('not.exist');
    });

    it('removes sidebar badge and marks notifications as seen when markSeen request succeeds', () => {
        mountNotificationsDisplay({
            notifications: getSampleNotifications(),
            unseenCount: 2,
            course: true,
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
        mountNotificationsDisplay({
            notifications: getSampleNotifications(),
            unseenCount: 2,
            course: true,
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
        mountNotificationsDisplay({
            notifications: getSampleNotifications(),
            unseenCount: 2,
            course: true,
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
        mountNotificationsDisplay({
            notifications,
            unseenCount: -1,
            course: true,
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

        mountNotificationsDisplay({
            notifications: updatedNotifications,
            unseenCount: -1,
            course: true,
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

        mountNotificationsDisplay({
            notifications: allSeenNotifications,
            unseenCount: 0,
            course: true,
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

        mountNotificationsDisplay({
            notifications,
            unseenCount: 1,
            course: true,
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

    it('respects initial unseen preference in localStorage on mount', () => {
        cy.window().then((win) => {
            win.localStorage.setItem('notification-preference', 'unseen');
        });

        mountNotificationsDisplay({
            notifications: getSampleNotifications(),
            unseenCount: 2,
            course: true,
        });

        cy.contains('button', 'Show All').should('exist');
    });

    it('respects initial all preference in localStorage on mount', () => {
        cy.window().then((win) => {
            win.localStorage.setItem('notification-preference', 'all');
        });

        mountNotificationsDisplay({
            notifications: getSampleNotifications(),
            unseenCount: 2,
            course: true,
        });

        // Show Unseen Only button should be visible because preference was all
        cy.contains('button', 'Show Unseen Only').should('exist');
    });

    it('renders settings link on course page with correct URL', () => {
        document.body.dataset.courseUrl = 'http://localhost/sample';
        mountNotificationsDisplay({
            notifications: getSampleNotifications(),
            unseenCount: 2,
            course: true,
        });

        cy.get('[data-testid="notification-settings-button"]')
            .should('exist')
            .and('be.visible')
            .and('have.attr', 'href', 'http://localhost/sample/notifications/settings');
    });

    it('uses fallback getCsrfToken when window.csrfToken is undefined', () => {
        document.body.dataset.courseUrl = 'http://localhost/sample';
        document.body.dataset.csrfToken = 'fallback_csrf';
        let capturedCsrf;
        cy.window().then((win) => {
            delete win.csrfToken;
            win.$.ajax = (options) => {
                capturedCsrf = options.data.csrf_token;
                if (options.success) {
                    options.success({ status: 'success' });
                }
            };
        });

        mountNotificationsDisplay({
            notifications: getSampleNotifications(),
            unseenCount: 2,
            course: true,
        });

        cy.contains('button', 'Mark as seen').click();
        cy.then(() => {
            expect(capturedCsrf).to.equal('fallback_csrf');
        });
    });

    it('handles markSeen when some notifications are already seen', () => {
        const notifications = [
            { ...getSampleNotifications()[0], seen: true },
            { ...getSampleNotifications()[1], seen: false },
        ];

        mountNotificationsDisplay({
            notifications,
            unseenCount: 1,
            course: true,
        });

        cy.contains('button', 'Mark as seen').click();
        cy.get('#nav-sidebar-notifications .notification-badge').should('not.exist');
    });

    it('does not emit update-badge-dom on mount when course is false', () => {
        const badgeSpy = cy.stub().as('badgeSpy');
        mountNotificationsDisplay({
            notifications: getSampleNotifications(),
            unseenCount: 2,
            course: false,
            onUpdateBadgeDom: badgeSpy,
        });

        cy.get('@badgeSpy').should('not.have.been.called');
    });

    it('clamps unseen count to 0 if individual seen is triggered when unseenCount is already 0', () => {
        mountNotificationsDisplay({
            notifications: getSampleNotifications(),
            unseenCount: 0,
            course: true,
        });

        cy.get('.notification-seen').first().click();
        cy.get('#nav-sidebar-notifications .notification-badge').should('not.exist');
    });

    it('renders older unseen notification message on course page when all displayed notifications are seen but unseen count > 0', () => {
        const allSeen = getSampleNotifications().map((n) => ({ ...n, seen: true }));
        mountNotificationsDisplay({
            notifications: allSeen,
            unseenCount: 2,
            course: true,
        });

        cy.get('.unseen-count-p').should('exist')
            .and('contain.text', 'You have 2 older unseen notifications in your course notifications not displayed here.');
    });

    it('hides unseen count text on home page when Show All is active', () => {
        mountNotificationsDisplay({
            notifications: getSampleNotifications(),
            unseenCount: 2,
            course: false,
        });

        cy.contains('button', 'Show All').click();
        cy.get('.unseen-count-p').should('not.exist');
    });

    it('renders empty message when no notifications exist', () => {
        mountNotificationsDisplay({
            notifications: [],
            unseenCount: 0,
            course: true,
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

        mountNotificationsDisplay({
            notifications: manyNotifications,
            unseenCount: 15,
            course: false,
        });

        cy.get('.unseen-count-p').should('exist').and('contain.text', 'additional unseen notifications');
    });

    it('renders singular unseen count message when count is exactly 11', () => {
        const elevenNotifications = [];
        for (let i = 1; i <= 11; i++) {
            elevenNotifications.push({
                ...getSampleNotifications()[0],
                id: i,
                content: `Notification ${i}`,
            });
        }

        mountNotificationsDisplay({
            notifications: elevenNotifications,
            unseenCount: 11,
            course: false,
        });

        cy.get('.unseen-count-p').should('exist')
            .and('contain.text', 'You have 1 additional unseen notification.');
    });

    it('renders older unseen notifications message when some unseen notifications are not in visible list', () => {
        const mixedNotifications = [];
        for (let i = 1; i <= 12; i++) {
            mixedNotifications.push({
                ...getSampleNotifications()[0],
                id: i,
                seen: i !== 1,
                content: `Notification ${i}`,
            });
        }

        mountNotificationsDisplay({
            notifications: mixedNotifications,
            unseenCount: 3,
            course: false,
        });

        cy.get('.unseen-count-p').should('exist')
            .and('contain.text', 'You have 2 older unseen notifications in your course notifications not displayed here.');
    });

    it('renders singular older unseen notification message when diff is 1 and unseen count is 1', () => {
        const mixedNotifications = [];
        for (let i = 1; i <= 12; i++) {
            mixedNotifications.push({
                ...getSampleNotifications()[0],
                id: i,
                seen: true,
                content: `Notification ${i}`,
            });
        }

        mountNotificationsDisplay({
            notifications: mixedNotifications,
            unseenCount: 1,
            course: false,
        });

        cy.get('.unseen-count-p').should('exist')
            .and('contain.text', 'You have 1 older unseen notification in your course notifications not displayed here.');
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

        mountNotificationsDisplay({
            notifications: getSampleNotifications(),
            unseenCount: 2,
            course: false,
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

    it('handles markAllSeen with mixed courses where other courses remain unseen', () => {
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

        mountNotificationsDisplay({
            notifications,
            unseenCount: 3,
            course: false,
        });

        cy.contains('button', 'Mark as seen').click();
        cy.contains('a', 'Select All').click();
        cy.get('[data-testid="popup-save-button"]').click();

        // 2 sample notifications marked seen, 1 other_course remains unseen
        cy.get('#nav-sidebar-notifications .notification-badge').should('have.text', '1');
    });
});
