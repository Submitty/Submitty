import path from 'path';
describe('download zip file', () => {
    const downloadsFolder = Cypress.config('downloadsFolder');
    it('downloading and verifying the file', () => {
        cy.login();
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=ZEXxK8vS1X8q7k3&sort=id&direction=ASC']); // wisoza anon id
        cy.get('body').type('{A}');
        cy.get('body').type('{O}'); // Solution/Notes
        cy.get('[data-testid="download-zip-file"]').should('contain', 'Download Zip File');
        cy.get('[data-testid="download-zip-file"]').click();
        const filename = path.join(downloadsFolder, 'grading_homework_ZEXxK8vS1X8q7k3_v1.zip');
        cy.readFile(filename);
    });
});
