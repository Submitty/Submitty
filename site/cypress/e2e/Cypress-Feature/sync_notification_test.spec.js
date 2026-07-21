// This test will not pass locally unless your authentication is set to database authentication

describe('notification sync and defaults test', () => {
    const visitNotificationSettings = (course = 'sample') => {
        cy.visit([course, 'notifications', 'settings']);
        cy.get('[data-testid="notification-settings-header"]').should('be.visible');
    };

    const randomName = () => {
        const chars = 'abcdefghijklmnopqrstuvwxyz';
        let s = '';
        for (let i = 0; i < 5; i++) {
            s += chars[Math.floor(Math.random() * chars.length)];
        }
        return s;
    };

    const openSyncPopup = () => {
        cy.get('[data-testid="sync-notification-settings-top"]').first().click();
        cy.get('#sync-notification-popup').should('be.visible');
    };

    const setCheckbox = (name, checked) => {
        cy.get(`input[name="${name}"]`).then(($cb) => {
            if ($cb.is(':checked') !== checked) {
                cy.wrap($cb).click();
                cy.wait('@saveSettings');
            }
        });
    };

    const clearDefaultIfSet = () => {
        openSyncPopup();
        cy.get('[data-testid="set-default-course"]').then(($cb) => {
            if ($cb.is(':checked')) {
                cy.wrap($cb).uncheck();
                cy.wait('@clearDefaults');
            }
        });
        cy.get('#sync-notification-popup').contains('button', 'Close').first().click();
    };

    const createCourse = () => {
        const course = `${randomName()}_noti_test`;

        cy.login('instructor');
        cy.visit('/home/courses/new');
        cy.get('[data-testid="course-title-input"]').type(course);
        cy.get('[data-testid="course-group-select"]').select('sample_tas_www');
        cy.get('[data-testid="create-course-submit"]').click();
        // eslint-disable-next-line no-restricted-syntax
        cy.waitAndReloadUntil(() => {
            return cy.get('body').then(($body) => {
                return $body.find(`[data-testid="${course}-button"]`).length > 0;
            });
        }, 5000, 100);
        return course;
    };

    const AddStudentToCourse = (course) => {
        cy.login('instructor');
        cy.visit([course, 'sections']);
        cy.get('[data-testid="add-registration-section-btn"]').click();
        cy.get('[data-testid="popup-window"]').should('be.visible');
        cy.get('[data-testid="new-section-id"]').type('1');
        cy.get('[data-testid="new-course-id-num"]').type('11111');
        cy.get('[data-testid="add-section-submit"]').click();
        cy.visit([course, 'users']);
        cy.get('[data-testid="new-student-form-btn"]').click();
        cy.get('[data-testid="user-id-input"]').type('student');
        // somewhere the authentication is set to database, so we need to type a password
        // if you are testing locally, comment this line out
        cy.get('[data-testid="password-input"]').type('student');
        cy.get('[data-testid="registration-section-dropdown"]').select('1');
        cy.get('[data-testid="submit-user-form-button"]').click();
        cy.get('[data-testid="popup-message').should('be.visible');
        cy.get('[data-testid="popup-message').should('have.text', 'Existing Submitty user \'student\' added');
        cy.visit('/home');

        cy.logout();
    };

    const archiveCourse = (course) => {
        cy.login('instructor');
        cy.visit([course, 'config']);
        cy.get('[data-testid="course-archive"]').check();
        cy.on('window:confirm', () => true);
    };

    beforeEach(() => {
        cy.intercept('POST', '**/notifications/settings').as('saveSettings');
        cy.intercept('POST', '**/notifications/save_defaults').as('saveDefaults');
        cy.intercept('POST', '**/notifications/clear_defaults').as('clearDefaults');
        cy.intercept('POST', '**/notifications/sync').as('syncSettings');
    });

    describe('Sync settings to other courses', () => {
        it('functionality of sync popup', () => {
            const course = createCourse();
            Cypress.env('test-course', course);
            cy.login('instructor');
            visitNotificationSettings('sample');
            openSyncPopup();

            cy.get('#sync-notification-popup').within(() => {
                cy.contains('Sync Notification Settings').should('be.visible');
                cy.get('[data-testid="sync-course-list"]').should('exist');
                cy.get('[data-testid="sync-course-checkbox"]').should('have.length.at.least', 1);
            });
            cy.get('[data-testid="sync-select-all"]').click();
            cy.get('[data-testid="sync-course-checkbox"]').each(($cb) => {
                cy.wrap($cb).should('be.checked');
            });
            cy.get('[data-testid="sync-clear-selection"]').click();
            cy.get('[data-testid="sync-course-checkbox"]').each(($cb) => {
                cy.wrap($cb).should('not.be.checked');
            });
            cy.get('#sync-notification-popup').contains('button', 'Close').first().click();
            cy.get('#sync-notification-popup').should('not.be.visible');
        });

        it('syncs settings to a new course', () => {
            const course = Cypress.env('test-course');
            cy.login('instructor');
            visitNotificationSettings('sample');
            setCheckbox('merge_threads', true);
            setCheckbox('all_new_threads', false);

            openSyncPopup();
            cy.get('[data-testid="sync-course-checkbox"]').each(($cb) => {
                if ($cb.val().includes(course)) {
                    cy.wrap($cb).check({ force: true });
                }
            });
            cy.get('[data-testid="sync-submit"]').click();
            cy.wait('@syncSettings');

            visitNotificationSettings(course);
            cy.get('input[name="merge_threads"]').should('be.checked');
            cy.get('input[name="all_new_threads"]').should('not.be.checked');
        });
    });

    describe('Future Course Default', () => {
        it('default checkbox is unchecked when this course is not the default', () => {
            const course = Cypress.env('test-course');
            cy.login('instructor');
            visitNotificationSettings('sample');
            clearDefaultIfSet();

            openSyncPopup();
            cy.get('[data-testid="set-default-course"]').scrollIntoView();
            cy.get('[data-testid="set-default-course"]').should('be.visible');
            cy.get('[data-testid="set-default-course"]').should('not.be.checked');
        });

        it('marks the course as default and shows the banner', () => {
            cy.login('instructor');
            visitNotificationSettings('sample');
            clearDefaultIfSet();

            openSyncPopup();
            cy.get('[data-testid="set-default-course"]').check();
            cy.wait('@saveDefaults');

            cy.get('[data-testid="default-course-banner"]').should('be.visible');
            cy.get('[data-testid="set-default-course"]').should('be.checked');
        });

        it('clears the default when unchecked and hides the banner', () => {
            cy.login('instructor');
            visitNotificationSettings('sample');

            openSyncPopup();
            cy.get('[data-testid="set-default-course"]').then(($cb) => {
                if (!$cb.is(':checked')) {
                    cy.wrap($cb).check();
                    cy.wait('@saveDefaults');
                }
            });

            cy.get('[data-testid="set-default-course"]').uncheck();
            cy.wait('@clearDefaults');

            cy.get('[data-testid="default-course-banner"]').should('not.be.visible');
        });

        it('shows the other-course banner on a different course', () => {
            cy.login('instructor');

            visitNotificationSettings('sample');
            openSyncPopup();
            cy.get('[data-testid="set-default-course"]').then(($cb) => {
                if (!$cb.is(':checked')) {
                    cy.wrap($cb).check();
                    cy.wait('@saveDefaults');
                }
            });
            const course = Cypress.env('test-course');
            visitNotificationSettings(course);
            cy.get('[data-testid="other-default-course-banner"]')
                .should('be.visible')
                .and('contain.text', 'sample');
        });

        it('applies a student\'s saved defaults to a newly created course', () => {
            cy.login('student');
            visitNotificationSettings('sample');
            setCheckbox('merge_threads', true);
            setCheckbox('team_invite', false);

            openSyncPopup();
            cy.get('[data-testid="set-default-course"]').then(($cb) => {
                if (!$cb.is(':checked')) {
                    cy.wrap($cb).check();
                    cy.wait('@saveDefaults');
                }
            });
            cy.get('[data-testid="default-course-banner"]').should('be.visible');
            cy.logout();

            const course = Cypress.env('test-course');
            AddStudentToCourse(course);

            cy.login('student');

            visitNotificationSettings(course);
            cy.get('input[name="merge_threads"]').scrollIntoView();
            cy.get('input[name="merge_threads"]').should('be.checked');
            cy.get('input[name="team_invite"]').should('not.be.checked');
            cy.logout();
            cy.login('instructor');
            archiveCourse(course);
        });
    });
});
