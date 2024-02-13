describe('Test cases for the rainbow grades page', () => {

    it('test Display Rainbow Grades Summary button', () => {
        cy.visit(['development', 'config']);
        cy.login('instructor');
        cy.get('#display-rainbow-grades-summary').then(($checkbox) => {
            if (!$checkbox.prop('checked')) {
            cy.get('#display-rainbow-grades-summary').click();
            }
        });
        cy.get('#display-rainbow-grades-summary').should('be.checked');
        
        cy.login('student');
        cy.visit(['development', 'grades']);
        cy.get('#nav-sidebar-grades').should('exist');
        cy.get('#rainbow-grades').should('contain','No grades are available...');
    });
        
    it('test Rainbow Grades only summary', () => {
        cy.visit(['sample', 'config']);
        cy.login('instructor');
        cy.get('#display-rainbow-grades-summary').then(($checkbox) => {
            if (!$checkbox.prop('checked')) {
            cy.get('#display-rainbow-grades-summary').click();
            }
        });
        cy.get('#display-rainbow-grades-summary').should('be.checked');

        cy.visit(['sample', 'reports' , 'rainbow_grades_customization']);
        cy.get('#save_status').should('contain', 'No changes to save');
        cy.get('#display_grade_summary').then(($checkbox) => {
            if (!$checkbox.prop('checked')) {
            cy.get('#display_grade_summary').click();
            }
        });
        cy.get('#display_grade_summary').should('be.checked');
        cy.get('#save_status_button').click();
        cy.get('#save_status').should('contain', 'Rainbow grades successfully generated!', { timeout: 50000 });

        cy.visit(['sample', 'grades']);
        cy.get('#rainbow-grades').should('contain', 'Information last updated: ');
        cy.get('#rainbow-grades').should('contain', 'USERNAME');
        cy.get('#rainbow-grades').should('contain', 'instructor');
        cy.get('#rainbow-grades').should('contain', 'NUMERIC ID');
        cy.get('#rainbow-grades').should('contain', '801516157');
        cy.get('#rainbow-grades').should('contain', 'FIRST');
        cy.get('#rainbow-grades').should('contain', 'Quinn');
        cy.get('#rainbow-grades').should('contain', 'LAST');
        cy.get('#rainbow-grades').should('contain', 'Instructor');
        cy.get('#rainbow-grades').should('contain', 'OVERALL');
        cy.get('#rainbow-grades').should('contain', '* = 1 late day used');
        cy.get('#rainbow-grades').should('contain', 'Lecture Participation Polls for: instructor');
        cy.get('#rainbow-grades').should('contain', 'IMPORTANT:');
        cy.get('#rainbow-grades').should('contain', 'Late days must be earned before the homework due date.');
        cy.logout();

        cy.login('student');
        cy.visit(['sample', 'grades']);
        cy.get('#rainbow-grades').should('contain', 'Information last updated: ');
        cy.get('#rainbow-grades').should('contain', 'USERNAME');
        cy.get('#rainbow-grades').should('contain', 'student');
        cy.get('#rainbow-grades').should('contain', 'NUMERIC ID');
        cy.get('#rainbow-grades').should('contain', '410853871');
        cy.get('#rainbow-grades').should('contain', 'FIRST');
        cy.get('#rainbow-grades').should('contain', 'Joe');
        cy.get('#rainbow-grades').should('contain', 'LAST');
        cy.get('#rainbow-grades').should('contain', 'Student');
        cy.get('#rainbow-grades').should('contain', 'OVERALL');
        cy.get('#rainbow-grades').should('contain', '* = 1 late day used');
        cy.get('#rainbow-grades').should('contain', 'Lecture Participation Polls for: student');
        cy.get('#rainbow-grades').should('contain', 'IMPORTANT:');
        cy.get('#rainbow-grades').should('contain', 'Late days must be earned before the homework due date.');
    });
});