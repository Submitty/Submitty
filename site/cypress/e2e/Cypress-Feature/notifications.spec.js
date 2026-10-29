import { buildUrl } from '../../support/utils';

const verifyUpdateMessage = (exists = true) => {
    if (exists) {
        cy.get('[data-testid="popup-message"]')
            .should('be.visible')
            .should('contain', 'Notification settings have been saved.')
            .get('[data-testid="remove-message-popup"]')
            .first()
            .click();
    }
    else {
        cy.get('[data-testid="popup-message"]').should('not.exist');
    }
};

const verifyIndividualNotificationUpdates = (name, reload = false, state = {}) => {
    if (Object.keys(state).length === 0) {
        // Initialize the state of all checkboxes before performing the initial action
        cy.get('input[data-testid="checkbox-input"]').each(($el) => {
            const inputName = $el.attr('name');
            state[inputName] = $el.prop('checked');
        });
    }

    cy.get(`input[data-testid="checkbox-input"][name="${name}"]`).should('exist').then(($el) => {
        const isDisabled = $el.prop('disabled');

        if (!isDisabled) {
            cy.wrap($el).click();
        }

        // Expect a success message only if checkbox is not disabled (i.e., not mandatory)
        verifyUpdateMessage(!isDisabled);
    });

    cy.get('input[data-testid="checkbox-input"]').each(($el) => {
        const inputName = $el.attr('name');
        const isDisabled = $el.prop('disabled');
        const currentChecked = $el.prop('checked');
        const previousChecked = state[inputName];

        if (!isDisabled && name === inputName) {
            // Checkboxes that are not mandatory should have been toggled
            expect(currentChecked).to.not.equal(previousChecked);
            // Persist the most recent state of the checkbox
            state[inputName] = currentChecked;
        }
        else {
            // Other checkboxes should remain unchanged
            expect(currentChecked).to.equal(previousChecked);
        }
    });

    if (!reload) {
        // Verify the updates persist for all actions after a full page reload
        cy.reload().then(() => verifyIndividualNotificationUpdates(name, true, state));
    }
    else if (!state.reversed) {
        // Perform the opposite action at most once, restoring the original state
        state.reversed = true;
        cy.then(() => verifyIndividualNotificationUpdates(name, false, state));
    }
};

const verifyBatchNotificationUpdates = (identifier, message, type, action, reload = false) => {
    if (!reload) {
        // Perform the initial batch update action
        cy.get(`[data-testid="${identifier}"]`).should('be.enabled').click();
        cy.get('[data-testid="notification-settings-button-group"]').should('contain', message);
        verifyUpdateMessage();
    }

    const selectorPrefix = `${type === 'notification' ? '.notification-checkbox' : '.email-checkbox'}`;
    cy.get(`${selectorPrefix} input[data-testid="checkbox-input"]`)
        .should('have.length.greaterThan', 0)
        .each(($el) => {
            switch (action) {
                case 'subscribe':
                    expect($el.prop('checked')).to.be.true;
                    break;
                case 'unsubscribe':
                    expect($el.prop('checked')).to.equal($el.prop('disabled'));
                    break;
                case 'reset':
                    expect($el.prop('checked')).to.equal($el.attr('data-default-checked') === 'true');
                    break;
                default:
                    throw new Error(`Invalid action: ${action}`);
            }
        });

    if (!reload) {
        // Verify the updates persist for all actions after a full page reload
        cy.reload().then(() => verifyBatchNotificationUpdates(identifier, message, type, action, true));
    }
};

const toggleSelfRegistration = (enable) => {
    cy.login('instructor');
    cy.visit(['sample', 'config']);

    if (enable) {
        cy.get('[data-testid="default-section-id"]').select('1');
        cy.get('[data-testid="all-self-registration"]').check();
    }
    else {
        cy.get('[data-testid="all-self-registration"]').uncheck();
    }

    cy.get('[data-testid="all-self-registration"]').should(enable ? 'be.checked' : 'not.be.checked');
    cy.logout();
};

