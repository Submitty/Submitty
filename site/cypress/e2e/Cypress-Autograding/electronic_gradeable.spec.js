/**
 * switches versions if we are not on the version number we want
 * @param {number} versionNumber
 */
const switchVersion = (versionNumber) => {
    cy.get('[data-testid="submission-version-select"').find('option:selected').then((selectedOption) => {
        const currentVersion = selectedOption.val();
        if (currentVersion !== versionNumber) {
            cy.get('[data-testid="submission-version-select"]').select(String(versionNumber));
        }
    });
};

/**
 * sets up the gradeable in a state where we can submit a new submission
 * @param {string} gradeableName the gradeable we want to submit for
 */
const newSubmission = (gradeableName) => {
    cy.visit(['development', 'gradeable', gradeableName]);
    // Wait for client JS to load - reduces flakyness
    cy.get('[data-testid="gradeable-time-remaining-text"]').contains('days');
    // If the clear button exists, we should click it.
    cy.get('[data-testid="clear-all-files-button"]').then(($btn) => {
        if (!$btn.is(':disabled')) {
            cy.wrap($btn).click();
        }
    });
};

/**
 * Uploads a file and compares the results with expected scores.
 * @param {string} fileUploadName the fixture to upload
 * @param {number} bucket the bucket to submit to. Default gradeables has buckets starting from 1
 * @param {boolean} firstFile checks the bucket for no files if it is the first file being uploaded
 */
const submitFiles = (fileUploadName, bucket, firstFile = false) => {
    if (firstFile) {
        cy.get(`[data-testid="file-upload-table-${bucket}"] > tr`).should('not.exist');
    }
    cy.get(`[data-testid="upload-files-${bucket}"]`).find('[data-testid="select-files"]').attachFile(fileUploadName);
};

/**
 * Hits the gradeable submit button
 * @param {number} versionNumber the version number we are on
 */
const submitGradeable = (versionNumber) => {
    cy.get('[data-testid="submit-gradeable"').click();
    cy.get('[data-testid="submission-version-select"').find('option:selected').should('contain.text', `Version #${versionNumber}`);
    cy.get('[data-testid="popup-message"]').should('contain.text', `Successfully uploaded version ${versionNumber}`);
};

/**
 * @param {number} versionNumber version number that we should check the grades against
 * @param {number[]} expectedScores the expected score for the submission
 * @param {number[]} fullScores the max scores for the gradeables
 */
const checkNonHiddenResults = (versionNumber, expectedScores, fullScores) => {
    switchVersion(versionNumber);

    expect(expectedScores.length).to.eq(fullScores.length);
    const scoreTotal = fullScores.reduce((partial, actual) => partial + actual, 0);
    const expectedTotal = expectedScores.reduce((partial, actual) => partial + actual, 0);

    // wait for autograding results and compares expected to score pills
    cy.get('[data-testid="autograding-total-no-hidden"]', { timeout: 60000 });
    cy.get('[data-testid="autograding-total-no-hidden"]').find('[data-testid="score-pill-badge"]').contains(`${expectedTotal} / ${scoreTotal}`);
    cy.get('[data-testid="results-box"]').each(($el, index) => {
        cy.wrap($el).find('[data-testid="score-pill-badge"]').contains(`${expectedScores[index]} / ${fullScores[index]}`);
    });
};

const constructFileName = (gradeable, fileName) => {
    const baseFolder = 'copy_of_more_autograding_examples';
    return `${baseFolder}/${gradeable}/submissions/${fileName}`;
};

describe('Test the development course gradeables', () => {
    it('Should test the cpp cats gradeable with full and buggy submissions', () => {
        const fullScores = [2, 3, 4, 4, 4, 4, 4];
        cy.login('instructor');

        // use 'let' later for multiple gradeables
        const gradeable = 'cpp_cats';
        // submits the all correct files
        newSubmission(gradeable);
        submitFiles(constructFileName(gradeable, 'allCorrect.zip'), 1, true);
        submitGradeable(1);

        // submits the half incorrect files
        newSubmission(gradeable);
        submitFiles(constructFileName(gradeable, 'extraLinesAtEnd.zip'), 1, true);
        submitGradeable(2);

        // check both results
        checkNonHiddenResults(1, fullScores, fullScores);
        checkNonHiddenResults(2, [2, 3, 2, 2, 2, 2, 0], fullScores);
    });
});
