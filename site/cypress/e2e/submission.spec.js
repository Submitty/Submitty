import { getCurrentSemester } from '../support/utils.js';

function setupTestStart(
    gradeableId = 'open_homework',
    headingText = 'New submission for: Open Homework',
) {
    cy.visit('/');
    cy.login();
    cy.visit(`/courses/${getCurrentSemester()}/sample`);
    cy.get(`div#${gradeableId} div.course-button a.btn-nav-submit`).click();
    cy.get('div#gradeable-submission-cont #gradeable-info h1')
        .contains(headingText)
        .should('be.visible');
}

function changeSubmissionVersion() {
    // Find an unselected version and click
    cy.get(
        'div.content div#version-cont select option:not([selected]):not([value="0"])',
    )
        .first()
        .then(($newVersionElem) => {
            const newVersion = $newVersionElem.attr('value');
            cy.get('#submission-version-select').select(newVersion);

            // Wait until the page reloads to change the selected version, then click the "Grade This Version" button
            cy.get(
                `div.content div#version-cont select option[value='${newVersion}'][selected]`,
            )
                .should('exist')
                .then(() => {
                    cy.get(
                        'div.content div#version-cont form input[type="submit"][id="version_change"]',
                    ).click();
                });

            // Accept late day alert
            cy.on('window:confirm', (msg) => {
                expect(msg).to.satisfy((msg) => {
                    return msg.includes('Are you sure you want to continue?');
                });
            });

            // Wait until the page reloads to change the active version, completing the test
            cy.get('select#submission-version-select option')
                .contains('GRADE THIS VERSION')
                .invoke('val')
                .then((optionValue) => {
                    // Use the optionValue here or perform any desired actions
                    expect(optionValue).to.equal(newVersion);
                    cy.get('select#submission-version-select').select(
                        optionValue,
                    );
                });

            cy.get(
                `div.content div#version-cont select option[value='${newVersion}'][selected]`,
            ).contains('GRADE THIS VERSION');
        });
}

// ensure that there are multiple versions so that switching is possible
function ensureMultipleVersions() {
    const submissionSelector =
        'div.content div#version-cont select option:not([value="0"])';

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
        'div.content div#version-cont select option:not([value="0"])';
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

    // wait for the autograding results to finish. Refresh the page intermittently to (hopefully) load autograding results
    cy.wrap(false).as('autogradingDone');
    if (autograding) {
        for (let i = 0; i < 6; i++) {
            cy.wait(500);
            cy.get('main').then(($element) => {
                if ($element.find('div[id^="tc_0"]').length !== 0) {
                    cy.reload();
                }
                else {
                    cy.wrap(true).as('autogradingDone');
                }
            });
        }
        cy.get('@autogradingDone').should('be.true');
    }
}

describe('Test Normal Upload', () => {
    it('normal upload of a single file', () => {
        setupTestStart(
            'grades_released_homework_autohiddenEC',
            'New submission for: Autograder Hidden and Extra Credit (C++ Hidden Tests)',
        );

        const filePaths = createFilePaths(false, true);
        makeSubmission({ filePaths: filePaths, autograding: true });
    });

    it('drag and drop upload of a single file', () => {
        setupTestStart(
            'grades_released_homework_autohiddenEC',
            'New submission for: Autograder Hidden and Extra Credit (C++ Hidden Tests)',
        );

        const filePaths = createFilePaths(false, true);
        makeSubmission({
            filePaths: filePaths,
            dragAndDrop: true,
            autograding: true,
        });
    });

    it('normal upload of a multple file', () => {
        setupTestStart(
            'grades_released_homework_autohiddenEC',
            'New submission for: Autograder Hidden and Extra Credit (C++ Hidden Tests)',
        );

        const filePaths = createFilePaths(true, true);
        makeSubmission({ filePaths: filePaths, autograding: true });
    });

    it('drag and drop upload of a multiple file', () => {
        setupTestStart(
            'grades_released_homework_autohiddenEC',
            'New submission for: Autograder Hidden and Extra Credit (C++ Hidden Tests)',
        );

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
        changeSubmissionVersion();
    });

    it('cancelling and re-selecting the submission version', () => {
        setupTestStart();
        ensureMultipleVersions();

        // click DO NOT GRADE button and to cancel the current submission version
        cy.get('div.content div#version-cont form #do_not_grade[type=\'submit\']').click();
        cy.get('div.content div#version-cont select option[value=\'0\'][selected]');

        // change back to a valid submission version
        changeSubmissionVersion();
    });
});
