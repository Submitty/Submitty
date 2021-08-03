import {buildUrl} from '../support/utils.js';


describe('Test cases relating to the grading of an assignment', () => {
    beforeEach(() => {
        cy.visit(`${buildUrl(['sample', 'gradeable', 'grading_homework', 'grading', 'details'], true)}?view=all`);
        cy.login('instructor');
        cy.get('#details-table').contains('aphacker').siblings(':nth-child(8)').click();
    });

    describe('Test cases relating to the functionality of the control bar', () => {
        it('test that home button returns to the grading index page', () => {
            cy.get('#main-page').click();
            cy.url().should('include', buildUrl(['sample', 'gradeable', 'grading_homework', 'grading', 'details'], true));
        });

        describe('', () => {
            beforeEach(() => {
                // the browser's local storage should keep the student info pane selected so we only have to click it once
                cy.get('#student_info_btn').click();
            });

            it('test basic prev/next', () => {
                cy.get('#grading-setting-btn').click();
                cy.get('#general-setting-list .ta-grading-setting-option').select('Prev/Next Student');
                cy.get('#settings-popup .form-buttons .btn').click();
                cy.get('.rubric-title').should('contain', 'Alyssa P Hacker (aphacker)');
                cy.get('#next-student').click();
                cy.get('.rubric-title').should('contain', 'Felicity Hammes (hammef)');
                cy.get('#next-student').click();
                cy.get('.rubric-title').should('contain', 'Mauricio Hettinger (hettim)');
                cy.get('#next-student').click();
                cy.get('.rubric-title').should('contain', 'Ena Huel (huele)');
                cy.get('#prev-student').click();
                cy.get('.rubric-title').should('contain', 'Mauricio Hettinger (hettim)');
                cy.get('#prev-student').click();
                cy.get('.rubric-title').should('contain', 'Felicity Hammes (hammef)');
                cy.get('#prev-student').click();
                cy.get('.rubric-title').should('contain', 'Alyssa P Hacker (aphacker)');
                // check to make sure we go back to the home page if we go past the start of the list and vice-versa
                cy.get('#prev-student').click();
                cy.url().should('include', buildUrl(['sample', 'gradeable', 'grading_homework', 'grading', 'details'], true));
                cy.get('#details-table').contains('whitel').siblings(':nth-child(8)').click();
                cy.get('#next-student').click();
                cy.url().should('include', buildUrl(['sample', 'gradeable', 'grading_homework', 'grading', 'details'], true));
            });

            it('test prev/next ungraded student', () => {
                cy.get('#main-page').click();
                cy.get('#details-table').contains('chrisw').siblings(':nth-child(8)').click();

                cy.get('#grading-setting-btn').click();
                cy.get('#general-setting-list .ta-grading-setting-option').select('Prev/Next Ungraded Student');
                cy.get('#settings-popup .form-buttons .btn').click();

                cy.get('.rubric-title').should('contain', 'Willy Christiansen (chrisw)');
                cy.get('#next-student').click();
                cy.get('.rubric-title').should('contain', 'Rollin Jakubowski (jakubr)');
                cy.get('#next-student').click();
                cy.get('.rubric-title').should('contain', 'Bridget Kunde (kundeb)');
                cy.get('#next-student').click();
                cy.get('.rubric-title').should('contain', 'Madelynn Larkin (larkim)');
                cy.get('#next-student').click();
                cy.get('.rubric-title').should('contain', 'Kaley Hayes (hayesk)');
                cy.get('#prev-student').click();
                cy.get('.rubric-title').should('contain', 'Madelynn Larkin (larkim)');
                cy.get('#prev-student').click();
                cy.get('.rubric-title').should('contain', 'Bridget Kunde (kundeb)');
                cy.get('#prev-student').click();
                cy.get('.rubric-title').should('contain', 'Rollin Jakubowski (jakubr)');
                cy.get('#prev-student').click();
                cy.get('.rubric-title').should('contain', 'Willy Christiansen (chrisw)');
            });
        });
    });

    describe('Test cases relating to the "files" pane', () => {
        beforeEach(() => {
            cy.get('#submission_browser_btn').click();
        });

        // it would be ideal to click on other types of folders too but unfortunately no autograding has been done at this point
        it('try clicking on the submissions folder and see that it expands and closes', () => {
            cy.get('#submission_browser').contains('.submit.timestamp').should('not.be.visible');
            cy.get('#submissions').click();
            cy.get('#submission_browser').contains('.submit.timestamp').should('be.visible');
            // close it back when we are done
            cy.get('#submission_browser_btn').click();
            cy.get('#submission_browser').contains('.submit.timestamp').should('not.be.visible');
        });

        it('try clicking a filename to make sure a code box appears with the right contents', () => {
            cy.get('#submissions').click();
            cy.get('.openable-element-submissions').contains('.submit.timestamp').click();
            cy.get('#file_viewer_sd1f1_iframe').should('be.visible');
        });

        it('try to open a plaintext file in full panel mode', () => {
            cy.get('#submissions').click();
            cy.get('#file-view').should('not.be.visible');
            cy.get('.fa-share[title="Show file in full panel"]').first().click();
            cy.get('#file-view').should('be.visible');
            cy.get('#file-view').within(() => {
                cy.get('#file_viewer_full_panel').should('be.visible');
                cy.get('.grading_label').first().should('contain', 'Submissions and Results Browser');
                cy.get('#grading_file_name').should('contain', '.submit.timestamp');
            });
            cy.get('#file-view .fa-reply[title="Back"]').click();
            cy.get('#file-view').should('not.be.visible');
        });

        it('test open/close submissions button', () => {
            // test basic functionality
            cy.get('#div_viewer_sd1').should('not.be.visible');
            cy.get('#file_viewer_sd1f1_iframe').should('not.exist');
            cy.get('#file_viewer_sd1f2_iframe').should('not.exist');

            cy.get('#toggleSubmissionButton').click();

            cy.get('#div_viewer_sd1').should('be.visible');
            cy.get('#file_viewer_sd1f1_iframe').should('be.visible');
            cy.get('#file_viewer_sd1f2_iframe').should('be.visible');

            cy.get('#toggleSubmissionButton').click();

            cy.get('#div_viewer_sd1').should('not.be.visible');
            cy.get('#file_viewer_sd1f1_iframe').should('not.be.visible');
            cy.get('#file_viewer_sd1f2_iframe').should('not.be.visible');

            // check that it opens/closes files and doesn't just toggle
            cy.get('#toggleSubmissionButton').click();
            cy.get('#div_viewer_sd1').should('be.visible');
            cy.get('#file_viewer_sd1f1_iframe').should('be.visible');
            cy.get('#file_viewer_sd1f2_iframe').should('be.visible');

            cy.get('.openable-element-submissions').contains('.submit.timestamp').click();
            cy.get('#file_viewer_sd1f1_iframe').should('not.be.visible');

            cy.get('#toggleSubmissionButton').click();
            cy.get('#div_viewer_sd1').should('not.be.visible');
            cy.get('#file_viewer_sd1f1_iframe').should('not.be.visible');
            cy.get('#file_viewer_sd1f2_iframe').should('not.be.visible');

            cy.get('#toggleSubmissionButton').click();
            cy.get('#div_viewer_sd1').should('be.visible');
            cy.get('#file_viewer_sd1f1_iframe').should('be.visible');
            cy.get('#file_viewer_sd1f2_iframe').should('be.visible');

        });

        it('test the "auto open" checkbox', () => {
            cy.get('#submissions').click();
            cy.get('.openable-element-submissions').contains('.submit.timestamp').click();
            cy.get('#file_viewer_sd1f1_iframe').should('be.visible');
            cy.get('#file_viewer_sd1f2_iframe').should('not.exist');

            cy.get('#autoscroll_id').click();

            cy.reload();

            cy.get('#file_viewer_sd1f1_iframe').should('be.visible');
            cy.get('#file_viewer_sd1f2_iframe').should('not.exist');

            cy.get('#autoscroll_id').click();

            cy.reload();

            cy.get('#file_viewer_sd1f1_iframe').should('not.exist');
            cy.get('#file_viewer_sd1f2_iframe').should('not.exist');
        });
    });

    describe('Test cases relating to the "rubric" pane', () => {
        it('try clicking on a component and play around with the grade a bit', () => {
            cy.get('#grading_rubric_btn').click();

            cy.get('.component-container').first().within(() => {
                cy.get('.graded-by').contains('Ungraded!');
                cy.get('#grading_total').contains('− / 2');
            });

            cy.get('.component-container').first().within(() => {
                // open the component
                cy.get('#grading_total').click();

                cy.get('#grading_total').contains('− / 2');

                // wait for the component to load properly
                cy.get('.mark-selector', { timeout: 4000 }).should('be.visible');

                cy.get('.mark-selector').eq(0).click();
                cy.get('#grading_total').contains('2 / 2');
                cy.get('#grading_total').should('have.class', 'green-background');

                cy.get('.mark-selector').eq(1).click();
                cy.get('#grading_total').contains('1 / 2');
                cy.get('#grading_total').should('have.class', 'yellow-background');

                cy.get('.mark-selector').eq(1).click();
                cy.get('#grading_total').contains('− / 2');

                cy.get('.mark-selector').eq(2).click();
                cy.get('#grading_total').contains('0 / 2');
                cy.get('#grading_total').should('have.class', 'red-background');
                cy.get('.mark-selector').eq(2).click();

                // check increment/decrement
                cy.get('.mark-note-custom').clear().type('Test mark');
                cy.get('.plus-minus > .fa-caret-down').click();
                cy.get('.plus-minus > .fa-caret-down').click();
                cy.get('#grading_total').contains('1 / 2');
                cy.get('.plus-minus > .fa-caret-up').click();
                cy.get('#grading_total').contains('1.5 / 2');

                // click the cancel button
                cy.get('.save-tools-cancel').click();

                cy.get('.mark-selector', { timeout: 4000 }).should('not.be.visible');

                cy.get('.graded-by').contains('Ungraded!');
                cy.get('#grading_total').contains('− / 2');
            });
        });

        it('save a mark and then remove it', () => {
            // switch to the rubric panel
            cy.get('#grading_rubric_btn').click();

            // make a mark
            cy.get('.component-container').first().within(() => {
                // open the component
                cy.get('#grading_total').click();

                // wait for the component to load properly
                cy.get('.mark-selector', { timeout: 4000 }).should('be.visible');

                cy.get('.mark-selector').eq(1).click();
                cy.get('#grading_total').contains('1 / 2');

                // click the save button
                cy.get('.save-tools-save').click();

                // wait for the component to close
                cy.get('.mark-selector', { timeout: 4000 }).should('not.be.visible');

                cy.get('.graded-by').contains('Graded by instructor');
                cy.get('#grading_total').contains('1 / 2');
            });

            cy.reload();

            cy.get('#grading_total', { timeout: 4000 }).first().should('be.visible');

            // check that the mark is still there
            cy.get('.component-container').first().within(() => {
                // open the component
                cy.get('#grading_total').click();

                // wait for the component to load properly
                cy.get('.mark-selector', { timeout: 4000 }).should('be.visible');

                cy.get('.mark-selector').eq(1).click();
                cy.get('#grading_total').contains('− / 2');

                // click the save button
                cy.get('.save-tools-save').click({force: true});

                // wait for the component to close
                cy.get('.mark-selector', { timeout: 4000 }).should('not.be.visible');

                cy.get('.graded-by').contains('Ungraded!');
                cy.get('#grading_total').contains('− / 2');
            });
        });
    });

    describe('Test cases relating to the "student info" pane', () => {
        beforeEach(() => {
            cy.get('#student_info_btn').click();
        });

        it('check cancelling and reinstating assignment', () => {
            cy.get('.rubric-title').should('contain', 'Alyssa P Hacker (aphacker)');
            cy.get('.rubric-title').should('contain', 'Submission Number: 1 / 1');
            cy.get('.rubric-title').should('contain', 'Submitted: 12/31/1971 23:59:59 EST');

            cy.get('#submission-version-select').should('have.length', 1);
            cy.get('#submission-version-select').should('contain', 'Version #1');
            cy.get('#submission-version-select').should('contain', 'GRADE THIS VERSION');
            cy.get('[value="Grade This Version"]').should('not.exist');

            cy.get('[value="Cancel Student Submission"]').click();
            cy.get('#bar_banner').should('contain', 'Cancelled Submission');
            cy.get('[value="Cancel Student Submission"]').should('not.exist');
            cy.get('[value="Grade This Version"]').should('not.exist');
            cy.get('.rubric-title').should('contain', 'Alyssa P Hacker (aphacker)');
            cy.get('.rubric-title').should('contain', 'Submission Number: 0 / 1');

            // oh, what a hassle this is.  There don't appear to be any easy ways to format dates in Cypress...
            const date = new Date();
            cy.get('.rubric-title').should('contain', `Submitted: ${date.getMonth() + 1 > 9 ? date.getMonth() + 1 : `0${(date.getMonth() + 1).toString()}`}/${date.getDate() > 9 ? date.getDate() : `0${date.getDate().toString()}`}/${date.getFullYear()}`);

            cy.get('#submission-version-select').select('Version #1');
            cy.get('[value="Cancel Student Submission"]').should('not.exist');
            cy.get('[value="Grade This Version"]').click();
            cy.get('#submission-version-select').should('contain', 'Version #1');
            cy.get('#submission-version-select').should('contain', 'GRADE THIS VERSION');
            cy.get('[value="Grade This Version"]').should('not.exist');
        });
    });
});
