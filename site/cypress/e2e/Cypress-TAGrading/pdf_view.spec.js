describe('Test cases involving pdf view and annotations', () => {
    
    beforeEach(() => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'grading_homework_pdf', 'grading', 'details']);
        cy.get('.btn').contains('View All').click();
        cy.get('#details-table').contains('mccule').siblings().eq(6).click();
        cy.get('#submission_browser_btn').click();
    });

    it('test the full panel view', () => {
        // this test is somewhat limited in what it can do because the page
        // displays an iframe which is inaccessible to Cypress for the most part
        // We just test that the full panel view opens, and that an iframe appears.

        cy.get('#submissions').click();
        cy.get('#viewer').should('not.exist');
        cy.get('[data-testid="open-file"]').eq(1).click();
        cy.get('#grading_file_name').should('contain', 'words_881.pdf');
        cy.get('#viewer').should('be.visible');
        cy.get('.textLayer').children().first().should('contain', 'A Simple PDF');

        cy.get('[aria-label="Collapse File"]').click();
        cy.get('#viewer').should('not.exist');
    });
});