describe('Test cases revolving around notification/email settings', () => {
    before(() => {
        // Additional notification settings are displayed for self registration courses
        toggleSelfRegistration(true);
    });

    beforeEach(() => {
        cy.login('student');
        cy.visit(buildUrl(['sample', 'notifications', 'settings']));
    });

    after(() => {
        // Reset the self registration setting to its original state for the sample course
        toggleSelfRegistration(false);
    });

    it('Should allow the user to access the notification settings page from the landing course page', () => {
        cy.visit(buildUrl(['sample']));
        cy.get('[data-testid="sidebar"]')
            .contains('Notifications')
            .click();
        cy.get('[data-testid="notification-settings-button"]').click();
        cy.get('[data-testid="notification-settings-header"]').should('contain', 'Notification/Email Settings');
    });

    it('Should allow the user to subscribe, unsubscribe, and reset notification/email settings', () => {
        verifyBatchNotificationUpdates('subscribe-all-notifications', 'Subscribe to all notifications', 'notification', 'subscribe');
        verifyBatchNotificationUpdates('unsubscribe-all-optional-notifications', 'Unsubscribe from all optional notifications', 'notification', 'unsubscribe');
        verifyBatchNotificationUpdates('reset-notification-settings', 'Reset notification settings', 'notification', 'reset');

        verifyBatchNotificationUpdates('subscribe-all-emails', 'Subscribe to all emails', 'email', 'subscribe');
        verifyBatchNotificationUpdates('unsubscribe-all-optional-emails', 'Unsubscribe from all optional emails', 'email', 'unsubscribe');
        verifyBatchNotificationUpdates('reset-email-settings', 'Reset email settings', 'email', 'reset');
    });

    it('Should allow the user to subscribe and unsubscribe to individual notification/email inputs', () => {
        cy.get('input[data-testid="checkbox-input"]')
            .each(($el) => verifyIndividualNotificationUpdates($el.attr('name')));
    });
});

describe('Test cases revolving around course notifications and sidebar badge count', () => {
    const threadAnnouncement1 = 'Cypress Announcement Badge 1';
    const threadAnnouncement2 = 'Cypress Announcement Badge 2';
    const threadContent = 'Cypress notification test body';

    const createAnnouncement = (title) => {
        cy.visit(['sample', 'forum', 'threads', 'new']);
        cy.get('[data-testid="title"]').type(title);
        cy.get('[data-testid="reply_box_1"]').type(threadContent);
        cy.get('[data-testid="categories-pick-list"] .cat-buttons').first().click({ force: true });
        cy.get('#Announcement').click();
        cy.get('[data-testid="forum-publish-thread"]').click({ force: true });
        cy.url().should('not.include', '/sample/forum/threads/new');
    };

    const cleanupThread = (title) => {
        cy.visit(['sample', 'forum', 'threads']).then(() => {
            cy.get('body').then(($body) => {
                if ($body.find(`[data-testid="thread-list-item"]:contains("${title}")`).length > 0) {
                    cy.get('[data-testid="thread-list-item"]').contains(title).click();
                    cy.get('[data-testid="thread-dropdown"]').first().click();
                    cy.get('[data-testid="delete-post-button"]').first().click({ force: true });
                }
            });
        });
    };

    before(() => {
        // Ensure student starts with clean state (all existing notifications marked seen)
        cy.login('student');
        cy.visit(buildUrl(['sample', 'notifications']));
        cy.get('body').then(($body) => {
            if ($body.find('[data-testid="mark-seen-button"]').length > 0) {
                cy.get('[data-testid="mark-seen-button"]').click();
            }
        });
        cy.logout();

        // Create two announcements as instructor to trigger unseen notifications
        cy.login('instructor');
        createAnnouncement(threadAnnouncement1);
        createAnnouncement(threadAnnouncement2);
        cy.logout();
    });

    after(() => {
        // Clean up both announcement threads
        cy.login('instructor');
        cleanupThread(threadAnnouncement1);
        cleanupThread(threadAnnouncement2);
        cy.logout();
    });

    it('Should display sidebar badge count, update badge on individual mark seen, and remove badge on mark all seen', () => {
        cy.login('student');
        cy.visit(buildUrl(['sample']));

        // Verify sidebar notification badge exists on the course page
        cy.get('[data-testid="sidebar-notification-badge"]')
            .should('exist')
            .and('contain.text', '2');

        // Navigate to course notifications page
        cy.get('[data-testid="nav-sidebar-notifications"]').click();
        cy.get('[data-testid="course-notifications-page"]').should('exist');
        cy.get('[data-testid="sidebar-notification-badge"]')
            .should('exist')
            .and('contain.text', '2');

        // Mark first individual notification as seen and verify badge count decrements dynamically
        cy.get('[data-testid="notification-seen-button"]').first().click();
        cy.get('[data-testid="sidebar-notification-badge"]')
            .should('exist')
            .and('contain.text', '1');

        // Mark remaining notification(s) as seen via mark-seen-button and verify badge is removed dynamically
        cy.get('[data-testid="mark-seen-button"]').click();
        cy.get('[data-testid="sidebar-notification-badge"]').should('not.exist');
        cy.get('[data-testid="no-unseen-notifications-message"]').should('exist');

        // Reload the page and verify badge remains removed
        cy.reload();
        cy.get('[data-testid="sidebar-notification-badge"]').should('not.exist');
        cy.get('[data-testid="no-unseen-notifications-message"]').should('exist');
    });
});
