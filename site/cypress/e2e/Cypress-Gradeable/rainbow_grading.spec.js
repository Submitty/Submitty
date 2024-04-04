const checkRainbowGrades = (username, numericId, firstName, lastName) => {
    cy.get('#rainbow-grades').should('contain', 'USERNAME');
    cy.get('#rainbow-grades').should('contain', username);
    cy.get('#rainbow-grades').should('contain', 'NUMERIC ID');
    cy.get('#rainbow-grades').should('contain', numericId);
    cy.get('#rainbow-grades').should('contain', 'FIRST');
    cy.get('#rainbow-grades').should('contain', firstName);
    cy.get('#rainbow-grades').should('contain', 'LAST');
    cy.get('#rainbow-grades').should('contain', lastName);
    cy.get('#rainbow-grades').should('contain', 'AVERAGE');
    cy.get('#rainbow-grades').should('contain', 'OVERALL');
    cy.get('#rainbow-grades').should('contain', 'FINAL GRADE');
    cy.get('#rainbow-grades').should('contain', `Lecture Participation Polls for: ${username}`);
};

describe('Test that for sample- rainbow grades', () => {
    it('can be configured automatically', () => {
        // Enable
        cy.visit(['sample', 'config']);
        cy.login('instructor');
        cy.get('#display-rainbow-grades-summary').then(($checkbox) => {
            if (!$checkbox.prop('checked')) {
                cy.get('#display-rainbow-grades-summary').click();
            }
        });
        cy.get('#display-rainbow-grades-summary').should('be.checked');

        // Check for student
        cy.login('student');
        cy.visit(['sample']);
        cy.get('#nav-sidebar-grades').should('exist').click();
    });

    it('can generate grade summaries and customize what to show', () => {
        // Generate Grade summaries
        cy.visit(['sample', 'reports']);
        cy.login('instructor');
        cy.get('#grade-summaries-button').click();

        // Customize Rainbow grades
        cy.visit(['sample', 'reports', 'rainbow_grades_customization']);
        cy.get('#save_status', { timeout: 500000 }).should('contain', 'No changes to save');
        cy.get('#display_grade_summary').then(($checkbox) => {
            if (!$checkbox.prop('checked')) {
                cy.get('#display_grade_summary').click();
            }
        });
        cy.get('#display_grade_summary').should('be.checked');
        cy.get('#save_status_button').click();

        cy.get('#display_grade_summary').should('be.checked');
        cy.get('#display_grade_details').click();
        cy.get('#display_section').click();
        cy.get('#display_benchmark_percent').click();
        cy.get('#display_final_grade').click();
        cy.get('#display_benchmarks_average').click();
        cy.get('#save_status_button').click();
        cy.get('#save_status', { timeout: 500000 }).should('contain', 'Rainbow grades successfully generated!');

        // Check for instructor
        cy.visit(['sample', 'grades']);
        checkRainbowGrades('instructor', '801516157', 'Quinn', 'Instructor');
        cy.logout();

        // Check for student
        cy.login('student');
        cy.visit(['sample', 'grades']);
        checkRainbowGrades('student', '410853871', 'Joe', 'Student');
    });
});
