describe('Test cases involving the files panel', () => {
    // Constants for repeated values
    const studentName = 'Evie McCullough (mccule)';
    const submissionNumber = 'Submission Number: 2 / 3';

    beforeEach(() => {
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);
        cy.login('instructor');
        cy.get('.btn').contains('View All').click();
        cy.get('#details-table').contains('mccule').siblings().eq(6).click();
        cy.get('#student_info_btn').click();
        cy.get('.rubric-title').as('rubricTitle'); // Alias for rubric title
        cy.get('#submission-version-select').as('versionSelect'); // Alias for version select
    });

    it('test cancelling and reinstating assignment', () => {
        cy.get('@rubricTitle').should('contain', studentName);
        cy.get('@rubricTitle').should('contain', submissionNumber);

        cy.get('@versionSelect').children().should('have.length', 3);
        cy.get('@versionSelect').find(':selected').should('contain', 'Version #2');
        cy.get('@versionSelect').find(':selected').should('contain', 'GRADE THIS VERSION');
        cy.get('[value="Grade This Version"]').should('not.exist');

        cy.get('[value="Cancel Student Submission"]').click();
        cy.get('#bar_banner').should('contain', 'Cancelled Submission');
        cy.get('[value="Cancel Student Submission"]').should('not.exist');
        cy.get('[value="Grade This Version"]').should('not.exist');
        cy.get('@rubricTitle').should('contain', studentName);
        cy.get('@rubricTitle').should('contain', 'Submission Number: 0 / 3');

        cy.get('@versionSelect').select('Version #2');
        cy.get('[value="Cancel Student Submission"]').should('not.exist');
        cy.get('[value="Grade This Version"]').click();
        cy.get('@versionSelect').find(':selected').should('contain', 'Version #2');
        cy.get('@versionSelect').find(':selected').should('contain', 'GRADE THIS VERSION');
        cy.get('[value="Grade This Version"]').should('not.exist');

        cy.get('@rubricTitle').should('contain', studentName);
        cy.get('@rubricTitle').should('contain', submissionNumber);
    });

    it('test switching versions', () => {
        cy.get('@rubricTitle').should('contain', studentName);
        cy.get('@rubricTitle').should('contain', submissionNumber);

        cy.get('@versionSelect').find(':selected').should('contain', 'Version #2');
        cy.get('@versionSelect').find(':selected').should('contain', 'GRADE THIS VERSION');
        cy.get('[value="Grade This Version"]').should('not.exist');

        cy.get('@versionSelect').select('Version #1');
        cy.get('@rubricTitle').should('contain', studentName);
        cy.get('@rubricTitle').should('contain', submissionNumber);
        cy.get('[value="Cancel Student Submission"]').should('not.exist');
        cy.get('[value="Grade This Version"]').click();
        cy.get('@rubricTitle').should('contain', studentName);
        cy.get('@rubricTitle').should('contain', 'Submission Number: 1 / 3');
        cy.get('@versionSelect').find(':selected').should('contain', 'Version #1');
        cy.get('@versionSelect').find(':selected').should('contain', 'GRADE THIS VERSION');

        cy.get('@versionSelect').select('Version #2');
        cy.get('[value="Cancel Student Submission"]').should('not.exist');
        cy.get('[value="Grade This Version"]').click();
        cy.get('@versionSelect').find(':selected').should('contain', 'Version #2');
        cy.get('@versionSelect').find(':selected').should('contain', 'GRADE THIS VERSION');
        cy.get('[value="Grade This Version"]').should('not.exist');

        cy.get('@rubricTitle').should('contain', studentName);
        cy.get('@rubricTitle').should('contain', submissionNumber);
    });
});
