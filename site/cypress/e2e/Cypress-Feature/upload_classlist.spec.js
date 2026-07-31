describe('Test cases revolving around uploading a classlist on the Manage Students page', () => {
    // id of new student used for testing
    const NEW_STUDENT = 'cypress';

    function assertStudentRow(studentId, section) {
        // check that the student appears in the table as expected
        cy.get(`[data-testid="user-row-${studentId}"]`).within(() => {
            // check the first & last name
            cy.get('[data-testid="student-given-name"]').should('contain.text', 'cypress');
            cy.get('[data-testid="student-family-name"]').should('contain.text', 'cypress');

            // check the registration section
            cy.get('[data-testid="registration-section"]')
                .invoke('text')
                .invoke('replace', /\s+/g, '') // strips whitespace characters
                .should('equal', section);
        });
    }

    function uploadClasslist(fixture) {
        cy.get('[data-testid="upload-classlist-button"]').click();
        cy.get('[data-testid="popup-window"]').should('be.visible');
        cy.get('[data-testid="classlist-upload-file"]').selectFile(`cypress/fixtures/upload_classlist/${fixture}`, { force: true });
        cy.get('[data-testid="submit-classlist-upload"]').click();
    }

    it('Should upload classlists to add, update, and validate student data', () => {
        cy.login('instructor');
        cy.visit(['sample', 'users']);
        // Test the "User ID" header being required
        uploadClasslist('upload_missing_userid_header.csv');
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Missing "User ID" column in uploaded CSV');
        cy.get(`[data-testid="user-row-${NEW_STUDENT}"]`).should('not.exist');

        // Test adding a new student via classlist upload
        uploadClasslist('upload_new_student.csv');
        cy.get('[data-testid="popup-message"]')
            .should('contain.text', 'upload_new_student.csv')
            .and('contain.text', '1 added, 0 updated');
        cy.get(`[data-testid="user-row-${NEW_STUDENT}"]`).should('exist');
        assertStudentRow(NEW_STUDENT, '1');

        // Test updating an existing student via classlist upload
        uploadClasslist('upload_update_student.csv');
        cy.get('[data-testid="popup-message"]')
            .should('contain.text', 'upload_update_student.csv')
            .and('contain.text', '0 added, 1 updated');
        assertStudentRow(NEW_STUDENT, '2');
    });

    after(() => {
        // Clean up the test student
        cy.login('instructor');
        cy.visit(['sample', 'users']);
        cy.get('[data-testid="student-table"]').should('be.visible').then(($table) => {
            if ($table.find(`[data-testid="delete-student-${NEW_STUDENT}-button"]`).length > 0) {
                cy.get(`[data-testid="delete-student-${NEW_STUDENT}-button"]`).click();
                cy.get('[data-testid="confirm-delete-button"]').click();
                cy.get('[data-testid="popup-message"]').should('contain.text', 'has been removed from your course');
            }
        });
        // This wait is necessary due to the JS $.ajax request, as if the logout request is sent too quickly,
        // the login page is sent to the $.ajax request instead of the accurate data. For some reason waiting for an intercepted route
        // does not work here, when it works below. (comment copied from registration.spec.js)
        // eslint-disable-next-line cypress/no-unnecessary-waiting
        cy.wait(500);
        cy.logout('instructor');
    });
});
