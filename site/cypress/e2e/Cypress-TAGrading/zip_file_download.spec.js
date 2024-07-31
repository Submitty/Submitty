import path from 'path';
describe('download zip file', () => {
    const downloadsFolder = Cypress.config('downloadsFolder');
    it('downloading and verifying the file', () => {
        cy.login();
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=hG1b13ljpDjKu32&sort=id&direction=ASC']);
        cy.get('body').type('{A}');
        cy.get('body').type('{O}'); // Solution/Notes
        cy.get('[data-testid="download-zip-file"]').should('contain', 'Download Zip File');
        cy.get('[data-testid="download-zip-file"]').click();
        const filename = path.join(downloadsFolder, 'grading_homework_hG1b13ljpDjKu32_v1.zip');
        cy.readFile(filename);
    });
});
