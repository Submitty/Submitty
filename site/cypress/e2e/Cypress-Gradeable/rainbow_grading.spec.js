import { skipOn } from '@cypress/skip-test';
skipOn(Cypress.env('run_area') === 'CI', () => {
    describe('Test Rainbow Grading', () => {
        beforeEach(() => {
            cy.login('instructor');
            cy.visit(['sample', 'config']);
        });
        it('Enable viewing of rainbow grades and generating the rainbow grading', () => {
            cy.get('[data-testid="display-rainbow-grades-summary"]').check();
            cy.visit(['sample', 'reports', 'rainbow_grades_customization']);
            cy.get('[data-testid="display-rainbow-grades-summary"]').should('be.checked');
            cy.get('[data-testid="display-grade-summary"]').check();
            cy.get('[data-testid="display-grade-summary"]').should('be.checked');
            cy.get('[data-testid="display-grade-details"]').check();
            cy.get('[data-testid="display-benchmarks-average"]').check();
            cy.get('[data-testid="display-benchmarks-stddev"]').check();
            cy.get('[data-testid="display-benchmarks-perfect"]').check();
            cy.get('[data-testid="save-status-button"]').click();
            cy.get('[data-testid="save-status"]', { timeout: 15000 }).should('contain', 'Rainbow grades successfully generated!');
            cy.visit(['sample', 'grades']);
            ['USERNAME', 'NUMERIC ID', 'AVERAGE', 'STDDEV', 'PERFECT'].forEach((fields) => {
                cy.get('[data-testid="rainbow-grades"]').should('contain', fields);
            });
            cy.get('[data-testid="rainbow-grades"]').should('contain', 'Information last updated');
            ['ta', 'student', 'grader', 'instructor'].forEach((username) => {
                cy.logout();
                cy.login(username);
                cy.visit(['sample', 'grades']);
                cy.get('[data-testid="rainbow-grades"]').should('contain', `Lecture Participation Polls for: ${username}`);
                if (username === 'instructor') {
                    checkRainbowGrades('instructor', 801516157, 'Quinn', 'Instructor');
                    checkRainbowGradesOption();
                }
                else if (username === 'ta') {
                    checkRainbowGrades('ta', 281179137, 'Jill', 'TA');
                    checkRainbowGradesOption();
                }
                else if (username === 'student') {
                    checkRainbowGrades('student', 'student', 410853871, 'Joe', 'Student');
                    checkRainbowGradesOption();
                }
                else if (username === 'grader') {
                    checkRainbowGrades('grader', 10306042, 'Tim', 'Grader');
                    checkRainbowGradesOption();
                }
            });
            cy.visit(['sample', 'config']);
            cy.get('[data-testid="display-rainbow-grades-summary"]').uncheck();
            cy.get('[data-testid="display-rainbow-grades-summary"]').should('not.be.checked');
            cy.visit(['sample', 'grades']);
            cy.get('[data-testid="rainbow-grades"]').should('contain', 'No grades are available...');
        });
    });
});
const checkRainbowGrades = (username, numericId, firstName, lastname) => {
    [username, numericId, firstName, lastname].forEach((value) => {
        cy.get('[data-testid="rainbow-grades"]').should('contain', value);
    });
};
const checkRainbowGradesOption = () => {
    ['USERNAME', 'NUMERIC ID', 'FIRST', 'LAST', 'OVERALL', 'AVERAGE', 'STDDEV', 'PERFECT'].forEach((element) => {
        cy.get('[data-testid="rainbow-grades"]').should('contain', element);
    });
};
