const tests = [
    {
        name: 'Normal uploading a single file',
        filePaths: ['../more_autograding_examples/file_check/submissions/a.txt'],
        dragAndDrop: false,
    },
    {
        name: 'Drag and drop uploading a single file',
        filePaths: ['../more_autograding_examples/file_check/submissions/b.txt'],
        dragAndDrop: true,
    },
    {
        name: 'Normal uploading multiple files',
        filePaths: ['../more_autograding_examples/file_check/submissions/a.txt', '../more_autograding_examples/file_check/submissions/b.txt'],
        dragAndDrop: false,
    },
    {
        name: 'Drag and drop uploading multiple files',
        filePaths: ['../more_autograding_examples/file_check/submissions/b.txt', '../more_autograding_examples/file_check/submissions/c.txt'],
        dragAndDrop: true,
    },
];

describe('Test Uploading Files', () => {
    beforeEach(() => {
        const gradeableId = 'open_homework';
        const headingText = 'New submission for: Open Homework';
        cy.login();
        cy.visit(['sample', 'gradeable', gradeableId]);
        cy.get('h1').contains(headingText).should('be.visible');
    });

    tests.forEach((test) => {
        it(`Should test ${test.name}`, () => {
            // get the starting submission count
            let submissionCount = 0;
            cy.get('#submission-version-select').then(($el) => {
                submissionCount = Cypress.$($el).length;
            });
            // clear the previous submission files (if any)
            cy.get('#startnew').then(($clearBtn) => {
                if (!($clearBtn.is(':disabled'))) {
                    $clearBtn.click();
                }
            });
            if (test.dragAndDrop) {
                cy.get('[data-testid="select-files"]').selectFile(test.filePaths, { action: 'drag-drop', force: true });
            }
            else {
                cy.get('[data-testid="select-files"]').selectFile(test.filePaths, { force: true });
            }
            // checks the alert message exist (we are not checking the exact msg) on clicking submit button, since there are two alerts on click() they can have 2 msg
            cy.on('window:confirm', (msg) => {
                expect(msg).to.satisfy((msg) => {
                    return msg.includes('Do you want to replace it?') || msg.includes('Are you sure you want to continue?');
                });
            });
            cy.get('#submit').click();

            cy.get('body').then(($element) => {
                if ($element.find('#success-0').length > 0) {
                    $element.find('#success-0').remove();
                }
            });
            // make sure the submission count has increased
            cy.get('#submission-version-select').should('have.length', submissionCount + 1);
        });
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
