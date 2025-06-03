// This should work for any electronic gradeable
// fileUploadName is where the file is relative to fixures directory
// expected scores is a list of expected scores that the user should have
// full scores is a list of scores that means 100%
const submitAndCheckResults = (fileUploadName, expectedScores, fullScores) => {
    assert(expectedScores.length === fullScores.length);
    const scoreTotal = fullScores.reduce((partial, actual) => partial + actual, 0);
    const expectedTotal = expectedScores.reduce((partial, actual) => partial + actual, 0);

    // if the clear button exists, we should clear the previous submission.
    cy.get('[data-testid="clear-all-files-button"]').then(($btn) => {
        if (!$btn.is(':disabled')) {
            cy.wrap($btn).click();
        }
    });

    // make sure that we have no files.
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

describe('Test the development course gradeables', () => {
    it('Should test the cpp cats gradeable with full and buggy submissions', () => {
        const fullScores = [2, 3, 4, 4, 4, 4, 4];
        cy.login('instructor');
        cy.visit(['development', 'gradeable', 'cpp_cats']);

        submitAndCheckResults('cpp_cats_submissions/allCorrect.zip', fullScores, fullScores);
        const partialScores = [2, 3, 2, 2, 2, 2, 0];
        submitAndCheckResults('cpp_cats_submissions/extraLinesAtEnd.zip', partialScores, fullScores);
    });

    it('Should test the python gradeable with full and buggy submissions', () => {
        const fullScores = [5];
        cy.login('instructor');
        cy.visit(['development', 'gradeable', 'python_count_ts']);
        submitAndCheckResults('python_count_ts_submissions/solution.py', [5], fullScores);
        submitAndCheckResults('python_count_ts_submissions/buggy.py', [2], fullScores);
        submitAndCheckResults('python_count_ts_submissions/syntax_error.py', [3], fullScores);
    });
});
