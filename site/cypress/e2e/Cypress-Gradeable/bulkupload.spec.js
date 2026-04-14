/*describe('Test cases revolving around non bulk uploading', () => {
    ['ta', 'instructor'].forEach((user) => {
        it(`${user} should have grader submission options`, () => {
            cy.login(user);
            cy.visit(['sample', 'gradeable', 'grading_homework_pdf']);
            cy.get('[data-testid="radio-student-upload"]').click();
            cy.get('[data-testid="submit-student-userid"]').should('be.visible');
            cy.get('#submission-mode-warning > .warning').should('have.text', 'Warning: Submitting files for a student!');
        });
    });
});

describe('Test cases revolving around bulk uploading', () => {
    ['instructor'].forEach((user) => {
        it(`${user} should have options to bulk upload by QR code, pages, and assign them to students`, () => {
            cy.login(user);
            cy.visit(['sample', 'gradeable', 'bulk_upload_test']);

            // Grader submission options
            cy.get('[data-testid="radio-student-upload"]').click();
            cy.get('[data-testid="submit-student-userid"]').should('be.visible');
            cy.get('#submission-mode-warning > .warning').should('have.text', 'Warning: Submitting files for a student!');

            cy.get('[data-testid="radio-bulk-upload"]').click();
            cy.get('[data-testid="split-by-qr-code"]').should('be.visible');
            cy.get('[data-testid="split-by-page-count"]').should('be.visible');
            cy.get('#submission-mode-warning > .warning').should('have.text', 'Warning: Submitting files for bulk upload!');

            // split by page count
            cy.get('[data-testid="split-by-page-count"]').type('1');

            // Bulk upload
            cy.get('[data-testid="select-files"]').selectFile('cypress/fixtures/bulk_upload.pdf', { force: true });
            cy.get('[data-testid="submit-gradeable"]').click();
            cy.get('[data-testid="assign-box"]').contains('2 files ready to assign', { timeout: 10000 });
            // reload to load the new files
            cy.reload();
            cy.get('[data-testid="bulk-delete-all"]').click();
            cy.get('[data-testid="assign-box"]').contains('0 files ready to assign', { timeout: 10000 });

            // Bulk upload with QR Code
            cy.get('[data-testid="split-by-qr-code"]').check();
            cy.get('[data-testid="select-files"]').selectFile('cypress/fixtures/bulk_upload_qr.pdf', { force: true });
            cy.get('[data-testid="submit-gradeable"]').click();
            cy.get('[data-testid="assign-box"]').contains('2 files ready to assign', { timeout: 10000 });
            cy.reload();
            cy.get('[data-testid="bulk-delete-all"]').click();
            cy.get('[data-testid="assign-box"]').contains('0 files ready to assign', { timeout: 10000 });

            // Bulk upload with QR code, detect and assign a test to studentID
            cy.get('[data-testid="select-files"]').selectFile('cypress/fixtures/bulk_upload_qr.pdf', { force: true });
            cy.get('[data-testid="submit-gradeable"]').click();
            cy.get('[data-testid="assign-box"]').contains('2 files ready to assign', { timeout: 10000 });
            cy.reload();
            cy.get('[data-testid="bulk-user-id-1"]').should('have.value', 'adamsg');
            // assign adamsg their test
            cy.get('[data-testid="bulk-submit-1"]').click();
            cy.get('[data-testid="bulk-delete-all"]').click();
            cy.get('[data-testid="assign-box"]').contains('0 files ready to assign', { timeout: 10000 });

            // confirm that adamsg has a test
            cy.visit(['sample', 'gradeable', 'bulk_upload_test', 'grading', 'details']);
            cy.get('[data-testid="view-sections"]').click();
            cy.get('[data-testid="grade-table"]').contains('adamsg').parent().get('[data-testid="grade-button"]').contains('Grade');
        });
    });
});*/
describe('Mentor visibility of upload.pdf for bulk uploaded exams', () => {

    it('upload.pdf visibility should follow blind grading and page assignment rules', () => {

        // Instructor uploads exam
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'bulk_upload_test']);

        cy.get('[data-testid="radio-student-upload"]').click();
        cy.get('[data-testid="submit-student-userid"]').type('student');
        cy.get('#submission-mode-warning > .warning').should('contain', 'Submitting files for a student');

        cy.get('[data-testid="select-files"]').selectFile('cypress/fixtures/bulk_upload.pdf', { force: true });
        cy.get('[data-testid="submit-gradeable"]').click();

        cy.get('[data-testid="assign-box"]', { timeout: 10000 }).should('exist');


        // Enable blind grading for mentors
        cy.visit(['sample', 'gradeable', 'bulk_upload_test', 'update']);

        cy.contains('Grader Assignment').click();

        cy.get('#blind_limited_access_grading').click();
        cy.logout();

        // Mentor checks submissions
        cy.login('grader');

        cy.visit(['sample', 'gradeable', 'bulk_upload_test', 'grading', 'details']);

        cy.get('[data-testid="agree-popup-btn"]').scrollIntoView().click();
        cy.get('[data-testid="grade-table"]', { timeout: 10000 }).should('be.visible');
        cy.get('[data-testid="grade-table"]')
            .find('[data-testid="grade-button"]')
            .contains('Grade')
            .first()
            .click();

        cy.get('[data-testid="show-submission"]').click();
        cy.get('#submissions').click();

        cy.contains('upload.pdf').should('not.exist');

        cy.logout();
        cy.wait(5000);

        // Enable page assignments
        cy.login('instructor');

        cy.visit(['sample', 'gradeable', 'bulk_upload_test', 'update']);

        cy.contains('Rubric').click();

        cy.get('#yes_pdf_page').click();

        cy.logout();
        cy.wait(5000);


        // Mentor checks again
        cy.login('grader');

        cy.visit(['sample', 'gradeable', 'bulk_upload_test', 'grading', 'details']);
        cy.get('[data-testid="grade-table"]', { timeout: 10000 }).should('be.visible');
        cy.get('[data-testid="grade-table"]')
            .find('[data-testid="grade-button"]')
            .contains('Grade')
            .first()
            .click();

        cy.get('#submissions').click();
        cy.contains('upload.pdf').should('not.exist');
        cy.logout();

        // Disable both conditions
        cy.login('instructor');

        cy.visit(['sample', 'gradeable', 'bulk_upload_test', 'update']);

        cy.contains('Grader Assignment').click();
        cy.get('#unblind_limited_access_grading').click();

        cy.contains('Rubric').click();
        cy.get('#no_pdf_page').click();
        cy.logout();
        cy.wait(5000);
        
        // Mentor now sees upload.pdf
        cy.login('grader');
        cy.visit(['sample', 'gradeable', 'bulk_upload_test', 'grading', 'details']);
        cy.get('[data-testid="grade-table"]', { timeout: 10000 }).should('be.visible');

        cy.get('[data-testid="grade-table"]')
            .find('[data-testid="grade-button"]')
            .contains('Grade')
            .first()
            .click();

        cy.get('#submissions').click();
        cy.contains('upload.pdf').should('exist');

    });

});
