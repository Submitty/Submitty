describe('Test cases revolving around the manage teams section of Team Gradeables', () => {
    // Students that will be used for testing (they start out teamless)
    const STUDENT_1 = 'jaskob';
    const STUDENT_2 = 'krajce';
    const STUDENT_3 = 'windlj';
    const GRADEABLE = 'grading_homework_team_pdf';

    function cyAssertRegistration(user, section, subsection = '') {
        if (subsection !== '') {
            cy.get(`[data-testid="user-row-${user}"] [data-testid="registration-section"]`)
                .invoke('text')
                .invoke('replace', /\s+/g, '') // strips whitespace characters
                .should('equal', `${section}-${subsection}`);
        }
        else {
            cy.get(`[data-testid="user-row-${user}"] [data-testid="registration-section"]`)
                .invoke('text')
                .invoke('replace', /\s+/g, '') // strips whitespace characters
                .should('equal', section);
        }
    }

    before(() => {
        // Log in to set up the initial state
        cy.login('instructor');

        // Navigate to Manage Students page
        cy.visit(['sample', 'users']);

        cyAssertRegistration(STUDENT_1, 'NULL');
        cyAssertRegistration(STUDENT_2, 'NULL');
        cyAssertRegistration(STUDENT_3, 'NULL');

        // Set up subsections
        cy.get(`[data-testid="edit-student-${STUDENT_1}-button"]`).click();
        cy.get('[data-testid="registration-section-dropdown"]').select('1');
        cy.get('[data-testid="registration-subsection"]').clear();
        cy.get('[data-testid="registration-subsection"]').type('cypress');
        cy.get('[data-testid="submit-user-form-button"]').click();

        cyAssertRegistration(STUDENT_1, '1', 'cypress');

        cy.get(`[data-testid="edit-student-${STUDENT_2}-button"]`).click();
        cy.get('[data-testid="registration-section-dropdown"]').select('1');
        cy.get('[data-testid="registration-subsection"]').clear();
        cy.get('[data-testid="registration-subsection"]').type('cypress');
        cy.get('[data-testid="submit-user-form-button"]').click();

        cyAssertRegistration(STUDENT_2, '1', 'cypress');

        // STUDENT_3 only gets a section (no subsection)
        // they are to be used for testing the creation of single-student teams
        cy.get(`[data-testid="edit-student-${STUDENT_3}-button"]`).click();
        cy.get('[data-testid="registration-section-dropdown"]').select('1');
        cy.get('[data-testid="submit-user-form-button"]').click();

        cyAssertRegistration(STUDENT_3, '1');
    });

    it('Should test Instructor creation, Student access, and deletion of teams', () => {
        // ==========================================
        // Test Instructor creation of teams
        // ==========================================
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', GRADEABLE, 'team']);

        // Verify the inline team count messaging
        cy.get('[data-testid="manage-teams-header"]').contains('Manage Teams').should('be.visible');

        cy.get('[data-testid="manage-teams-message"]')
            .contains('existing teams for this gradeable') // only check the first part of the message
            .invoke('text')
            .should('equal', 'There are currently 36 existing teams for this gradeable. There are currently 3 students not yet on a team and 101 students on existing teams for this gradeable.');

        // Tests the links to Grade Details and Manage Students
        cy.get('[data-testid="team-management-links"]').within(() => {
            cy.get('[data-testid="grade-details-link"]').click();
        });
        cy.url().should('include', '/grading');

        cy.visit(['sample', 'gradeable', GRADEABLE, 'team']);

        cy.get('[data-testid="team-management-links"]').within(() => {
            cy.get('[data-testid="manage-students-link"]').click();
        });
        cy.url().should('include', '/users');

        cy.visit(['sample', 'gradeable', GRADEABLE, 'team']); // Go back to teams page

        // Verify the buttons for management of teams exist
        cy.get('[data-testid="create-teams-from-registration-subsections"]')
            .should('be.visible')
            .and('contain', 'Create Teams from Registration Subsections');

        cy.get('[data-testid="create-single-student-teams"]')
            .should('be.visible')
            .and('contain', 'Create Single-Student Teams');

        cy.get('[data-testid="delete-all-teams"]')
            .should('be.visible')
            .and('contain', 'Delete All Teams');

        // Test the creation of teams from Registration Subsections
        cy.on('window:confirm', () => true); // Automatically accept the browswer confirmation popup
        cy.get('[data-testid="create-teams-from-registration-subsections"]').click();

        cy.get('[data-testid="popup-message"]')
            .invoke('text')
            .should('equal', 'Successfully created 1 teams from registration subsections.');

        // The Twig template has a setTimeout of 1500ms before reloading the page
        cy.get('[data-testid="manage-teams-message"]', { timeout: 5000 })
            .should('have.text', 'There are currently 37 existing teams for this gradeable. There are currently 1 students not yet on a team and 103 students on existing teams for this gradeable.');

        // Test the creation of single-student teams
        cy.get('[data-testid="create-single-student-teams"]').click();

        cy.get('[data-testid="popup-message"]')
            .invoke('text')
            .should('equal', 'Successfully created 1 single-student teams.');

        // The Twig template has a setTimeout of 1500ms before reloading the page
        cy.get('[data-testid="manage-teams-message"]', { timeout: 5000 })
            .should('have.text', 'There are currently 38 existing teams for this gradeable. There are currently 0 students not yet on a team and 104 students on existing teams for this gradeable.');

        cy.logout('instructor');
        // ==========================================
        // Test student team access
        // ==========================================
        [STUDENT_1, STUDENT_2, STUDENT_3].forEach((user) => {
            cy.login(user);
            cy.visit(['sample', 'gradeable', GRADEABLE, 'team']);
            cy.get('[data-testid="your-team-header"]').should('exist');
        });

        cy.logout(STUDENT_3);

        // ==========================================
        // Test instructor deletion of teams
        // ==========================================
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', GRADEABLE, 'team']);
        cy.on('window:confirm', () => true);

        cy.get('[data-testid="delete-all-teams"]').click();

        cy.get('[data-testid="popup-message"]')
            .invoke('text')
            .should('equal', 'Successfully deleted 2 teams. Skipped 36 teams with submissions. Skipped 0 teams with instructor-level users.');

        cy.get('[data-testid="manage-teams-message"]', { timeout: 5000 })
            .should('have.text', 'There are currently 36 existing teams for this gradeable. There are currently 3 students not yet on a team and 101 students on existing teams for this gradeable.');
    });

    after(() => {
        // Clean up remaining testing state
        cy.login('instructor');

        // Reset the registration sections & subsections on the Manage Students page
        cy.visit(['sample', 'users']);
        cy.get(`[data-testid="edit-student-${STUDENT_1}-button"]`).click();
        cy.get('[data-testid="registration-section-dropdown"]').select('null');
        cy.get('[data-testid="registration-subsection"]').clear();
        cy.get('[data-testid="submit-user-form-button"]').click();

        cyAssertRegistration(STUDENT_1, 'NULL');

        cy.get(`[data-testid="edit-student-${STUDENT_2}-button"]`).click();
        cy.get('[data-testid="registration-section-dropdown"]').select('null');
        cy.get('[data-testid="registration-subsection"]').clear();
        cy.get('[data-testid="submit-user-form-button"]').click();

        cyAssertRegistration(STUDENT_2, 'NULL');

        cy.get(`[data-testid="edit-student-${STUDENT_3}-button"]`).click();
        cy.get('[data-testid="registration-section-dropdown"]').select('null');
        cy.get('[data-testid="submit-user-form-button"]').click();

        cyAssertRegistration(STUDENT_3, 'NULL');
    });
});
