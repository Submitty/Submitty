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
        cy.wait(50);

        // Customize Rainbow grades
        cy.visit(['sample', 'reports', 'rainbow_grades_customization']);
        cy.get('#save_status').should('contain', 'No changes to save');
        cy.get('#display_grade_summary').then(($checkbox) => {
            if (!$checkbox.prop('checked')) {
                cy.get('#display_grade_summary').click();
            }
        });
        cy.get('#display_grade_summary').should('be.checked');
        cy.get('#save_status_button').click();
        cy.wait(50);

        cy.get('#display_grade_summary').should('be.checked');
        cy.get('#display_grade_details').click();
        cy.get('#display_section').click();
        cy.get('#display_benchmark_percent').click();
        cy.get('#display_final_grade').click();
        cy.get('#display_benchmarks_average').click();
        cy.get('#save_status_button').click();
        cy.wait(20000);
        cy.get('#save_status').should('contain', 'Rainbow grades successfully generated!', { timeout: 50000 });

        // Check for instructor
        cy.visit(['sample', 'grades']);
        cy.get('#rainbow-grades').should('contain', 'USERNAME');
        cy.get('#rainbow-grades').should('contain', 'instructor');
        cy.get('#rainbow-grades').should('contain', 'NUMERIC ID');
        cy.get('#rainbow-grades').should('contain', '801516157');
        cy.get('#rainbow-grades').should('contain', 'FIRST');
        cy.get('#rainbow-grades').should('contain', 'Quinn');
        cy.get('#rainbow-grades').should('contain', 'LAST');
        cy.get('#rainbow-grades').should('contain', 'Instructor');
        cy.get('#rainbow-grades').should('contain', 'AVERAGE');
        cy.get('#rainbow-grades').should('contain', 'OVERALL');
        cy.get('#rainbow-grades').should('contain', 'FINAL GRADE');
        cy.get('#rainbow-grades').should('contain', 'Lecture Participation Polls for: instructor');
        cy.logout();

        // Check for student
        cy.login('student');
        cy.visit(['sample', 'grades']);
        cy.get('#rainbow-grades').should('contain', 'USERNAME');
        cy.get('#rainbow-grades').should('contain', 'student');
        cy.get('#rainbow-grades').should('contain', 'NUMERIC ID');
        cy.get('#rainbow-grades').should('contain', '410853871');
        cy.get('#rainbow-grades').should('contain', 'FIRST');
        cy.get('#rainbow-grades').should('contain', 'Joe');
        cy.get('#rainbow-grades').should('contain', 'LAST');
        cy.get('#rainbow-grades').should('contain', 'Student');
        cy.get('#rainbow-grades').should('contain', 'AVERAGE');
        cy.get('#rainbow-grades').should('contain', 'OVERALL');
        cy.get('#rainbow-grades').should('contain', 'FINAL GRADE');
        cy.get('#rainbow-grades').should('contain', 'Lecture Participation Polls for: student');
    });
});
