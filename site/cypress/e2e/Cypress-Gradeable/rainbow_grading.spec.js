describe('Test Rainbow Grading', () => {
    beforeEach(() => {
        cy.login('instructor');
        cy.visit(['sample', 'config']);

    });
    it('Enable viewing of rainbow grades and generating the rainbow grading', () => {
        cy.get('[data-testid="display-rainbow-grades-summary"]').check();
        cy.get('[data-testid="display-rainbow-grades-summary"]').should('be.checked');
        cy.visit(['sample', 'reports', 'rainbow_grades_customization']);
        cy.get('[data-testid="display-grade-summary"]').check();
        cy.get('[data-testid="display-grade-summary"]').should('be.checked');
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
            if (username==='instructor') {
                checkRainbowGrades(801516157, 'Quinn');
            }
            else if (username === 'ta') {
                checkRainbowGrades(281179137, 'Jill');
            }
            else if (username === 'student') {
                checkRainbowGrades(410853871, 'Joe');
            }
            else if (username === 'grader') {
                checkRainbowGrades(10306042, 'Tim');
            }
        });
        cy.visit(['sample', 'config']);
        cy.get('[data-testid="display-rainbow-grades-summary"]').uncheck();
        cy.get('[data-testid="display-rainbow-grades-summary"]').should('not.be.checked');
        cy.visit(['sample', 'grades']);
        cy.get('[data-testid="rainbow-grades"]').should('contain', 'No grades are available...');
    });
});

const checkRainbowGrades = (numericId, firstName) => {
    [numericId, firstName].forEach((value) => {
        cy.get('[data-testid="rainbow-grades"]').should('contain', value);
    });
};

