describe('Notification Settings: Sync & Future Course Defaults', () => {
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
        cy.get(`[data-testid="setting-${name}"]`).then(($cb) => {
            if ($cb.is(':checked') !== checked) {
                cy.wrap($cb).click();
                cy.wait('@saveSettings');
            }
        });
    };

    const createCourseAndAddStudent = () => {
        const course = `${randomName()}_noti_test`;
        cy.intercept('GET', '**/user_information').as('userInfo');
        cy.intercept('POST', '**/users').as('addUser');

        cy.login('instructor');
        cy.visit('/home/courses/new');
        cy.get('[data-testid="course-title-input"]').type(course);
        cy.get('[data-testid="course-group-select"]').select(4);
        cy.get('[data-testid="create-course-submit"]').click();
        cy.wait(5000);
        cy.visit([course, 'sections']);
        cy.get('[data-testid="add-registration-section-btn"]').click();
        cy.get('[data-testid="popup-window"]').should('be.visible');
        cy.get('[data-testid="new-section-id"]').type('1');
        cy.get('[data-testid="new-course-id-num"]').type('11111');
        cy.get('[data-testid="add-section-submit"]').click();
        cy.visit([course, 'users']);
        cy.get('[data-testid="new-student-form-btn"]').click();
        cy.get('[data-testid="user-id-input"]').type('student');
        cy.get('[data-testid="registration-section-dropdown"]').select('1');
        cy.get('[data-testid="submit-user-form-button"]').click();
        cy.visit('/home');
        cy.logout();
        return course;
    };

    beforeEach(() => {
        cy.intercept('POST', '**/notifications/settings').as('saveSettings');
        cy.intercept('POST', '**/notifications/save_defaults').as('saveDefaults');
        cy.intercept('POST', '**/notifications/sync').as('syncSettings');
    });

    describe('Sync settings to other courses', () => {
        beforeEach(() => {
            cy.login('instructor');
            visitNotificationSettings('sample');
        });

        it('opens the sync popup', () => {
            openSyncPopup();
            cy.get('#sync-notification-popup').within(() => {
                cy.contains('Sync Notification Settings').should('be.visible');
                cy.get('[data-testid="sync-course-list"]').should('exist');
                cy.get('[data-testid="sync-course-checkbox"]').should('have.length.at.least', 1);
            });
        });

        it('selects all courses with Select All', () => {
            openSyncPopup();
            cy.get('[data-testid="sync-select-all"]').click();
            cy.get('[data-testid="sync-course-checkbox"]').each(($cb) => {
                cy.wrap($cb).should('be.checked');
            });
        });

        it('clears selection with Clear Selection', () => {
            openSyncPopup();
            cy.get('[data-testid="sync-select-all"]').click();
            cy.get('[data-testid="sync-clear-selection"]').click();
            cy.get('[data-testid="sync-course-checkbox"]').each(($cb) => {
                cy.wrap($cb).should('not.be.checked');
            });
        });

        it('closes the popup with Close', () => {
            openSyncPopup();
            cy.get('#sync-notification-popup').contains('button', 'Close').first().click();
            cy.get('#sync-notification-popup').should('not.be.visible');
        });

        it('syncs settings to the tutorial course', () => {
            setCheckbox('merge_threads', true);
            setCheckbox('all_new_threads', false);

            openSyncPopup();
            cy.get('[data-testid="sync-course-checkbox"]').each(($cb) => {
                if ($cb.val().includes('tutorial')) {
                    cy.wrap($cb).check({ force: true });
                }
            });
            cy.get('[data-testid="sync-submit"]').click();
            cy.wait('@syncSettings');

            visitNotificationSettings('tutorial');
            cy.get('[data-testid="setting-merge_threads"]').should('be.checked');
            cy.get('[data-testid="setting-all_new_threads"]').should('not.be.checked');
        });
    });

    describe('Save as Future Course Default', () => {
        it('shows the "Save as Future Course Default" label when sample is not the default', () => {
            cy.login('instructor');
            visitNotificationSettings('sample');
            openSyncPopup();
            cy.get('[data-testid="save-notification-defaults"]').scrollIntoView();
            cy.get('[data-testid="save-notification-defaults"]').should('be.visible');
            cy.get('[data-testid="save-notification-defaults"]').should('contain.text', 'Save as Future Course Default');
        });

        it('marks the course as default and shows the banner', () => {
            cy.login('instructor');
            visitNotificationSettings('sample');

            openSyncPopup();
            cy.get('[data-testid="save-notification-defaults"]').click();
            cy.wait('@saveDefaults');

            cy.get('[data-testid="default-course-banner"]').should('be.visible');

            openSyncPopup();
            cy.get('[data-testid="save-notification-defaults"]')
                .should('contain.text', 'This Course Is Your Default');
        });

        it('applies a student\'s saved defaults to a newly created course', () => {
            cy.login('student');
            visitNotificationSettings('sample');
            setCheckbox('merge_threads', true);
            setCheckbox('team_invite', false);

            openSyncPopup();
            cy.get('[data-testid="save-notification-defaults"]').click();
            cy.wait('@saveDefaults');
            cy.get('[data-testid="default-course-banner"]').should('be.visible');
            cy.logout();

            const course = createCourseAndAddStudent();

            cy.login('student');
            visitNotificationSettings(course);
            cy.get('[data-testid="setting-merge_threads"]').scrollIntoView();
            cy.get('[data-testid="setting-merge_threads"]').should('be.checked');
            cy.get('[data-testid="setting-team_invite"]').should('not.be.checked');
        });
    });
});
