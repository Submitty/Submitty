import { buildUrl } from '../../support/utils';
/*
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
}); */

const no_unseen_message = 'No unseen notifications.';

const markAllNotificationsSeen = (user) => {
    cy.login(user);
    cy.visit();
    cy.get('[data-testid="mark-seen-btn"]').click();
    cy.get('[data-testid="select-mark-all"]').click();
    cy.contains('button', 'Mark Seen').click();
    cy.logout();
}

const verifyAllNotificationsSeen = (user) => {
    cy.login(user);
    cy.visit();
    cy.get('[data-testid="toggle-unseen-only"]').then($btn => {
        if ($btn.text().includes('Show Unseen Only')) {
            cy.wrap($btn).click()
        }
    });
    cy.contains(no_unseen_message);
    cy.visit(buildUrl(['sample', 'notifications']));
    cy.contains(no_unseen_message);

    cy.logout();
};

// Tracks total notifications created
let notificationCount = 0;

const createAnnouncement = (title, content) => {
    cy.window().then(async (win) => {
        const body = {
            'title': title,
            'markdown_status': 0,
            'lock_thread_date': '',
            'thread_post_content': content,
            'cat[]': '1',
            'thread_status': -1,
            'csrf_token': win.csrfToken,
        };

        return cy.request({
            method: 'POST',
            url: buildUrl(['sample', 'forum', 'threads', 'new']),
            form: true,
            body: body,
            
        }).then((res) => {
            const body = JSON.parse(res.body);
            expect(res.status).to.eq(200);
            expect(body.status).to.eq('success');
            
        });
    });
};

const createAnnouncements = (count) => {
    cy.login('instructor');
    cy.visit(['sample', 'forum']);

    for (let i = 0; i < count; i++) {
        createAnnouncement(`Cypress Thread ${notificationCount}`, 'This is a Cypress-generated announcement.');
        notificationCount++;
    }

    cy.logout();
};

const deleteAnnouncements = () => {
    cy.login('instructor');
    cy.visit(buildUrl(['sample', 'forum']));

    cy.get('[data-testid="thread-list-item"]').first().invoke('text').then((text) => {
        const match = text.match(/\((\d+)\)/);
        const threadId = match ? Number(match[1]) : null;

        for (let i = 0; i < count; i++) {
            cy.window().then(async (win) => {
                const body = {
                    'title': title,
                    'markdown_status': 0,
                    'lock_thread_date': '',
                    'thread_post_content': content,
                    'cat[]': '1',
                    'thread_status': -1,
                    'csrf_token': win.csrfToken,
                };

                return cy.request({
                    method: 'POST',
                    url: buildUrl(['sample', 'forum', 'threads', 'new']),
                    form: true,
                    body: body,
                    
                }).then((res) => {
                    const body = JSON.parse(res.body);
                    expect(res.status).to.eq(200);
                    expect(body.status).to.eq('success');
                    
                });
            });
        }
    });


    cy.logout();
}

describe('Tests for creating and interacting with notifications', () => {
    before(() => {
        cy.login('student');
        cy.visit();
        cy.get('[data-testid="toggle-unseen-only"]') .should('have.text', 'Show All'); // Verify the initial state of local storage is unseen only
        cy.logout();

        ['instructor', 'student'].forEach((user) => {
            verifyAllNotificationsSeen(user);
            markAllNotificationsSeen(user);
        });

        createAnnouncements(12);
    });

    after(() => {
        deleteAnnouncements();
    });

    ['student'].forEach((user) => {
        it(`Should test triggering notifications, marking seen, navigation, dynamic updates for ${user}`, () => {
            cy.login(user);
            cy.visit();
        });
    });
});