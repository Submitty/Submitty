function setupTestStart() {
    const gradeableId = 'open_homework';
    const headingText = 'New submission for: Open Homework';
    cy.login();
    cy.visit(['sample']);
    cy.get(`.${gradeableId}_submit`).click();
    cy.get('h1').contains(headingText).should('be.visible');
}

function changeSubmissionVersion(number) {
    cy.get('#submission-version-select').select(number);
    cy.get('#version_change').click();
    // Accept late day alert
    cy.on('window:confirm', (msg) => {
        return msg.includes('Are you sure you want to continue?');
    });

    // Wait until the page reloads to change the active version, completing the test
    cy.get('#submission-version-select').should('contain.text', `Version#${number} GRADE THIS VERSION`);
}

// ensure that there are multiple versions so that switching is possible
function ensureMultipleVersions() {
    const submissionSelector =
        '#submission-version-select option:not([value="0"])';

    cy.get(submissionSelector).then(($el) => {
        const submissionCount = Cypress.$($el).length;
        if (submissionCount < 1) {
            const filePaths = createFilePaths();
            makeSubmission(filePaths);
        }
        else if (submissionCount < 2) {
            const filePaths = createFilePaths(true);
            makeSubmission(filePaths);
        }
    });
}

function createFilePaths(multiple = false, autograding = false) {
    const fileDir = 'cypress/fixtures/submissions';
    if (autograding) {
        const filePaths = [`${fileDir}/frame.cpp`];
        if (multiple) {
            return filePaths.concat([`${fileDir}/frame_buggy.cpp`]);
        }
        else {
            return filePaths;
        }
    }
    else {
        const filePaths = [`${fileDir}/infinite_loop_too_much_output.py`];
        if (multiple) {
            return filePaths.concat([`${fileDir}/part1.py`]);
        }
        else {
            return filePaths;
        }
    }
}

function inputFiles(filePaths = [], dragAndDrop = false, targetId = 'upload1') {
    if (dragAndDrop) {
        cy.get(`#${targetId}`).selectFile(filePaths, { action: 'drag-drop' });
    }
    else {
        cy.get(`#${targetId} input[type="file"]`).selectFile(filePaths, {
            force: true,
        });
    }
}

function removeSuccessPopup() {
    cy.get('body').then(($element) => {
        if ($element.find('#success-0').length > 0) {
            $element.find('#success-0').remove();
        }
    });
}

function makeSubmission({
    filePaths = [],
    dragAndDrop = false,
    targetId = 'upload1',
    autograding = false,
}) {
    const submissionSelector =
        '#submission-version-select option:not([value="0"])';
    // get the starting submission count
    cy.get(submissionSelector).then(($el) => {
        const submissionCount = Cypress.$($el).length;

        // clear the previous submission files (if any)
        cy.get('#startnew').then(($clearBtn) => {
            if ($clearBtn.is(':enabled')) {
                $clearBtn.click();
            }
        });

        // input and submit the files
        inputFiles(filePaths, dragAndDrop, targetId);

        // checks the alert message exist (we are not checking the exact msg) on clicking submit button, since there are two alerts on click() they can have 2 msg
        cy.on('window:confirm', (msg) => {
            expect(msg).to.satisfy((msg) => {
                return msg.includes('Do you want to replace it?') || msg.includes('Are you sure you want to continue?');
            });
        });
        cy.get('#submit').click();

        // Making sure that the files are submitted properly by waiting for the submission success popup (inner message)
        cy.get('#success-0');
        removeSuccessPopup();

        // make sure the submission count has increased
        cy.get(submissionSelector).should('have.length', submissionCount + 1);
    });

    // create a set fill it with submitted file names and compare them to the displayed submitted files
    const fileNamesSet = new Set();
    for (const filePath of filePaths) {
        fileNamesSet.add(filePath.substring(filePath.lastIndexOf('/') + 1));
    }

    cy.get('#submitted-files').then(($submittedFiles) => {
        const submittedFilesText = $submittedFiles.text().trim();
        const submittedFileTexts = submittedFilesText.split('\n');
        submittedFileTexts.forEach((submittedFileText) => {
            const idx = submittedFileText.lastIndexOf('(');
            const fileName = submittedFileText.substring(0, idx - 1).trim();
            fileNamesSet.delete(fileName);
        });
        cy.wrap(fileNamesSet.size).should('eq', 0);
    });

    // wait for the autograding results to finish loading autograding results
    cy.wrap(false).as('autogradingDone');
    if (autograding) {
        for (let i = 0; i < 6; i++) {
            cy.wait(500);
            cy.get('main').then(($element) => {
                if ($element.find('div[id^="tc_0"]').length === 0) {
                    cy.wrap(true).as('autogradingDone');
                }
            });
        }
        cy.get('@autogradingDone').should('be.true');
    }
}

describe('Test Normal Upload', () => {
    it('normal upload of a single file', () => {
        setupTestStart();
        const filePaths = createFilePaths(false, true);
        makeSubmission({ filePaths: filePaths, autograding: true });
    });

    it('drag and drop upload of a single file', () => {
        setupTestStart();
        const filePaths = createFilePaths(false, true);
        makeSubmission({
            filePaths: filePaths,
            dragAndDrop: true,
            autograding: true,
        });
    });

    it('normal upload of multple files', () => {
        setupTestStart();
        const filePaths = createFilePaths(true, true);
        makeSubmission({ filePaths: filePaths, autograding: true });
    });

    it('drag and drop upload of multiple files', () => {
        setupTestStart();
        const filePaths = createFilePaths(true, true);
        makeSubmission({
            filePaths: filePaths,
            dragAndDrop: true,
            autograding: true,
        });
    });

    it('changing the submission version', () => {
        setupTestStart();
        ensureMultipleVersions();
        changeSubmissionVersion(1);
    });

    it('cancelling and re-selecting the submission version', () => {
        setupTestStart();
        ensureMultipleVersions();
        // click DO NOT GRADE button and to cancel the current submission version
        cy.get('#do_not_grade').click();
        cy.get('div.content div#version-cont select option[value=\'0\'][selected]');

        // change back to a valid submission version
        changeSubmissionVersion(2);
    });
});
