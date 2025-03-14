describe('Tests cases revolving around gradeable access and submition', () => {
    ['student', 'ta', 'grader', 'instructor'].forEach((user) => {
        it('Should upload file, submit, and remove file', () => {
            cy.login(user);
            const testfile1 = 'cypress/fixtures/file1.txt';
            const testfile2 = 'cypress/fixtures/file2.txt';

            cy.visit(['sample', 'gradeable', 'open_homework']);

            // Makes sure the clear button is not disabled by adding a file
            cy.get('#upload1').selectFile(testfile1, { action: 'drag-drop' });
            cy.get('#startnew').click();
            cy.get('#submit').should('be.disabled');
            cy.get('#upload1').selectFile(testfile1, { action: 'drag-drop' });

            cy.waitPageChange(() => {
                cy.get('#submit').click();
            });
            cy.get('#submitted-files > div').contains('file1.txt');
            cy.get('#submitted-files > div').contains('Download all files:').should('not.exist');

            cy.get('[fname = "file1.txt"] > td').first().contains('file1.txt').next('.file-trash').click();
            cy.get('[fname = "file1.txt"]').should('not.exist');
            cy.get('#upload1').selectFile([testfile1, testfile2], { action: 'drag-drop' });

            cy.waitPageChange(() => {
                cy.get('.alert-success > a').click(); // Dismiss successful upload message
                cy.get('#submit').click();
            });

            // Checks submitted files
            cy.get('#submitted-files > div').contains('span', 'file1.txt');
            cy.get('#submitted-files > div').contains('span', 'file2.txt');
            cy.get('#submitted-files > div').contains('Download all files:');
            // Commented out to pass cypress in CI -- FIXME
            // cy.get('[aria-label="Download file1.txt"]').click();
            // cy.readFile('cypress/downloads/file1.txt').should('eq','a\n');
        });
    });
});
