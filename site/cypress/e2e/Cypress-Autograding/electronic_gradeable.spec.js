/**
 * switches versions if we are not on the version number we want. Null means that we return the latest version
 * @param {number|null} versionNumber
 */
const switchOrFindVersion = (versionNumber) => {
    return cy.get('[data-testid="content-main"]').then(($content) => {
        if ($content.find('[data-testid="no-submissions-box"]').length > 0) {
            return cy.wrap(0);
        }
        return cy.wrap($content).get('[data-testid="submission-version-select"]').find('option:selected').then((selectedOption) => {
            const currentVersion = parseInt(selectedOption.val());
            // There should a submission for this version already
            if (versionNumber !== null && currentVersion !== versionNumber) {
                cy.get('[data-testid="submission-version-select"]').select(String(versionNumber));
            }
            return cy.wrap(currentVersion);
        });
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
    switchOrFindVersion(versionNumber);
    expect(expectedScores.length).to.eq(fullScores.length);
    // after 20 submissions we start having penalties
    expect(versionNumber).to.be.lessThan(20);
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
        cy.login('instructor');

        const cpp_cats = 'cpp_cats';
        const cpp_cats_full_score = [2, 3, 4, 4, 4, 4, 4];

        // Grab the current version, and then submit and check the gradeable
        cy.visit(['development', 'gradeable', cpp_cats]);
        switchOrFindVersion(null).then((startingVersion) => {
            newSubmission(cpp_cats);
            submitFiles(constructFileName(cpp_cats, 'allCorrect.zip'), 1, true);
            submitGradeable(startingVersion + 1);

            // submits the half incorrect files
            newSubmission(cpp_cats);
            submitFiles(constructFileName(cpp_cats, 'extraLinesAtEnd.zip'), 1, true);
            submitGradeable(startingVersion + 2);

            // check both results
            checkNonHiddenResults(startingVersion + 1, cpp_cats_full_score, cpp_cats_full_score);
            checkNonHiddenResults(startingVersion + 2, [2, 3, 2, 2, 2, 2, 0], cpp_cats_full_score);
        });
    });
});
