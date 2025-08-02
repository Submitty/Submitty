function visitGradeable(gradeableName) {
    const course = Cypress.env('course');
    cy.visit([course, 'gradeable', gradeableName]);
    // wait 10 seconds for client JS to load - reduces flakyness
    cy.get('[data-testid="gradeable-time-remaining-text"]', { timeout: 10000 }).should('contain.text', 'days');
}

/**
 * A number versionNumber means that we are switching to a specific version
 * A null versionNumber means that we are returning the current version
 * @param {string} gradeableName the name of the gradeable we are switching to
 * @param {number|null} versionNumber
 */
function switchOrFindVersion(gradeableName, versionNumber) {
    visitGradeable(gradeableName);
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
 * Sets up the gradeable in a state where we can submit a new submission
 * @param {string} gradeableName the name of the gradeable we are submitting to
 */
function newSubmission(gradeableName) {
    visitGradeable(gradeableName);
    // If the clear button exists, we should click it and clear all files so we can submit new ones
    cy.get('[data-testid="clear-all-files-button"]').then(($btn) => {
        if (!$btn.is(':disabled')) {
            cy.wrap($btn).click();
        }
    });
};

/**
 * Uploads a file and compares the results with expected scores.
 * Requires newSubmission to be called first
 * @param {string} fileUploadName the fixture to upload
 * @param {number} bucket the bucket to submit to. Default gradeables has buckets starting from 1
 * @param {boolean} firstFile checks the bucket for no files if it is the first file being uploaded
 */
function submitFiles(fileUploadName, bucket, firstFile = false) {
    if (firstFile) {
        cy.get(`[data-testid="file-upload-table-${bucket}"] > tr`).should('not.exist');
    }
    cy.get(`[data-testid="upload-files-${bucket}"]`).find('[data-testid="select-files"]').attachFile(fileUploadName);
};

/**
 * Hits the gradeable submit button
 * Requires newSubmission to be called first
 * @param {number} versionNumber the version number we are on
 */
function submitGradeable(versionNumber) {
    cy.get('[data-testid="submit-gradeable"]').click();
    cy.get('[data-testid="submission-version-select"]').find('option:selected').should('contain.text', `Version #${versionNumber}`);
    cy.get('[data-testid="popup-message"]').should('contain.text', `Successfully uploaded version ${versionNumber}`);
};

/**
 * @param {string} gradeableName the name of the gradeable we are checking
 * @param {number} versionNumber version number that we should check the grades against
 * @param {(number|'?')[]} expectedScores the expected score for the submission
 * @param {(number|'?')[]} fullScores the max scores for the gradeables
 */
function checkNonHiddenResults(gradeableName, versionNumber, expectedScores, fullScores) {
    switchOrFindVersion(gradeableName, versionNumber);
    expect(expectedScores.length).to.eq(fullScores.length);
    // after 20 submissions we start having penalties
    expect(versionNumber).to.be.lessThan(20);
    // wait for autograding results for two minutes
    cy.get('[data-testid="autograding-total-no-hidden"]', { timeout: 120000 });

    // we should only check total if we do not have a non-deterministic score
    if (!fullScores.includes('?')) {
        const scoreTotal = fullScores.reduce((partial, actual) => partial + actual, 0);
        const expectedTotal = expectedScores.reduce((partial, actual) => partial + actual, 0);
        cy.get('[data-testid="autograding-total-no-hidden"]').find('[data-testid="score-pill-badge"]').should('contain.text', `${expectedTotal} / ${scoreTotal}`);
    }

    cy.get('[data-testid="results-box"]').each(($el, index) => {
        if (expectedScores[index] === '?' || fullScores[index] === '?') {
            return;
        }
        cy.wrap($el).find('[data-testid="score-pill-badge"]').should('contain.text', `${expectedScores[index]} / ${fullScores[index]}`);
    });
};

/**
 * Constructs the file path for the submission files based on the course, gradeable name, and file path.
 * @param {string} gradeable the name of the gradeable
 * @param {string} filePath the path to the file within the gradeable
 * @returns {string} the full path to the file
 */
const constructFileName = (gradeable, filePath) => {
    const course = Cypress.env('course');
    if (course === 'development') {
        return `copy_of_more_autograding_examples/${gradeable}/submissions/${filePath}`;
    }
    return `copy_of_tutorial/examples/${gradeable}/submissions/${filePath}`;
};

/**
 * Submits multiple submissions for a gradeable.
 * @param {string} gradeable the name of the gradeable
 * @param {Array} submissions an array of JSON objects that contains submissionFiles and expected scores
 */
function submitSubmissions(gradeable, submissions) {
    return switchOrFindVersion(gradeable, null).then((startingVersion) => {
        cy.wrap(startingVersion).as(`${gradeable}_starting_version`);

        cy.wrap(submissions).each((submission, index) => {
            newSubmission(gradeable);

            // Loop through each bucket and submit the files
            Object.entries(submission.submissionFiles).forEach(([bucket, files]) => {
                files.forEach((file, idx) => {
                    const filePath = constructFileName(gradeable, file);
                    // first file should be true, all other files are false
                    submitFiles(filePath, Number(bucket), idx === 0);
                });
            });

            cy.get(`@${gradeable}_starting_version`).then((start) => {
                submitGradeable(start + index + 1);
            });
        });
    });
}

/**
 * Checks the submissions for a gradeable.
 * @param {string} gradeable the name of the gradeable
 * @param {Array} submissions an array of JSON objects that contains submissionFiles and expected scores
 */
function checkSubmissions(gradeable, submissions) {
    cy.get(`@${gradeable}_starting_version`).then((start) => {
        cy.wrap(submissions).each((submission, index) => {
            const version = start + index + 1;
            checkNonHiddenResults(gradeable, version, submission.expected, submission.full);
        });
    });
}

/**
 * Runs the tests for a list of gradeables.
 * @param {Array} gradeables an array of gradeable objects with name and submissions
 */
export function runTests(gradeables) {
    gradeables.forEach((gradeable, index) => {
        submitSubmissions(gradeable.name, gradeable.submissions);

        // if at end then loop of submitting submissions then through each gradeables and check the results
        if (index === gradeables.length - 1) {
            gradeables.forEach((gradeable) => {
                checkSubmissions(gradeable.name, gradeable.submissions);
            });
        }
    });
}
