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

    // Toggle a checkbox to a known state and wait for the auto-save to persist
    const setCheckbox = (name, checked) => {
        cy.get(`input[name="${name}"]`).then(($cb) => {
            if ($cb.is(':checked') !== checked) {
                cy.wrap($cb).click();
                cy.wait('@saveSettings');
            }
        });
    };

    const createCourseAndAddStudent = () => {
        const course = randomName() + '_noti_test';
        cy.login('instructor');
        cy.visit('/home/courses/new');
        cy.get('#course_title').type(course);
        cy.get('#group_name').select(4);
        cy.get('#course-creation-form button[type="submit"]').click();
        cy.reload();
        cy.visit([course, 'sections']);
        cy.get('.add-registration-section-btn').click();
        cy.get('[data-testid="popup-window"]').should('be.visible');
        cy.get('#new-section-id').type('1');
        cy.get('#new-course-id-num').type('11111');
        cy.get('input[type="submit"][value="Add Section"]').click();
        cy.visit([course, 'users']);
        cy.get('a[href="javascript:newStudentForm()"]').click();
        cy.get('#user_id').type('student');
        cy.get('[data-testid="registration-section-dropdown"]').select('1');
        cy.get('[data-testid="submit-user-form-button"]').click();
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
                cy.get('.sync-course-list').should('be.visible');
                cy.get('.sync-course-checkbox').should('have.length.at.least', 1);
            });
        });

        it('selects all courses with Select All', () => {
            openSyncPopup();
            cy.get('#sync-notification-popup').contains('button', 'Select All').click();
            cy.get('#sync-notification-popup .sync-course-checkbox').each(($cb) => {
                cy.wrap($cb).should('be.checked');
            });
        });

        it('clears selection with Clear Selection', () => {
            openSyncPopup();
            cy.get('#sync-notification-popup').contains('button', 'Select All').click();
            cy.get('#sync-notification-popup').contains('button', 'Clear Selection').click();
            cy.get('#sync-notification-popup .sync-course-checkbox').each(($cb) => {
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
            cy.get('#sync-notification-popup .sync-course-checkbox').each(($cb) => {
                if ($cb.val().includes('tutorial')) {
                    cy.wrap($cb).check({ force: true });
                }
            });
            cy.get('#sync-notification-popup').contains('button', 'Sync Settings').click();
            cy.wait('@syncSettings');

            visitNotificationSettings('tutorial');
            cy.get('input[name="merge_threads"]').should('be.checked');
            cy.get('input[name="all_new_threads"]').should('not.be.checked');
        });
    });

    describe('Save as Future Course Default', () => {
        it('shows the "Save as Future Course Default" label when sample is not the default', () => {
            cy.login('instructor');
            visitNotificationSettings('sample');
            openSyncPopup();
            cy.get('[data-testid="save-notification-defaults"]')
                .scrollIntoView()
                .should('be.visible')
                .and('contain.text', 'Save as Future Course Default');
        });

        it('marks the course as default and shows the banner', () => {
            cy.login('instructor');
            visitNotificationSettings('sample');

            openSyncPopup();
            cy.get('[data-testid="save-notification-defaults"]').click();
            cy.wait('@saveDefaults');

            // page reloads on success → banner should now be present
            cy.get('[data-testid="default-course-banner"]').should('be.visible');

            openSyncPopup();
            cy.get('[data-testid="save-notification-defaults"]')
                .should('contain.text', 'This Course Is Your Default');
        });

        it('applies a student\'s saved defaults to a newly created course', () => {
            // 1. Student sets NON-default values on sample, then saves sample as the default
            cy.login('student');
            visitNotificationSettings('sample');
            setCheckbox('merge_threads', true);   // sample default is false
            setCheckbox('team_invite', false);    // sample default is true

            openSyncPopup();
            cy.get('[data-testid="save-notification-defaults"]').click();
            cy.wait('@saveDefaults');
            cy.get('[data-testid="default-course-banner"]').should('be.visible');
            cy.logout();

            // 2. Instructor creates a new course and enrolls the student
            const course = createCourseAndAddStudent();

            // 3. The student's settings in the new course should match the source course
            cy.login('student');
            visitNotificationSettings(course);
            cy.get('input[name="merge_threads"]').should('be.checked');
            cy.get('input[name="team_invite"]').should('not.be.checked');
        });
    });
});