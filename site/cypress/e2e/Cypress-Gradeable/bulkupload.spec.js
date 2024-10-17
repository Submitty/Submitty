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
        it(`${user} should have grader submission options`, () => {
            cy.login(user);
            cy.visit(['sample', 'gradeable', 'bulk_upload_test']);
            cy.get('#radio-student').click();
            cy.get('#user_id').should('be.visible');
            cy.get('#submission-mode-warning > .warning').should('have.text', 'Warning: Submitting files for a student!');

            cy.get('#radio-bulk').click();
            cy.get('#use-qr').should('be.visible');
            cy.get('#num_pages').should('be.visible');
            cy.get('#submission-mode-warning > .warning').should('have.text', 'Warning: Submitting files for bulk upload!');
        });

        it(`${user} should be able to upload and split by QR code`, () => {
            cy.login(user);
            cy.visit(['sample', 'gradeable', 'bulk_upload_test']);
            cy.get('#radio-bulk').click();
            cy.get('#use-qr').check();
            cy.get('#bulk-upload-file').selectFile('cypress/fixtures/bulk_upload_qr.pdf', { force: true });
            cy.get('#bulk-upload-submit').click();
            cy.get('.alert-success').should('contain', 'Bulk upload successful');
        });

        it(`${user} should be able to upload and split by page count`, () => {
            cy.login(user);
            cy.visit(['sample', 'gradeable', 'bulk_upload_test']);
            cy.get('#radio-bulk').click();
            cy.get('#num_pages').type('2');
            cy.get('#bulk-upload-file').selectFile('cypress/fixtures/bulk_upload_page_count.pdf', { force: true });
            cy.get('#bulk-upload-submit').click();
            cy.get('.alert-success').should('contain', 'Bulk upload successful');
        });

        it(`${user} should be able to delete uploads`, () => {
            cy.login(user);
            cy.visit(['sample', 'gradeable', 'bulk_upload_test']);
            cy.get('#radio-bulk').click();
            cy.get('#bulk-upload-file').selectFile('cypress/fixtures/bulk_upload_qr.pdf', { force: true });
            cy.get('#bulk-upload-submit').click();
            cy.get('.alert-success').should('contain', 'Bulk upload successful');
            cy.get('#bulk-upload-delete').click();
            cy.get('.alert-success').should('contain', 'Bulk upload deleted');
        });

        it(`${user} should be able to submit uploads and link to student in grading interface`, () => {
            cy.login(user);
            cy.visit(['sample', 'gradeable', 'bulk_upload_test']);
            cy.get('#radio-bulk').click();
            cy.get('#use-qr').check();
            cy.get('#bulk-upload-file').selectFile('cypress/fixtures/bulk_upload_qr.pdf', { force: true });
            cy.get('#bulk-upload-submit').click();
            cy.get('.alert-success').should('contain', 'Bulk upload successful');
            cy.visit(['sample', 'gradeable', 'bulk_upload_test', 'grading', 'details']);
            cy.get('[data-testid="view-sections"]').click();
            cy.get('[data-testid="grade-button"]').first().click();
            cy.get('[data-testid="student-info"]').should('contain', 'student');
        });
    });
});
