describe('Tests leaderboard access', () => {
    const updateSubmissionOpenDate = (date) => {
        cy.intercept('POST', '**/courses/*/sample/gradeable/leaderboard/update').as('saveGradeableUpdate');
        cy.get('[data-testid="submission-open-date"]').clear({ force: true }).invoke('val', date).trigger('change', { force: true });
        cy.get('body').click(0, 0);
        cy.wait('@saveGradeableUpdate', { timeout: 30000 }).then((interception) => {
            expect(interception.response.statusCode).to.eq(200);
        });
        // Validate the saved value directly from the form after reload to avoid flaky transient status text checks.
        cy.reload();
        cy.get('#page_5_nav').click();
        cy.get('[data-testid="submission-open-date"]').should('have.value', date);
    };

    const submitLeaderboardCode = (user, testCode) => {
        cy.login(user);
        cy.visit(['sample', 'gradeable', 'leaderboard']);
        cy.get('#upload_file').attachFile(
            {
                fileContent: testCode,
                fileName: 'submission.cpp',
                mimeType: 'text/plain',
            },
            { force: true },
        );
        cy.intercept('POST', '**/courses/*/sample/gradeable/leaderboard/upload*').as('uploadSubmission');
        cy.get('#submit').click();
        cy.wait('@uploadSubmission', { timeout: 30000 }).then((interception) => {
            expect(interception.response.statusCode).to.eq(200);
            expect(interception.response.body.status).to.eq('success', `Upload failed: ${interception.response.body.message}`);
        });
        cy.logout();
    };

    before(() => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'leaderboard', 'update']);
        cy.get('#page_5_nav').click();
        updateSubmissionOpenDate('2100-01-15 23:59:59');
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
        // Keep date ordering valid before moving submission-open-date into the past.
        cy.intercept('POST', '**/courses/*/sample/gradeable/leaderboard/update').as('saveGradeableUpdateTA');
        cy.get('[data-testid="ta-view-start-date"]').clear({ force: true }).invoke('val', '1999-01-15 23:59:59').trigger('change', { force: true });
        cy.get('body').click(0, 0);
        cy.wait('@saveGradeableUpdateTA', { timeout: 30000 }).its('response.statusCode').should('eq', 200);
        cy.wait(1000);
        updateSubmissionOpenDate('2000-01-15 23:59:59');
        cy.logout();

        const testCode = '#include <iostream>\nint main() { return 0; }';

        // Student submission
        submitLeaderboardCode('student', testCode);

        // Aphacker submission (different user, same code)
        submitLeaderboardCode('aphacker', testCode);

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
