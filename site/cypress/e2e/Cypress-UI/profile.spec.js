import { buildUrl } from '../../support/utils';

const genAlpha = (length = 5) => {
    const characters = 'abcdefghijklmnopqrstuvwxyz';
    return Array(length).fill().map(() => characters[Math.floor(Math.random() * characters.length)]).join('');
};

const testFormOpening = (rowId, formId) => {
    cy.get(`${rowId} > button.icon`).click();
    cy.get(formId).should('be.visible');
    cy.get(`.popup-form${formId} .form-buttons .close-button`).click();
    cy.get(`.popup-form${formId}`).should('not.be.visible');
};

const getVisibleData = () => {
    const data = {};

    cy.get('#givenname-row > button').invoke('text').then((text) => data.givenName = text.trim());
    cy.get('#familyname-row > button').invoke('text').then((text) => data.familyName = text.trim());
    cy.get('#pronouns-row > button').invoke('text').then((text) => data.pronouns = text.trim());
    cy.get('#secondary-email-row > button').invoke('text').then((text) => data.secondaryEmail = text.trim());

    return data;
};

const testModification = (formId, cb) => {
    cy.get('.alert-success').invoke('hide').should('not.be.visible');
    cy.get(`.popup-form${formId}`).invoke('show').within(cb);
    cy.get(`.popup-form${formId} .form-buttons input[type="submit"]`).click();
    cy.get(`.popup-form${formId}`).should('not.be.visible');
    cy.get('.alert-success', { timeout: 5000 }).should('be.visible');
};

const fillData = (data) => {
    testModification('#edit-username-form', () => {
        cy.get('#user-givenname-change').clear();
        cy.get('#user-givenname-change').type(data.givenName);
        cy.get('#user-familyname-change').clear();
        cy.get('#user-familyname-change').type(data.familyName);
    });

    testModification('#edit-pronouns-form', () => {
        cy.get('#user-pronouns-change').clear();
        cy.get('#user-pronouns-change').as('pronounsInput');
        data.pronouns && cy.get('@pronounsInput').type(data.pronouns);
    });

    testModification('#edit-secondary-email-form', () => {
        cy.get('#user-secondary-email-change').clear();
        cy.get('#user-secondary-email-change').as('secondaryEmailInput');
        data.secondaryEmail && cy.get('@secondaryEmailInput').type(data.secondaryEmail);
    });
};

const newUserData = {
    givenName: genAlpha(),
    familyName: genAlpha(),
    pronouns: genAlpha(),
    secondaryEmail: `${genAlpha()}@example.com`,
};

let priorUserData = {};

