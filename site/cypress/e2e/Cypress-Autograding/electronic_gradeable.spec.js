/**
 * Uploads a file and compares the results with expected scores.
 * @param {string} fileUploadName the fixture to upload
 * @param {number[]} fullScores the max scores for the gradeables
 * @param {number[]} expectedScores the expected score for the submission
 */
const submitAndCheckResults = (fileUploadName, expectedScores, fullScores) => {
    expect(expectedScores.length).to.eq(fullScores.length);
    const scoreTotal = fullScores.reduce((partial, actual) => partial + actual, 0);
    const expectedTotal = expectedScores.reduce((partial, actual) => partial + actual, 0);

    // Wait for client JS to load - reduces flakyness
    cy.get('[data-testid="gradeable-time-remaining-text"]').contains('days');
    // If the clear button exists, we should click it.
    cy.get('[data-testid="clear-all-files-button"]').then(($btn) => {
        if (!$btn.is(':disabled')) {
            cy.wrap($btn).click();
        }
    });

    // make sure that we have no files.
    cy.get('[data-testid="file-upload-table-1"] > tr').should('not.exist');
    cy.get('[data-testid="select-files"]').attachFile(fileUploadName);
    cy.get('[data-testid="submit-gradeable"').click();
    cy.get('[data-testid="popup-message"]').contains('Successfully uploaded version');

    // wait for autograding results and compares expected to score pills
    cy.get('[data-testid="autograding-total-no-hidden"]', { timeout: 60000 });
    cy.get('[data-testid="autograding-total-no-hidden"]').find('[data-testid="score-pill-badge"]').contains(`${expectedTotal} / ${scoreTotal}`);
    cy.get('[data-testid="results-box"]').each(($el, index) => {
        cy.wrap($el).find('[data-testid="score-pill-badge"]').contains(`${expectedScores[index]} / ${fullScores[index]}`);
    });
};

describe('Test the development course gradeables', () => {
    it('Should test the cpp cats gradeable with full and buggy submissions', () => {
        const fullScores = [2, 3, 4, 4, 4, 4, 4];
        cy.login('instructor');
        cy.visit(['development', 'gradeable', 'cpp_cats']);

        submitAndCheckResults('cpp_cats_submissions/allCorrect.zip', fullScores, fullScores);
        submitAndCheckResults('cpp_cats_submissions/extraLinesAtEnd.zip', [2, 3, 2, 2, 2, 2, 0], fullScores);
    });
});
