function setupTestStart() {
    const gradeableId = 'open_homework';
    const headingText = 'New submission for: Open Homework';
    cy.login();
    cy.visit(['sample', 'gradeable', gradeableId]);
    cy.get('h1').contains(headingText).should('be.visible');
}

function removeSuccessPopup() {
    cy.get('body').then(($element) => {
        if ($element.find('#success-0').length > 0) {
            $element.find('#success-0').remove();
        }
    });
}

function makeSubmission(filePaths, dragAndDrop) {
    const submissionSelector = '#submission-version-select';
    // get the starting submission count
    let submissionCount = 0;
    cy.get(submissionSelector).then(($el) => {
        submissionCount = Cypress.$($el).length;
    });
    // clear the previous submission files (if any)
    cy.get('#startnew').then(($clearBtn) => {
        if (!($clearBtn.is(':disabled'))) {
            $clearBtn.click();
        }
    });
    if (dragAndDrop) {
        cy.get('[data-testid="select-files"]').selectFile(filePaths, { action: 'drag-drop', force: true });
    }
    else {
        cy.get('[data-testid="select-files"]').selectFile(filePaths, { force: true });
    }
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
}

describe('Test Normal Upload', () => {
    beforeEach(() => {
        setupTestStart();
    });

    it('normal upload of a single file', () => {
        const filePaths = ['../more_autograding_examples/file_check/submissions/a.txt'];
        makeSubmission(filePaths, false);
    });

    it('drag and drop upload of a single file', () => {
        const filePaths = ['../more_autograding_examples/file_check/submissions/b.txt'];
        makeSubmission(filePaths, true);
    });

    it('normal upload of multple files', () => {
        const filePaths = ['../more_autograding_examples/file_check/submissions/a.txt', '../more_autograding_examples/file_check/submissions/b.txt'];
        makeSubmission(filePaths, false);
    });

    it('drag and drop upload of multiple files', () => {
        const filePaths = ['../more_autograding_examples/file_check/submissions/b.txt', '../more_autograding_examples/file_check/submissions/c.txt'];
        makeSubmission(filePaths, true);
    });

    it('Should select the first submission, change to grade it, then select do not grade', () => {
        cy.get('#submission-version-select').select(1);
        cy.get('#version_change').click();
        cy.get('#submission-version-select').should('contain.text', 'GRADE THIS VERSION');
        cy.get('#do_not_grade').click();
        cy.get('.red-message').should('contain.text', 'NOT GRADE THIS ASSIGNMENT');

        cy.get('#submission-version-select').select(2);
    });
});
