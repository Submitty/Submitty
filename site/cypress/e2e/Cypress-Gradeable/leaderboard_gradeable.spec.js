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
        cy.get('[data-testid="submission-open-date"]').clear();
        cy.get('[data-testid="submission-open-date"]').type('2000-01-15 23:59:59');
        cy.get('body').click(0, 0);
        cy.get('#save_status', { timeout: 10000 }).should('have.text', 'All Changes Saved');
        cy.logout();

        const testCode = [
            '#include <algorithm>',
            '#include <chrono>',
            '#include <iostream>',
            '#include <thread>',
            '#include <vector>',
            '',
            'int main() {',
            '    std::vector<int> values;',
            '    int value = 0;',
            '    while (std::cin >> value) {',
            '        values.push_back(value);',
            '    }',
            '    std::sort(values.begin(), values.end());',
            '    std::this_thread::sleep_for(std::chrono::milliseconds(600));',
            '    for (const int current : values) {',
            '        std::cout << current << \'\\n\';',
            '    }',
            '    return 0;',
            '}',
        ].join('\n');

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

        // Use the overall sorting leaderboard with a fixed sleep in the program so
        // the displayed points, time, and memory values deterministically tie in CI.
        cy.get('#general_sorting_nav').click();

        // Wait for both submissions to be autograded and appear on the leaderboard
        // eslint-disable-next-line no-restricted-syntax
        cy.waitAndReloadUntil(() => {
            return cy.get('table').then(($table) => {
                const text = $table.text();
                return text.includes('student') && text.includes('aphacker');
            });
        }, 120000, 5000);

        const getLeaderboardMetrics = (userId) => {
            return cy.contains('td', userId).parent().then(($row) => {
                const cells = $row.find('td');
                return {
                    rank: Cypress.$(cells[0]).text().trim(),
                    points: Cypress.$(cells[2]).text().trim(),
                    time: Cypress.$(cells[3]).text().trim(),
                    memory: Cypress.$(cells[4]).text().trim(),
                };
            });
        };

        // Verify that the tied entries show the same displayed metrics and rank.
        getLeaderboardMetrics('student').then((studentMetrics) => {
            getLeaderboardMetrics('aphacker').then((aphackerMetrics) => {
                expect(aphackerMetrics.points).to.equal(studentMetrics.points);
                expect(aphackerMetrics.time).to.equal(studentMetrics.time);
                expect(aphackerMetrics.memory).to.equal(studentMetrics.memory);
                expect(aphackerMetrics.rank).to.equal(studentMetrics.rank);
            });
        });
    });
});
