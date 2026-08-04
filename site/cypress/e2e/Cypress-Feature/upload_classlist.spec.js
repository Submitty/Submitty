import { getCurrentSemester } from '/cypress/support/utils.js';

describe('Test cases revolving around uploading a classlist on the Manage Students page', () => {
    // ID of new student used for testing
    const NEW_STUDENT = 'cypress';
    const FIXTURE_PATH = 'cypress/fixtures/upload_classlist';
    const DOWNLOAD_PATH = 'cypress/downloads';

    function assertStudentRow(studentId, section = 'NULL') {
        // Check that the student appears in the table as expected
        cy.get(`[data-testid="user-row-${studentId}"]`).within(() => {
            // Check the first & last name
            cy.get('[data-testid="student-given-name"]').should('contain.text', 'cypress');
            cy.get('[data-testid="student-family-name"]').should('contain.text', 'cypress');

            // Check the registration section
            cy.get('[data-testid="registration-section"]')
                .invoke('text')
                .invoke('replace', /\s+/g, '') // strips whitespace characters
                .should('equal', section);
        });
    }

    function uploadClasslist(filePath) {
        cy.get('[data-testid="upload-classlist-button"]').click();
        cy.get('[data-testid="popup-window"]').should('be.visible');
        cy.get('[data-testid="classlist-upload-file"]').selectFile(filePath, { force: true });
        cy.get('[data-testid="submit-classlist-upload"]').click();
    }

    function downloadAndAssertClasslist(filePath) {
        // Download the CSV
        cy.get('[data-testid="download-users-button"]').click();
        cy.get('[data-testid="popup-window"]').should('be.visible');
        cy.get('[data-testid="registration-section-null-checkbox"]').click();
        cy.get('[data-testid="submit-download-users"]').click();

        // Close the Download Users modal by pressing the Escape key
        cy.get('body').type('{esc}');
        cy.get('[data-testid="popup-window"]').should('not.be.visible');

        // path to the downloaded file
        const downloadedFilePath = `${DOWNLOAD_PATH}/${getCurrentSemester()}_sample_users_data.csv`;

        // verify the file exists
        cy.readFile(downloadedFilePath);

        // compare the file against the fixture with the expected values
        cy.readFile(`${FIXTURE_PATH}/${filePath}`).then((expectedCsv) => {
            cy.readFile(downloadedFilePath).then((downloadedCsv) => {
                // Normalize line endings (\r\n to \n) and trim whitespace
                const normalize = (csvString) => csvString.replace(/\r\n/g, '\n').trim();

                const expected = normalize(expectedCsv);
                const actual = normalize(downloadedCsv);

                // Assert that the entire file contents match exactly
                expect(actual).to.equal(expected);
            });
        });
    }

    it('Should upload classlists to add, update, and validate student data', () => {
        cy.login('instructor');
        cy.visit(['sample', 'users']);

        // Test the Download Users button
        downloadAndAssertClasslist('sample_users_data.csv');

        // Verify the Upload and Download features are compatible
        uploadClasslist(`${DOWNLOAD_PATH}/${getCurrentSemester()}_sample_users_data.csv`);
        cy.get('[data-testid="popup-message"]')
            .should('contain.text', `${getCurrentSemester()}_sample_users_data.csv`)
            .and('contain.text', '0 added, 139 updated');

        // Test the Upload Users button with the new student
        // Test the "User ID" header being required
        uploadClasslist(`${FIXTURE_PATH}/upload_missing_userid_header.csv`);
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Missing "User ID" column in uploaded CSV');
        cy.get(`[data-testid="user-row-${NEW_STUDENT}"]`).should('not.exist');

        // Test adding a new student via classlist upload
        uploadClasslist(`${FIXTURE_PATH}/upload_new_student.csv`);
        cy.get('[data-testid="popup-message"]')
            .should('contain.text', 'upload_new_student.csv')
            .and('contain.text', '1 added, 0 updated');
        cy.get(`[data-testid="user-row-${NEW_STUDENT}"]`).should('exist');
        assertStudentRow(NEW_STUDENT, '1');

        // Test updating an existing student via classlist upload
        uploadClasslist(`${FIXTURE_PATH}/upload_update_student.csv`);
        cy.get('[data-testid="popup-message"]')
            .should('contain.text', 'upload_update_student.csv')
            .and('contain.text', '0 added, 1 updated');
        assertStudentRow(NEW_STUDENT, '2');

        // Test the Download Users button with the new cypress student
        downloadAndAssertClasslist('sample_users_data_with_cypress.csv');

        // Test 'Move students missing from the classlist to NULL section'
        cy.get('[data-testid="upload-classlist-button"]').click();
        cy.get('[data-testid="popup-window"]').should('be.visible');
        cy.get('[data-testid="move-missing-checkbox"]').click();
        cy.get('[data-testid="classlist-upload-file"]').selectFile(`${FIXTURE_PATH}/sample_users_data.csv`, { force: true });
        cy.get('[data-testid="submit-classlist-upload"]').click();
        cy.get('[data-testid="popup-message"]').should('contain.text', '0 added, 140 updated');
        assertStudentRow(NEW_STUDENT);
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
