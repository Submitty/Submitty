const scores = [];
describe('Test revolving around the student side and whether or not they should see a version conflict', () => {
    it('Student should have a version conflict', () => {
        cy.login('student');
        cy.visit(['sample', 'gradeable', 'grades_released_homework_onlytaEC']);

        cy.get('[data-testid="version-conflict-version-box"]').should('contain', 'Note: The version you have selected to be graded is not the version graded by the instructor/TAs.');
        cy.get('[data-testid="version-conflict-version-box"]').should('contain', 'If the graded version does not match your selected version, a zero will be recorded in the gradebook.');

        cy.get('[data-testid="version-conflict-ta"]').should('exist');
        cy.get('[data-testid="version-conflict-ta"]').should('contain', 'has been detected in your submission. A grade of zero will be recorded in the gradebook. Please resolve as necessary.');

        cy.get('[data-testid="ta-results-box"').should('have.css', 'backgroundColor', 'rgb(217, 83, 79)');

        cy.get('[data-testid="score-pill-badge"]').each(($el, index) => {
            cy.wrap($el)
                .should('have.css', 'backgroundColor', 'rgb(136, 136, 136)')
                .then(() => {
                    scores.push($el.text());
                });
        });

        cy.get('[data-testid="ta-grade-results"]').each(($el, index) => {
            cy.wrap($el).should('contain', 'For Version #2');
        });
    });

    it('Change Submission Version', () => {
        cy.login('student');
        cy.visit(['sample', 'gradeable', 'grades_released_homework_onlytaEC']);
        cy.get('#submission-version-select').select('2');
        cy.get('#version_change').click();
    });

    it('Student should not have a version conflict', () => {
        cy.login('student');
        cy.visit(['sample', 'gradeable', 'grades_released_homework_onlytaEC']);

        cy.get('[data-testid="version-conflict-version-box"]').should('not.exist');
        cy.get('[data-testid="ta-results-box"').should('have.css', 'backgroundColor', 'rgb(255, 255, 255)');

        cy.get('[data-testid="score-pill-badge"]').each(($el, index) => {
            cy.wrap($el)
                .should('have.text', scores[index])
                .should('have.css', 'backgroundColor')
                .and('not.equal', 'rgb(136, 136, 136)');
        });
    });

    it('Change back Submission Version', () => {
        cy.login('student');
        cy.visit(['sample', 'gradeable', 'grades_released_homework_onlytaEC']);
        cy.get('#submission-version-select').select('1');
        cy.get('#version_change').click();
    });
});
