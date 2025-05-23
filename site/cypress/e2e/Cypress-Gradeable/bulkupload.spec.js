describe('Test cases revolving around non bulk uploading', () => {
    ['ta', 'instructor'].forEach((user) => {
        it(`${user} should have grader submission options`, () => {
            cy.login(user);
            cy.visit(['sample', 'gradeable', 'grading_homework_pdf']);
            cy.get('#radio-student').click();
            cy.get('#user_id').should('be.visible');
            cy.get('#submission-mode-warning > .warning').should('have.text', 'Warning: Submitting files for a student!');
        });
    });
});

describe('Test cases revolving around bulk uploading', () => {
    ['instructor'].forEach((user) => {
        it(`${user} should have grader submission options, be able to upload, split by QR code, split by page count, check link to student in grading interface, and delete all pdfs`, () => {
            cy.login(user);
            cy.visit(['sample', 'gradeable', 'bulk_upload_test']);

            // Grader submission options
            cy.get('#radio-student').click();
            cy.get('#user_id').should('be.visible');
            cy.get('#submission-mode-warning > .warning').should('have.text', 'Warning: Submitting files for a student!');

            cy.get('#radio-bulk').click();
            cy.get('#use-qr').should('be.visible');
            cy.get('#num_pages').should('be.visible');
            cy.get('#submission-mode-warning > .warning').should('have.text', 'Warning: Submitting files for bulk upload!');

            // Delete any existing pdfs
            cy.get('#bulk_delete_all').click();

            // Split by page count
            cy.get('#radio-bulk').click();
            cy.get('#num_pages').type('1');

            // Bulk upload
            cy.get('#input-file1').selectFile('cypress/fixtures/bulk_upload.pdf', { force: true });
            cy.get('#submit').click();

            // Bulk upload with QR Code
            cy.get('#radio-bulk').click();
            cy.get('#use-qr').check();
            cy.get('#input-file1').selectFile('cypress/fixtures/bulk_upload_qr.pdf', { force: true });
            cy.get('#submit').click();

            // Check link to student in grading interface
            cy.visit(['sample', 'gradeable', 'bulk_upload_test', 'grading', 'details']);
            cy.get('[data-testid="view-sections"]').click();
            cy.get('[data-testid="grade-button"]').first().click();
            cy.get('[data-testid="student-info"]').should('contain', 'student');

            // Delete all pdfs
            cy.visit(['sample', 'gradeable', 'bulk_upload_test', 'grading', 'details']);
            cy.get('#bulk_delete_all').click();
        });

        it(`${user} should be able to submit uploads and link to student in grading interface`, () => {
            cy.login(user);
            cy.visit(['sample', 'gradeable', 'bulk_upload_test']);
        });
    });
});
