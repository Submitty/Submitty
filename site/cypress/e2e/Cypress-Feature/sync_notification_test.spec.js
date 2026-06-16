describe('Notification Settings Sync', () => {
    // Helper to visit notification settings for a given course
    const visitNotificationSettings = (course = 'sample') => {
        cy.visit([course, 'notifications', 'settings']);
        cy.get('[data-testid="notification-settings-header"]').should('be.visible');
    };

    const getRandomGradeableName = () => {
    const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    let randomString = '';
    for (let i = 0; i < 5; i++) {
        randomString += characters[Math.floor(Math.random() * characters.length)];
    }
    return randomString;
    };

    // Helper to open the sync popup
    const openSyncPopup = () => {
        cy.get('[data-testid="sync-notification-settings-top"]').first().click();
        cy.get('#sync-notification-popup').should('be.visible');
    };

    // Helper to create new course and add student
    const createNewCourse = () => {
        cy.login('instructor');
        cy.visit('/home/courses/new');
        const course = getRandomGradeableName().toLowerCase() + "_noti_test";
        cy.get('#course_title').type(course);
        // select the sample group
        cy.get('#group_name').select(4);
        cy.get('#course-creation-form button[type="submit"]').click();
        cy.reload();
        cy.visit([course, 'sections']);
        cy.get('.add-registration-section-btn').click();
        cy.get('[data-testid="popup-window"]').should('be.visible');
        cy.get('#new-section-id').click();
        cy.get('#new-section-id').type('1');
        cy.get('#new-course-id-num').click();
        cy.get('#new-course-id-num').type('11111');
        // submit added section
        cy.get('input[type="submit"][value="Add Section"]').click();
        cy.visit([course, 'users']);
        cy.get('a[href="javascript:newStudentForm()"]').click();
        cy.get('#user_id').click();
        cy.get('#user_id').type('student');
        cy.get('[data-testid="registration-section-dropdown"]').select('1');
        cy.get('[data-testid="submit-user-form-button"]').click();
        cy.logout();
        return course;
    };

    describe('Sync settings to other courses', () => {
        beforeEach(() => {
            cy.login('instructor');
            visitNotificationSettings('sample');
        });

        it('Should open the sync popup when clicking Sync notification settings', () => {
            openSyncPopup();
            cy.get('#sync-notification-popup').within(() => {
                cy.contains('Sync Notification Settings').should('be.visible');
                cy.contains('Select courses to copy').should('not.exist'); // uses the twig label
                cy.get('.sync-course-list').should('be.visible');
                cy.get('.sync-course-checkbox').should('have.length.at.least', 1);
            });
        });

        it('Should sync notification settings to a selected course', () => {
            // Set a known state: check merge_threads, uncheck all_new_threads
            cy.get('input[name="merge_threads"]').then(($cb) => {
                if (!$cb.is(':checked')) {
                    cy.wrap($cb).click();
                }
            });
            cy.get('input[name="all_new_threads"]').then(($cb) => {
                if ($cb.is(':checked')) {
                    cy.wrap($cb).click();
                }
            });

            openSyncPopup();

            // Select the first available course
            cy.get('#sync-notification-popup .sync-course-checkbox').first().check({ force: true });

            // Click Sync Settings
            cy.get('#sync-notification-popup').contains('button', 'Sync Settings').click();

            // Expect a success message
            cy.get('.alert-success, #success-alert, [data-testid="success-message"]')
                .should('be.visible');
        });

        it('Should select all courses using Select All button', () => {
            openSyncPopup();

            cy.get('#sync-notification-popup').contains('button', 'Select All').click();
            cy.get('#sync-notification-popup .sync-course-checkbox').each(($cb) => {
                cy.wrap($cb).should('be.checked');
            });
        });

        it('Should deselect all courses using Clear Selection button', () => {
            openSyncPopup();

            cy.get('#sync-notification-popup').contains('button', 'Select All').click();
            cy.get('#sync-notification-popup').contains('button', 'Clear Selection').click();
            cy.get('#sync-notification-popup .sync-course-checkbox').each(($cb) => {
                cy.wrap($cb).should('not.be.checked');
            });
        });

        it('Should close the popup when clicking Close', () => {
            openSyncPopup();
            cy.get('#sync-notification-popup').contains('button', 'Close').first().click();
            cy.get('#sync-notification-popup').should('not.be.visible');
        });

        it('Should verify synced settings appear on the target course', () => {
            // Set a distinctive state on source course
            cy.get('input[name="team_invite"]').then(($cb) => {
                if (!$cb.is(':checked')) {
                    cy.wrap($cb).click();
                }
            });
            cy.get('input[name="all_new_posts"]').then(($cb) => {
                if ($cb.is(':checked')) {
                    cy.wrap($cb).click();
                }
            });

            openSyncPopup();

            // Sync to 'tutorial' course
            cy.get('#sync-notification-popup .sync-course-checkbox').each(($cb) => {
                if ($cb.val().includes('tutorial')) {
                    cy.wrap($cb).check({ force: true });
                }
            });

            cy.get('#sync-notification-popup').contains('button', 'Sync Settings').click();
            cy.get('.alert-success, #success-alert, [data-testid="success-message"]')
                .should('be.visible');

            // Navigate to the target course and verify settings were copied
            visitNotificationSettings('tutorial');
            cy.get('input[name="team_invite"]').should('be.checked');
            cy.get('input[name="all_new_posts"]').should('not.be.checked');
        });
    });

    describe('Save as Future Course Default', () => {
        beforeEach(() => {
            cy.login('instructor');
            visitNotificationSettings('sample');
        });

        it('Should show Save as Future Course Default button when no defaults exist', () => {
            openSyncPopup();
            cy.get('[data-testid="save-notification-defaults"]').scrollIntoView();
            cy.get('[data-testid="save-notification-defaults"]').should('be.visible');
            cy.get('[data-testid="save-notification-defaults"]').should('contain.text', 'Save as Future Course Default');
        });

        it('Should save current settings as future course defaults', () => {
            // Set a known state
            cy.get('input[name="merge_threads"]').then(($cb) => {
                if (!$cb.is(':checked')) {
                    cy.wrap($cb).click();
                }
            });
            cy.get('input[name="all_new_threads"]').then(($cb) => {
                if ($cb.is(':checked')) {
                    cy.wrap($cb).click();
                }
            });

            openSyncPopup();
            cy.get('[data-testid="save-notification-defaults"]').click();

            cy.get('.alert-success, #success-alert, [data-testid="success-message"]')
                .should('be.visible');

            // Button should now say "Update Future Course Defaults"
            openSyncPopup();
            cy.get('[data-testid="save-notification-defaults"]')
                .should('contain.text', 'Update Future Course Defaults');
        });

        it('Should apply saved defaults to a newly created course', () => {
            // Step 1: Set and save defaults on source course
            cy.login('student');
            visitNotificationSettings('sample');
            cy.get('input[name="team_invite"]').then(($cb) => {
                if (!$cb.is(':checked')) {
                    cy.wrap($cb).click();
                }
            });
            cy.get('input[name="self_notification"]').then(($cb) => {
                if ($cb.is(':checked')) {
                    cy.wrap($cb).click();
                }
            });

            openSyncPopup();
            cy.get('[data-testid="save-notification-defaults"]').click();
            cy.get('.alert-success, #success-alert, [data-testid="success-message"]')
                .should('be.visible');

            //create a new course and add student to it, also return the name of the new course
            let course = createNewCourse();

            cy.login('student');
            visitNotificationSettings('test_noti_new_course');

            cy.get('input[name="team_invite"]').should('be.checked');
            cy.get('input[name="self_notification"]').should('not.be.checked');
        });
    });
});
