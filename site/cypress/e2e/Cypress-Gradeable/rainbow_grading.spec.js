describe('Test Rainbow Grading', () => {
    beforeEach(() => {
        cy.login('instructor');
        cy.visit(['sample', 'config']);

    });
    it('Enable viewing of rainbow grades and generating the rainbow grading', () => {
        // enabled the rainbow grades
        cy.get('[data-testid="display-rainbow-grades-summary"]').should('be.visible').and('not.be.checked');
        cy.get('[data-testid="display-rainbow-grades-summary"]').should('not.be.checked'); // remove one of this
        cy.visit(['sample', 'grades']);
        cy.get('[data-testid="rainbow-grades"]').should('contain', 'No grades are available...');
        cy.visit(['sample', 'config']);
        cy.get('[data-testid="display-rainbow-grades-summary"]').check();
        cy.get('[data-testid="display-rainbow-grades-summary"]').should('be.checked');
        // generating the rainbow grading
        cy.visit(['sample', 'reports', 'rainbow_grades_customization']);
        cy.get('#display_grade_summary').then(($checkbox) => {
            if (!$checkbox.prop('checked')) {
                cy.get('#display_grade_summary').check();
            }
        });
        cy.get('#display_grade_summary').should('be.checked');
        cy.get('#display_benchmarks_average').check();
        cy.get('#display_benchmarks_stddev').check();
        cy.get('#display_benchmarks_perfect').check();
        cy.get('[data-testid="save-status-button"]').click();
        cy.get('[data-testid="save-status"]', { timeout: 500000 }).should('contain', 'Rainbow grades successfully generated!');
        cy.visit(['sample', 'grades']);
        ['USERNAME', 'NUMERIC ID', 'AVERAGE', 'STDDEV', 'PERFECT'].forEach((fields) => {
            cy.get('[data-testid="rainbow-grades"]').should('contain', fields);
        });
        cy.get('[data-testid="rainbow-grades"]').should('contain', 'Information last updated');
        ['instructor', 'ta', 'student', 'grader'].forEach((username) => {
            cy.logout();
            cy.login(username);
            console.log(username);
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
    });
});

const checkRainbowGrades = (numericId, firstName) => {
    [numericId, firstName].forEach((value) => {
        cy.get('[data-testid="rainbow-grades"]').should('contain', value);
    });
};