describe('Test cases revolving around user profile page', () => {
    beforeEach(() => {
        cy.visit('/user_profile');
        cy.login();
    });

    it('Should show the information rows', () => {
        return;
        cy.get('#username-row').should('be.visible');
        cy.get('#givenname-row').should('be.visible');
        cy.get('#familyname-row').should('be.visible');
        cy.get('#pronouns-row').should('be.visible');
        cy.get('#email-row').should('be.visible');
        cy.get('#secondary-email-row').should('be.visible');
        cy.get('#secondary-email-notify-row').should('be.visible');
    });

    // Selenium test_basic_info
    it('Should start with accurate values', () => {
        return;
        cy.get('[data-testid="username-row"]').should('contain.text', 'instructor');
        cy.get('[data-testid="email-row"]').should('contain.text', 'instructor@example.com');
    });

    // Selenium test_time_zone_selection
    it('should handle the timezone selector correctly', () => {
        return;
        // Check that the default value is NOT_SET/NOT_SET
        cy.get('[data-testid="time-zone-dropdown"]').should('contain.text', 'NOT_SET/NOT_SET');

        // Search and select the first timezone
        cy.get('#select2-time_zone_drop_down-container').click({ force: true });
        cy.get('input[aria-controls="select2-time_zone_drop_down-results"]').type('(UTC+14:00) Pacific/Kiritimati');
        cy.get('.select2-results__option').contains('(UTC+14:00) Pacific/Kiritimati').click({ force: true });
        cy.get('[data-testid="popup-message"]').next().should('contain.text', 'Warning: Local timezone does not match user timezone. Consider updating user timezone in profile.');
        cy.get('[data-testid="time-zone-dropdown"]').should('contain.text', '(UTC+14:00) Pacific/Kiritimati');

        // Search and select the last timezone
        cy.get('#select2-time_zone_drop_down-container').click({ force: true });
        cy.get('input[aria-controls="select2-time_zone_drop_down-results"]').type('(UTC-11:00) Pacific/Pago_Pago');
        cy.get('.select2-results__option').contains('(UTC-11:00) Pacific/Pago_Pago').click({ force: true });
        cy.get('[data-testid="popup-message"]').next().should('contain.text', 'Warning: Local timezone does not match user timezone. Consider updating user timezone in profile.');
        cy.get('[data-testid="time-zone-dropdown"]').should('contain.text', '(UTC-11:00) Pacific/Pago_Pago');

        // Filter options based on partial search input
        cy.get('#select2-time_zone_drop_down-container').click({ force: true });
        cy.get('input[aria-controls="select2-time_zone_drop_down-results"]').type('Pacific');
        cy.get('.select2-results__option').should('have.length', 38).and('contain.text', 'Pacific/');

        // Navigate and select options via keyboard
        cy.get('input[aria-controls="select2-time_zone_drop_down-results"]').type('{downarrow}{downarrow}{enter}', { force: true });
        cy.get('[data-testid="time-zone-dropdown"]').parent().find('.select2-selection__rendered').should('contain.text', '(UTC-11:00) Pacific/Pago_Pago');
        cy.get('[data-testid="popup-message"]').next().next().should('contain.text', 'Time-zone updated successfully');

        // Display message when no search results are found
        cy.get('#select2-time_zone_drop_down-container').click({ force: true });
        cy.get('input[aria-controls="select2-time_zone_drop_down-results"]').type('Nonexistent Zone');
        cy.get('.select2-results').should('contain.text', 'No results found').click({ force: true });
    });

    it('Should error then succeed uploading profile photo', () => {
        return;
        const filePath = '../more_autograding_examples/image_diff_mirror/submissions/student1.png';
        cy.get('[data-testid="upload-photo-button"]').click();
        cy.get('[data-testid="submit-button"]').click();
        // Since the login success message is still up, we get the next message.
        cy.get('[data-testid="popup-message"]').next().next().should('contain.text', 'No image uploaded to update the profile photo');
        cy.get('[data-testid="upload-photo-button"]').click();
        cy.get('[data-testid="user-image-button"]').selectFile(filePath);
        cy.get('[data-testid="submit-button"]').click();
        cy.get('[data-testid="popup-message"]').next().next().next().should('contain.text', 'Profile photo updated successfully!');
    });

    it('Flagging an innapropriate photo', () => {
        return;
        // Make sure an image has been uploaded
        const filePath = '../more_autograding_examples/image_diff_mirror/submissions/student1.png';
        cy.get('[data-testid="upload-photo-button"]').click();
        cy.get('[data-testid="user-image-button"]').selectFile(filePath);
        cy.get('[data-testid="submit-button"]').click();
        cy.visit(['sample', 'student_photos']);
        cy.get('.fa-flag').click({ force: true });
        cy.on('window:confirm', () => {
            return true;
        });

        cy.visit('/user_profile');
        cy.get('[data-flagged="flagged"]').should('contain.text', 'Your preferred image was flagged as inappropriate.');

        // Undo flagging of image
        cy.visit(['sample', 'student_photos']);
        cy.get('.fa-undo').click();
        cy.on('window:confirm', () => {
            return true;
        });
    });

    it('Should open and close the popups', () => {
        return;
        cy.get('.popup-form').should('not.be.visible');

        testFormOpening('#givenname-row', '#edit-username-form');
        testFormOpening('#familyname-row', '#edit-username-form');

        testFormOpening('#pronouns-row', '#edit-pronouns-form');

        testFormOpening('#secondary-email-row', '#edit-secondary-email-form');
        testFormOpening('#secondary-email-notify-row', '#edit-secondary-email-form');

        cy.get('.popup-form').should('not.be.visible');
    });

    it('Should test the modifying of the values', () => {
        return;
        priorUserData = getVisibleData();

        fillData(newUserData);

        const updatedData = getVisibleData();
        cy.wrap(updatedData).should('deep.equal', newUserData);
    });

    it('Should persist on refresh', () => {
        return;
        const userData = getVisibleData();
        cy.wrap(userData).should('deep.equal', newUserData);
    });

    it('Should handle notification sync preference across multiple courses', () => {
        const courses = ['sample', 'testing', 'tutorial', 'development', 'blank'];

        // Helper function to dismiss all popup messages
        const dismissAllMessages = () => {
            cy.get('body').then(($body) => {
                if ($body.find('[data-testid="popup-message"]').length > 0) {
                    cy.get('[data-testid="remove-message-popup"]').click({ multiple: true });
                }
            });
        };

        // Helper function to wait for and verify a success message
        const verifyMessage = (expectedText) => {
            cy.get('[data-testid="popup-message"]', { timeout: 10000 })
                .should('be.visible')
                .should('contain', expectedText);
            cy.get('[data-testid="remove-message-popup"]').first().click();
        };

        // Logout the instructor and login as the student
        cy.logout();
        cy.login('student');

        // Test sync functionality across courses
        courses.forEach((course, index) => {
            if (index === 0) {
                // Set up initial state on first course
                cy.visit('/user_profile');

                // First ensure sync is disabled
                cy.get('[data-testid="notification-sync-preference-dropdown"]')
                    .should('be.visible')
                    .then(($select) => {
                        cy.wrap($select).select('unsync');
                        verifyMessage('Notification sync has been disabled');
                    });

                // Navigate to notifications settings
                cy.visit(buildUrl([course, 'notifications', 'settings']));

                // Verify sync button shows "Sync Notifications"
                cy.get('[data-testid="sync-notifications-button"]')
                    .should('be.visible')
                    .should('contain', 'Sync Notifications');

                // Reset to clean state first to only be subscribed to mandatory notifications
                cy.get('[data-testid="unsubscribe-all-optional-notifications"]').click();
                cy.get('[data-testid="unsubscribe-all-optional-emails"]').click();
                cy.get('[data-testid="reset-notification-settings"]').click();
                cy.get('[data-testid="reset-email-settings"]').click();

                // Enable two specific non-disabled notifications for testing
                cy.get('#reply_in_post_thread').should('not.be.disabled').check();
                verifyMessage('Notification settings have been saved');

                cy.get('#all_released_grades_email').should('not.be.disabled').check();
                verifyMessage('Notification settings have been saved');

                // Enable sync
                cy.get('[data-testid="sync-notifications-button"]').click();
                verifyMessage('Notification sync has been enabled');

                // Verify the button text has been updated
                cy.get('[data-testid="sync-notifications-button"]')
                    .should('be.visible')
                    .should('contain', 'Unsync Notifications');

                // Verify the dropdown value has been updated
                cy.visit('/user_profile');
                cy.get('[data-testid="notification-sync-preference-dropdown"]')
                    .should('have.value', 'sync');
            }
            else {
                // On subsequent courses, verify sync propagated the settings
                cy.visit(buildUrl([course, 'notifications', 'settings']));

                // Verify sync button shows "Unsync Notifications"
                cy.get('[data-testid="sync-notifications-button"]')
                    .should('be.visible')
                    .should('contain', 'Unsync Notifications');

                // Verify the specific notifications we enabled are checked (sync is enabled)
                cy.get('#reply_in_post_thread').should('be.checked');
                cy.get('#all_released_grades_email').should('be.checked');

                // Ensure all other notifications are unchecked outside of disabled inputs
                cy.get('input[data-testid="checkbox-input"]').each(($checkbox) => {
                    const id = $checkbox.attr('id');

                    if (id === 'reply_in_post_thread' || id === 'all_released_grades_email') {
                        cy.wrap($checkbox).should('be.checked');
                    }
                    else if (!$checkbox.prop('disabled')) {
                        cy.wrap($checkbox).should('not.be.checked');
                    }
                });
            }
        });
    });

    after(() => {
        cy.visit('/user_profile');
        cy.login();

        fillData(priorUserData);

        const revertedData = getVisibleData();
        cy.wrap(revertedData).should('deep.equal', priorUserData);
    });
});
