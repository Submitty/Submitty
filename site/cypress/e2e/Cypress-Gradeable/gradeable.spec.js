it('Should test if team autograding is working correctly', () => {
    cy.login('student');
    const teamFile = 'cypress/fixtures/copy_of_more_autograding_examples/cpp_hidden_tests/submissions/frame_hardcoded.cpp';
    cy.visit(['sample', 'gradeable', 'closed_team_homework']);
    cy.get('#startnew').click();

    cy.get('#upload1').selectFile(teamFile, { action: 'drag-drop' });
    cy.get('#submit').click();
    cy.get('[data-testid="new-submission-info"]').should('contain', 'New submission for: Closed Team Homework');
    cy.get('body').type('{enter}');
    cy.get('[data-testid="new-submission-info"]').should('contain', 'New submission for: Closed Team Homework');
    cy.get('body').should('not.contain', 'went wrong');
    cy.reload();
    cy.contains('[data-testid="score-pill-badge"]', '12 / 10', { timeout: 100000 }).should('exist');

    cy.login('wisoza');
    cy.visit(['sample', 'gradeable', 'closed_team_homework']);
    cy.contains('[data-testid="score-pill-badge"]', '12 / 10', { timeout: 100000 }).should('exist');

    cy.get('#startnew').click();
    const badFile = 'cypress/fixtures/copy_of_more_autograding_examples/file_check/submissions/a.txt';
    cy.get('#upload1').selectFile(badFile, { action: 'drag-drop' });
    cy.get('#submit').click();

    cy.get('[data-testid="new-submission-info"]').should('contain', 'New submission for: Closed Team Homework');
    cy.get('body').type('{enter}');
    cy.get('[data-testid="new-submission-info"]').should('contain', 'New submission for: Closed Team Homework');
    cy.get('body').should('not.contain', 'went wrong');
    cy.reload();
    cy.contains('[data-testid="score-pill-badge"]', '0 / 10', { timeout: 120000 }).should('exist');

    cy.login('student');
    cy.visit(['sample', 'gradeable', 'closed_team_homework']);
    cy.contains('[data-testid="score-pill-badge"]', '0 / 10', { timeout: 120000 }).should('exist');
});

it('Should test if non-team autograding is working correctly', () => {
    cy.login('bitdiddle');
    const subFile = 'cypress/fixtures/copy_of_tutorial/examples/12_system_calls/submissions/serial_fork.c';
    cy.visit(['sample', 'gradeable', 'grades_released_homework_autota']);
    cy.get('#upload1').selectFile(subFile, { action: 'drag-drop' });
    cy.get('#submit').click();

    cy.get('[data-testid="new-submission-info"]').should('contain', 'New submission for: Autograde');
    cy.get('body').type('{enter}');
    cy.get('[data-testid="new-submission-info"]').should('contain', 'New submission for: Autograde');
    cy.get('body').should('not.contain', 'went wrong');
    cy.reload();
    cy.contains('[data-testid="score-pill-badge"]', '10 / 10', { timeout: 120000 }).should('exist');
});

it('Should test locked gradeable with non-zero depends_on_points', () => {
    ['smithj', 'student', 'instructor2'].forEach((user) => {
        cy.login(user);
        cy.visit(['testing']);

        cy.get('[data-testid="locked_homework_points"]')
            .find('[data-testid="submit-btn"]')
            .click();

        cy.on('window:alert', (text) => {
            expect(text).to.include(
                'Please complete Open Homework first with a score of 7 point(s).'
            );
        });

        if (user !== 'instructor2') {
            cy.visit(['testing', 'gradeable', 'locked_homework_points']);
            cy.get('[data-testid="popup-message"]')
                .contains('You have not unlocked this gradeable yet');
        }
        cy.logout();
    });

    // users with 7+ points on open_homework should have access
    ['kinge', 'adamsg', 'aphacker'].forEach((user) => {
        cy.login(user);
        cy.visit(['testing']);

        cy.get('[data-testid="locked_homework_points"]')
            .find('[data-testid="submit-btn"]')
            .click();

        cy.visit(['testing', 'gradeable', 'locked_homework_points']);
        cy.get('[data-testid="new-submission-info"]').should('exist');
        cy.logout();
    });
});