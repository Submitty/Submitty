describe('Test cases revolving around uploading a classlist on the Manage Students page', () => {
    // id of new student used for testing
    const NEW_STUDENT = 'cypress';

    function cyAssertRegistrationSection(user, section) {
        cy.get(`[data-testid="user-row-${user}"] [data-testid="registration-section"]`)
            .invoke('text')
            .invoke('replace', /\s+/g, '') // strips whitespace characters
            .should('equal', section);
    }

    function uploadClasslist(fixture) {
        cy.visit(['sample', 'users']);
        cy.get('[data-testid="upload-classlist-button"]').click();
        cy.get('[data-testid="popup-window"]').should('be.visible');
        cy.get('[data-testid="classlist-upload-file"]').selectFile(`cypress/fixtures/upload_classlist/${fixture}`, { force: true });
        cy.get('[data-testid="submit-classlist-upload"]').click();
    }

    function assertStudentRow(section) {
        cy.get(`[data-testid="user-row-${NEW_STUDENT}"] .td-given-name`).should('have.text', 'cypress');
        cy.get(`[data-testid="user-row-${NEW_STUDENT}"] .td-family-name`).should('have.text', 'cypress');
        cyAssertRegistrationSection(NEW_STUDENT, section);
    }

    before(() => {
        // Log in to set up the initial state
        cy.login('instructor');
    });

    after(() => {
        // Clean up the test student
        cy.login('instructor');
        cy.visit(['sample', 'users']);
        cy.get('body').then(($body) => {
            if ($body.find(`[data-testid="delete-student-${NEW_STUDENT}-button"]`).length > 0) {
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

    it('Should upload classlists to add, update, and validate student data', () => {
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
        assertStudentRow('1');

        // Test updating an existing student via classlist upload
        uploadClasslist('upload_update_student.csv');
        cy.get('[data-testid="popup-message"]')
            .should('contain.text', 'upload_update_student.csv')
            .and('contain.text', '0 added, 1 updated');
        assertStudentRow('2');
    });
});
