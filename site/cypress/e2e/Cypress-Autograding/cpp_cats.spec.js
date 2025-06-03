const submitAndCheckResults = (fileUploadName, expectedScores, fullScores) => {
    assert(expectedScores.length === fullScores.length);
    const scoreTotal = fullScores.reduce((partial, actual) => partial + actual, 0);
    const expectedTotal = expectedScores.reduce((partial, actual) => partial + actual, 0);

    cy.get('[data-testid="file-upload-table-1"] > tr').should('not.exist');
    cy.get('[data-testid="select-files"]').attachFile(fileUploadName);
    cy.get('[data-testid="submit-gradeable-btn"').click();
    cy.get('[data-testid="popup-message"]').contains('Successfully uploaded version');

    cy.get('[data-testid="autograding-total-no-hidden"]', { timeout: 60000 });
    cy.get('[data-testid="autograding-total-no-hidden"]').find('[data-testid="score-pill-badge"]').contains(`${expectedTotal} / ${scoreTotal}`);
    cy.get('[data-testid="results-box"]').each(($el, index) => {
        cy.wrap($el).find('[data-testid="score-pill-badge"]').contains(`${expectedScores[index]} / ${fullScores[index]}`);
    });
};

describe('Test the cpp cats gradeable', () => {
    it('Should test the cpp cats gradeable with full and buggy submissions', () => {
        const fullScores = [2, 3, 4, 4, 4, 4, 4];
        cy.login('instructor');
        cy.visit(['development', 'gradeable', 'cpp_cats']);

        submitAndCheckResults('cpp_cats_submissions/allCorrect.zip', fullScores, fullScores);
        const partialScores = [2, 3, 2, 2, 2, 2, 0];
        submitAndCheckResults('cpp_cats_submissions/extraLinesAtEnd.zip', partialScores, fullScores);
    });
});
