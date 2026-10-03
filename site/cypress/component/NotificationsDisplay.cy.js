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

    beforeEach(() => {
        // Clear local storage notification preference to default
        localStorage.removeItem('notification-preference');

        // Clean up previous sidebar elements if any
        const existing = document.getElementById('nav-sidebar-notifications');
        if (existing) {
            existing.remove();
        }

        // Setup DOM sidebar badge element
        sidebarEl = document.createElement('div');
        sidebarEl.id = 'nav-sidebar-notifications';
        sidebarEl.innerHTML = '<span class="notification-badge">2</span>';
        document.body.appendChild(sidebarEl);
    });

    afterEach(() => {
        if (sidebarEl && sidebarEl.parentNode) {
            sidebarEl.remove();
        }
    });

    it('removes sidebar badge and marks notifications as seen when markSeen request succeeds', () => {
        cy.window().then((win) => {
            win.csrfToken = 'test_csrf_token';
            win.$ = win.jQuery = {
                ajax: (options) => {
                    if (options.success) {
                        options.success({ status: 'success' });
                    }
                },
            };
        });

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
        cy.get('.notification-seen').should('have.length', 0);
    });

    it('retains sidebar badge and unseen status when markSeen request fails', () => {
        cy.window().then((win) => {
            win.csrfToken = 'test_csrf_token';
            win.$ = win.jQuery = {
                ajax: (options) => {
                    if (options.error) {
                        options.error(new Error('Network error'));
                    }
                },
            };
        });

        cy.mount(NotificationsDisplay, {
            props: {
                notifications: getSampleNotifications(),
                unseenCount: 2,
                course: true,
            },
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
        cy.window().then((win) => {
            win.csrfToken = 'test_csrf_token';
            win.$ = win.jQuery = {
                ajax: (options) => {
                    if (options.success) {
                        options.success({ status: 'success' });
                    }
                },
            };
        });

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
        cy.get('.notification-seen').should('have.length', 1);
    });
});
