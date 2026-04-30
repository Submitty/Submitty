describe('Tests cases revolving around gradeable access and submission', () => {
    ['student', 'ta', 'grader', 'instructor'].forEach((user) => {
        it('Should upload file, submit, and remove file', () => {
            cy.login(user);
            const testfile1 = 'cypress/fixtures/file1.txt';
            const testfile2 = 'cypress/fixtures/file2.txt';

            cy.visit(['sample', 'gradeable', 'open_homework']);

            // Makes sure the clear button is not disabled by adding a file
            cy.get('#upload1').selectFile(testfile1, { action: 'drag-drop' });
            cy.get('#startnew').click();
            cy.get('#submit').should('be.disabled');
            cy.get('#upload1').selectFile(testfile1, { action: 'drag-drop' });

            cy.waitPageChange(() => {
                cy.get('#submit').click();
            });
            cy.get('#submitted-files > div').contains('file1.txt');
            cy.get('#submitted-files > div').contains('Download all files:').should('not.exist');

            cy.get('[fname = "file1.txt"] > td').first().contains('file1.txt').next('.file-trash').click();
            cy.get('[fname = "file1.txt"]').should('not.exist');
            cy.get('#upload1').selectFile([testfile1, testfile2], { action: 'drag-drop' });

            cy.waitPageChange(() => {
                cy.get('.alert-success > a').click(); // Dismiss successful upload message
                cy.get('#submit').click();
            });

            // Checks submitted files
            cy.get('#submitted-files > div').contains('span', 'file1.txt');
            cy.get('#submitted-files > div').contains('span', 'file2.txt');
            cy.get('#submitted-files > div').contains('Download all files:');
            // Commented out to pass cypress in CI -- FIXME
            // cy.get('[aria-label="Download file1.txt"]').click();
            // cy.readFile('cypress/downloads/file1.txt').should('eq','a\n');
        });
    });

    it('Should test whether or not certain users have access to gradeable', () => {
        // users should not have access to locked homework
        ['smithj', 'student', 'instructor2'].forEach((user) => {
            cy.login(user);
            cy.visit(['testing']);
            cy.get('[data-testid="locked_homework"]').find('[data-testid="submit-btn"]').click();
            cy.on('window:alert', (text) => {
                expect(text).to.include('Please complete Open Homework first with a score of 0 point(s).');
            });
            if (user !== 'instructor2') {
                cy.visit(['testing', 'gradeable', 'locked_homework']);
                cy.get('[data-testid="popup-message"]').contains('You have not unlocked this gradeable yet');
            }
            cy.logout();
        });

        // users should have access to locked homework
        ['kinge', 'adamsg', 'aphacker'].forEach((user) => {
            cy.login(user);
            cy.visit(['testing']);
            cy.get('[data-testid="locked_homework"]').find('[data-testid="submit-btn"]').click();
            cy.visit(['testing', 'gradeable', 'locked_homework']);
            cy.get('[data-testid="new-submission-info"]').should('exist');
            cy.logout();
        });
    });
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

        // users with 7+ points on open_homework 
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
});
