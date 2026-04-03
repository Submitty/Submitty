describe('Tests leaderboard access', () => {
    before(() => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'leaderboard', 'update']);
        cy.get('#page_5_nav').click();
        cy.get('[data-testid="submission-open-date"]').clear();
        cy.get('[data-testid="submission-open-date"]').type('2100-01-15 23:59:59');
        // clicks out of the calendar and save
        cy.get('body').click(0, 0);
        cy.get('#save_status', { timeout: 10000 }).should('have.text', 'All Changes Saved');
    });

    it('Should check if leaderboard is accessible to users', () => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'leaderboard']);
        cy.get('[data-testid="invalid-gradeable"]').should('not.exist');
        cy.visit(['sample', 'gradeable', 'leaderboard']);
        cy.get('[data-testid="invalid-gradeable"]').should('not.exist');
        cy.logout();

        cy.login('ta');
        cy.visit(['sample', 'gradeable', 'leaderboard']);
        cy.get('[data-testid="invalid-gradeable"]').should('not.exist');
        cy.visit(['sample', 'gradeable', 'leaderboard']);
        cy.get('[data-testid="invalid-gradeable"]').should('not.exist');
        cy.logout();

        cy.login('grader');
        cy.visit(['sample', 'gradeable', 'leaderboard']);
        cy.get('[data-testid="invalid-gradeable"]').should('not.exist');

        cy.visit(['sample', 'gradeable', 'leaderboard']);
        cy.get('[data-testid="invalid-gradeable"]').should('not.exist');

        cy.logout();
        cy.login('student');
        cy.visit(['sample', 'gradeable', 'leaderboard']);
        cy.get('[data-testid="invalid-gradeable"]').should('exist');

        cy.visit(['sample', 'gradeable', 'leaderboard']);
        cy.get('[data-testid="invalid-gradeable"]').should('exist');
    });

    it('Should correctly handle tied rankings on the leaderboard', () => {
        // Re-open the gradeable so students can submit
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'leaderboard', 'update']);
        cy.get('#page_5_nav').click();
        cy.get('[data-testid="submission-open-date"]')
            .clear()
            .type('2000-01-15 23:59:59{enter}') 
            .blur(); 
        cy.get('#save_status', { timeout: 10000 }).should('have.text', 'All Changes Saved');
        cy.logout();

        const testCode = '#include <iostream>\nint main() { return 0; }';

        // Student submission
        cy.login('student');
        cy.visit(['sample', 'gradeable', 'leaderboard']);
        cy.get('#upload_file').attachFile({
            fileContent: testCode,
            fileName: 'submission.cpp',
            mimeType: 'text/plain',
        });
        cy.get('#submit').click();
        cy.get('#submit_status', { timeout: 10000 }).should('contain', 'Submission Complete');
        cy.logout();

        // Aphacker submission (different user, same code)
        cy.login('aphacker');
        cy.visit(['sample', 'gradeable', 'leaderboard']);
        cy.get('#upload_file').attachFile({
            fileContent: testCode,
            fileName: 'submission.cpp',
            mimeType: 'text/plain',
        });
        cy.get('#submit').click();
        cy.get('#submit_status', { timeout: 10000 }).should('contain', 'Submission Complete');
        cy.logout();

        // Instructor visits the leaderboard to verify rankings
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'leaderboard', 'leaderboard']);

        // Navigate to the "Compilation" leaderboard which judges compile time (very fast for this test)
        cy.get('#compilation_nav').click();

        // Wait for both submissions to be autograded and appear on the leaderboard
        // eslint-disable-next-line no-restricted-syntax
        cy.waitAndReloadUntil(() => {
            return cy.get('table').then(($table) => {
                const text = $table.text();
                return text.includes('student') && text.includes('aphacker');
            });
        }, 120000, 5000);

        // Verify that they are together in the leaderboard and share the same rank
        // We find the rows for our two test students
        cy.contains('td', 'student').parent().find('.row_number').then(($el1) => {
            const rank1 = $el1.text().trim();
            cy.contains('td', 'aphacker').parent().find('.row_number').should('contain', rank1);
        });
    });
});
