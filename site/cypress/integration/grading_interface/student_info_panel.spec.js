describe('Test cases involving the files panel', () => {
    beforeEach(() => {
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);
        cy.login('instructor');
        cy.get('.btn').contains('View All').click();
        cy.get('#details-table').contains('roobc').siblings().eq(6).click();
        cy.get('#student_info_btn').click();
    });

    it('test cancelling and reinstating assignment', () => {
        cy.get('.rubric-title').should('contain', 'Cali Roob (roobc)');
        cy.get('.rubric-title').should('contain', 'Submission Number: 2 / 3');

        cy.get('#submission-version-select').children().should('have.length', 3);
        cy.get('#submission-version-select').find(':selected').should('contain', 'Version #2');
        cy.get('#submission-version-select').find(':selected').should('contain', 'GRADE THIS VERSION');
        cy.get('[value="Grade This Version"]').should('not.exist');

        cy.get('[value="Cancel Student Submission"]').click();
        cy.get('#bar_banner').should('contain', 'Cancelled Submission');
        cy.get('[value="Cancel Student Submission"]').should('not.exist');
        cy.get('[value="Grade This Version"]').should('not.exist');
        cy.get('.rubric-title').should('contain', 'Cali Roob (roobc)');
        cy.get('.rubric-title').should('contain', 'Submission Number: 0 / 3');

        cy.get('#submission-version-select').select('Version #2');
        cy.get('[value="Cancel Student Submission"]').should('not.exist');
        cy.get('[value="Grade This Version"]').click();
        cy.get('#submission-version-select').find(':selected').should('contain', 'Version #2');
        cy.get('#submission-version-select').find(':selected').should('contain', 'GRADE THIS VERSION');
        cy.get('[value="Grade This Version"]').should('not.exist');

        cy.get('.rubric-title').should('contain', 'Cali Roob (roobc)');
        cy.get('.rubric-title').should('contain', 'Submission Number: 2 / 3');
    });

    it('test switching versions', () => {
        cy.get('.rubric-title').should('contain', 'Cali Roob (roobc)');
        cy.get('.rubric-title').should('contain', 'Submission Number: 2 / 3');

        cy.get('#submission-version-select').find(':selected').should('contain', 'Version #2');
        cy.get('#submission-version-select').find(':selected').should('contain', 'GRADE THIS VERSION');
        cy.get('[value="Grade This Version"]').should('not.exist');

        cy.get('#submission-version-select').select('Version #1');
        cy.get('.rubric-title').should('contain', 'Cali Roob (roobc)');
        cy.get('.rubric-title').should('contain', 'Submission Number: 2 / 3');
        cy.get('[value="Cancel Student Submission"]').should('not.exist');
        cy.get('[value="Grade This Version"]').click();
        cy.get('.rubric-title').should('contain', 'Cali Roob (roobc)');
        cy.get('.rubric-title').should('contain', 'Submission Number: 1 / 3');
        cy.get('#submission-version-select').find(':selected').should('contain', 'Version #1');
        cy.get('#submission-version-select').find(':selected').should('contain', 'GRADE THIS VERSION');

        cy.get('#submission-version-select').select('Version #2');
        cy.get('[value="Cancel Student Submission"]').should('not.exist');
        cy.get('[value="Grade This Version"]').click();
        cy.get('#submission-version-select').find(':selected').should('contain', 'Version #2');
        cy.get('#submission-version-select').find(':selected').should('contain', 'GRADE THIS VERSION');
        cy.get('[value="Grade This Version"]').should('not.exist');

        cy.get('.rubric-title').should('contain', 'Cali Roob (roobc)');
        cy.get('.rubric-title').should('contain', 'Submission Number: 2 / 3');
    });
});
