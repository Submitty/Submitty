describe('Test cases revolving around the manage teams section of Team Gradeables', () => {
    // Students that will be assigned subsections (they start out teamless)
    const STUDENT_1 = 'browna';
    const STUDENT_2 = 'cummin';

    before(() => {
        // Log in to set up the initial state
        cy.login('instructor');

        // Navigate to Manage Students page
        cy.visit(['sample', 'users']);

        // Set up subsections
        cy.get(`[data-testid="edit-student-${STUDENT_1}-button"]`).click();
        cy.get('#registration_subsection').clear();
        cy.get('#registration_subsection').type('cypress');
        cy.get('#user-form-submit').click();

        cy.get(`[data-testid="edit-student-${STUDENT_2}-button"]`).click();
        cy.get('#registration_subsection').clear();
        cy.get('#registration_subsection').type('cypress');
        cy.get('#user-form-submit').click();

        // Re-visit the Manage Students page to ensure the changes applied
        cy.visit(['sample', 'users']);
        cy.get(`#user-${STUDENT_1} .td-registration-section`).should('contain', 'cypress');
        cy.get(`#user-${STUDENT_2} .td-registration-section`).should('contain', 'cypress');
    });

    context('Instructor team management', () => {
        // Login the instructor and navigate to the teams page before each test in this context
        beforeEach(() => {
            cy.login('instructor');
            cy.visit(['sample', 'gradeable', 'open_team_homework', 'team']);
        });

        it('Should verify the inline team count messaging', () => {
            cy.get('h1').contains('Manage Teams').should('be.visible');

            cy.get('[data-testid="manage-teams-message"]')
                .contains('existing teams for this gradeable') // only check the first part of the message
                .invoke('text')
                .should('equal', 'There are currently 30 existing teams for this gradeable. There are currently 16 students not yet on a team and 85 students on existing teams for this gradeable.');
        });

        it('Should have working links to Grade Details and Manage Students', () => {
            cy.get('.team-management-links').within(() => {
                cy.contains('a', 'Grade Details').click();
            });
            cy.url().should('include', '/grading');

            // Go back
            cy.visit(['sample', 'gradeable', 'open_team_homework', 'team']);

            cy.get('.team-management-links').within(() => {
                cy.contains('a', 'Manage Students').click();
            });
            cy.url().should('include', '/users');
        });

        it('Should verify the instructor management buttons for teams exist', () => {
            cy.get('#create_teams_from_registration_subsections')
                .should('be.visible')
                .and('contain', 'Create Teams from Registration Subsections');

            cy.get('#create_single_student_teams')
                .should('be.visible')
                .and('contain', 'Create Single-Student Teams');

            cy.get('#delete_all_teams')
                .should('be.visible')
                .and('contain', 'Delete All Teams');
        });

        it('Should successfully create teams from Registration Subsections', () => {
            // Automatically accept the browser confirmation popup
            cy.on('window:confirm', () => true);

            // Click the button
            cy.get('#create_teams_from_registration_subsections').click();

            cy.get('[data-testid="popup-message"]')
                .invoke('text')
                .should('equal', 'Successfully created 1 teams from registration subsections.');

            // The Twig template has a setTimeout of 1500ms before reloading the page
            cy.get('[data-testid="manage-teams-message"]', { timeout: 5000 })
                .should('have.text', 'There are currently 31 existing teams for this gradeable. There are currently 14 students not yet on a team and 87 students on existing teams for this gradeable.');
        });

        it('Should successfully create single-student teams', () => {
            // Automatically accept the browser confirmation popup
            cy.on('window:confirm', () => true);

            // Click the button
            cy.get('#create_single_student_teams').click();

            cy.get('[data-testid="popup-message"]')
                .invoke('text')
                .should('equal', 'Successfully created 14 single-student teams.');

            // The Twig template has a setTimeout of 1500ms before reloading the page
            cy.get('[data-testid="manage-teams-message"]', { timeout: 5000 })
                .should('have.text', 'There are currently 45 existing teams for this gradeable. There are currently 0 students not yet on a team and 101 students on existing teams for this gradeable.');
        });
    });

    context('Student team access', () => {
        it('Should test student access to their new teams created in this test', () => {
            [STUDENT_1, STUDENT_2, 'efferm'].forEach((user) => {
                cy.login(user);
                cy.visit(['sample', 'gradeable', 'open_team_homework', 'team']);
                cy.get('[data-testid="your-team-header"]').should('exist');
            });
        });
    });

    context('Instructor team management, test delete teams', () => {
        it('Should successfully test the deletion of teams', () => {
            cy.login('instructor');
            cy.visit(['sample', 'gradeable', 'open_team_homework', 'team']);
            cy.on('window:confirm', () => true);

            cy.get('#delete_all_teams').click();

            cy.get('[data-testid="popup-message"]')
                .invoke('text')
                .should('equal', 'Successfully deleted 15 teams. Skipped 30 teams with submissions. Skipped 0 teams with instructor-level users.');

            cy.get('[data-testid="manage-teams-message"]', { timeout: 5000 })
                .should('have.text', 'There are currently 30 existing teams for this gradeable. There are currently 16 students not yet on a team and 85 students on existing teams for this gradeable.');
        });
    });

    after(() => {
        // Clean up remaining testing state
        cy.login('instructor');

        // Reset the registration subsections on the Manage Students page
        cy.visit(['sample', 'users']);
        cy.get(`[data-testid="edit-student-${STUDENT_1}-button"]`).click();
        cy.get('#registration_subsection').clear();
        cy.get('#user-form-submit').click();

        cy.get(`[data-testid="edit-student-${STUDENT_2}-button"]`).click();
        cy.get('#registration_subsection').clear();
        cy.get('#user-form-submit').click();
    });
});
