import { buildUrl } from '../../support/utils';

const DEFAULT_COURSES = ['sample', 'testing', 'tutorial', 'development', 'blank'];

const verifyNotificationSync = (initialize = true) => {
    DEFAULT_COURSES.forEach((course, index) => {
        if (initialize && index === 0) {
            // Verify sync button shows "Sync Notifications"
            cy.get('[data-testid="sync-notifications-button"]').then(($button) => {
                if ($button.text().trim() === 'Unsync Notifications') {
                    // If sync is already enabled, disable it first
                    cy.wrap($button).click();
                    verifyUpdateMessage(true, 'Notification syncing has been disabled');
                }
            });

            // Ensure only mandatory notifications are subscribed to
            cy.get('[data-testid="unsubscribe-all-optional-notifications"]').click();
            cy.get('[data-testid="unsubscribe-all-optional-emails"]').click();

            // Enable two specific non-disabled notifications for testing
            cy.get('#reply_in_post_thread').should('not.be.checked').check();
            verifyUpdateMessage();

            cy.get('#all_released_grades_email').should('not.be.checked').check();
            verifyUpdateMessage();

            // Enable sync
            cy.get('[data-testid="sync-notifications-button"]').should('contain', 'Sync Notifications').click();
            verifyUpdateMessage(true, 'Notification syncing has been enabled');

            // Verify the button text has been updated
            cy.get('[data-testid="sync-notifications-button"]')
                .should('contain', 'Unsync Notifications');
        }
        else {
            // On subsequent courses, verify sync propagated the settings
            cy.visit(buildUrl([course, 'notifications', 'settings']));

            // Verify sync button shows "Unsync Notifications"
            cy.get('[data-testid="sync-notifications-button"]')
                .should('contain', 'Unsync Notifications');

            // Verify the specific notifications we enabled are checked (sync is enabled)
            cy.get('#reply_in_post_thread').should('be.checked');
            cy.get('#all_released_grades_email').should('be.checked');

            // Ensure all other notifications are unchecked outside of disabled inputs
            cy.get('input[data-testid="checkbox-input"]').each(($checkbox) => {
                const id = $checkbox.attr('id');

                if (id === 'reply_in_post_thread' || id === 'all_released_grades_email' || (!initialize && id === 'team_member_submission')) {
                    cy.wrap($checkbox).should('be.checked');
                }
                else if (!$checkbox.prop('disabled')) {
                    cy.wrap($checkbox).should('not.be.checked');
                }
            });
        }
    });
};

const verifyUpdateMessage = (exists = true, message = 'Notification settings have been saved') => {
    if (exists) {
        cy.get('[data-testid="popup-message"]')
            .should('be.visible')
            .should('contain', message)
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
        cy.get('[data-testid="notification-settings-button-group"]').should('contain', message).click();
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
        return;
        cy.visit(buildUrl(['sample']));
        cy.get('[data-testid="sidebar"]')
            .contains('Notifications')
            .click();
        cy.get('[data-testid="notification-settings-button"]').click();
        cy.get('[data-testid="notification-settings-header"]').should('contain', 'Notification/Email Settings');
    });

    it('Should allow the user to subscribe, unsubscribe, and reset notification/email settings', () => {
        return;
        verifyBatchNotificationUpdates('subscribe-all-notifications', 'Subscribe to all notifications', 'notification', 'subscribe');
        verifyBatchNotificationUpdates('unsubscribe-all-optional-notifications', 'Unsubscribe from all optional notifications', 'notification', 'unsubscribe');
        verifyBatchNotificationUpdates('reset-notification-settings', 'Reset notification settings', 'notification', 'reset');

        verifyBatchNotificationUpdates('subscribe-all-emails', 'Subscribe to all emails', 'email', 'subscribe');
        verifyBatchNotificationUpdates('unsubscribe-all-optional-emails', 'Unsubscribe from all optional emails', 'email', 'unsubscribe');
        verifyBatchNotificationUpdates('reset-email-settings', 'Reset email settings', 'email', 'reset');
    });

    it('Should allow the user to subscribe and unsubscribe to individual notification/email inputs', () => {
        return;
        cy.get('input[data-testid="checkbox-input"]')
            .each(($el) => verifyIndividualNotificationUpdates($el.attr('name')));
    });

    it('Should allow the user to apply notification defaults across multiple courses', () => {
        verifyNotificationSync(true);

        // Apply an additional unique setting to the development course to ensure sync is truly propagated
        cy.visit(['development', 'notifications', 'settings']);
        cy.get('#team_member_submission').should('not.be.checked').check();
        verifyUpdateMessage();

        verifyNotificationSync(false);

        // Remove syncing for cleanup
        cy.visit(['sample', 'notifications', 'settings']);
        cy.get('[data-testid="sync-notifications-button"]').click();
        verifyUpdateMessage(true, 'Notification syncing has been disabled');
    });
});
