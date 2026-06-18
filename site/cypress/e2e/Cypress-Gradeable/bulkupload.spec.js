describe('Test cases revolving around non bulk uploading', () => {
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
});
