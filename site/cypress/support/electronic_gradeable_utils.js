/**
 * Visits a gradeable and wait for JS to load so we can see if the clear all files button is enabled.
 * @param {string} gradeableName the name of the gradeable we are visiting
 */
const AUTOGRADING_RESULTS_TIMEOUT_MS = 300000;
const AUTOGRADING_RESULTS_POLL_INTERVAL_MS = 5000;

function visitGradeable(gradeableName) {
    const course = Cypress.env('course');
    cy.visit([course, 'gradeable', gradeableName]);
    // wait 10 seconds for client JS to load - reduces flakyness
    cy.get('[data-testid="gradeable-time-remaining-text"]', { timeout: 10000 }).should('contain.text', 'days');
}

/**
 * Switches to the specified version of a gradeable, or finds the current version if versionNumber is null.
 * @param {string} gradeableName the name of the gradeable we are switching to
 * @param {number|null} versionNumber the version number we are switching to, or null to find the current version
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
 * Requires newSubmission to be called first; uploads files to the specified bucket.
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
 * Requires newSubmission to be called first; clicks the gradeable submit button and checks the version
 * @param {number} versionNumber the version number we are on
 */
function submitGradeable(versionNumber) {
    cy.get('[data-testid="submit-gradeable"]').click();
    cy.get('[data-testid="submission-version-select"]').find('option:selected').should('contain.text', `Version #${versionNumber}`);
    cy.get('[data-testid="popup-message"]').should('contain.text', `Successfully uploaded version ${versionNumber}`);
};

/**
 * Waits until autograding totals are visible, periodically refreshing the page while results are still pending.
 * @param {number} deadlineMs absolute timestamp (in ms) after which we should fail
 * @returns {Cypress.Chainable}
 */
function waitForAutogradingResults(deadlineMs) {
    return cy.get('body').then(($body) => {
        const totalSelector = '[data-testid="autograding-total-no-hidden"]';
        if ($body.find(totalSelector).length > 0) {
            return;
        }

        const queueText = $body.find('.auto-results-queue-msg').text().trim();
        const incompleteText = $body.find('h4:contains("Autograding Results Incomplete")').text().trim();
        const dockerErrorText = $body.find('.error-header').text().trim();

        if (dockerErrorText.includes('Docker Image not present on machine')) {
            throw new Error(`Autograding failed due to a docker image error: ${dockerErrorText}`);
        }

        if (Date.now() >= deadlineMs) {
            const statusText = [queueText, incompleteText, dockerErrorText].filter((text) => text.length > 0).join(' | ');
            throw new Error(`Timed out waiting for autograding totals. Current status: ${statusText || 'No autograding state message found.'}`);
        }

        // eslint-disable-next-line cypress/no-unnecessary-waiting -- intentional polling delay while waiting for autograding queue to advance
        cy.wait(AUTOGRADING_RESULTS_POLL_INTERVAL_MS);
        cy.reload();
        return waitForAutogradingResults(deadlineMs);
    });
}

/**
 * Checks non hidden test results
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
    waitForAutogradingResults(Date.now() + AUTOGRADING_RESULTS_TIMEOUT_MS);

    // we should only check total if we do not have a non-deterministic score
    if (!fullScores.includes('?')) {
        const scoreTotal = fullScores.reduce((partial, actual) => partial + actual, 0);
        const expectedTotal = expectedScores.reduce((partial, actual) => partial + actual, 0);
        cy.get('[data-testid="autograding-total-no-hidden"]').find('[data-testid="score-pill-badge"]').should('contain.text', `${expectedTotal} / ${scoreTotal}`);
    }

    cy.get('[data-testid="results-box"]').each(($el, index) => {
        if (expectedScores[index] === '?' || fullScores[index] === '?') {
            cy.wrap($el).find('[data-testid="score-pill-badge"]').invoke('text').should('match', /\d+ \/ [1-9]\d*/);
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
    });
    gradeables.forEach((gradeable) => {
        checkSubmissions(gradeable.name, gradeable.submissions);
    });
}
