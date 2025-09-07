const future_date = '9994-12-31 23:59:59';
const past_date = '1970-10-10 23:59:59';

const notBeVisible = (button_selectors, selectors) => {
    for (const button_selector of button_selectors) {
        cy.get(button_selector).click();
        cy.get('#save_status', { timeout: 10000 }).should('have.text', 'All Changes Saved');
    }
    for (const selector of selectors) {
        cy.get(selector).should('not.be.visible');
    }
};

const beVisible = (button_selectors, selectors) => {
    for (const button_selector of button_selectors) {
        cy.get(button_selector).click();
        cy.get('#save_status', { timeout: 10000 }).should('have.text', 'All Changes Saved');
    }
    for (const selector of selectors) {
        cy.get(selector).should('be.visible');
    }
};

const logoutLogin = (user, url) => {
    cy.logout();
    cy.login(user);
    cy.visit(url);
};

const updateDates = (inputSelector, date, saveText) => {
    cy.get(inputSelector).clear();
    cy.get(inputSelector).type(date);
    // clicks out of the calendar
    cy.get('body').click(0, 0);
    cy.get('#save_status').should('have.text', saveText);
};

describe('Tests cases revolving around modifying gradeables', () => {
    beforeEach(() => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'open_peer_homework', 'update']);
    });

    after(() => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'open_peer_homework', 'update?nav_tab=5']);

        updateDates('#date_ta_view', past_date, '');
        updateDates('#date_submit', past_date, '');
        updateDates('#date_grade_due', future_date, 'All Changes Saved');
        updateDates('#date_grade', future_date, 'All Changes Saved');
        updateDates('#date_due', future_date, 'All Changes Saved');
    });

    it('Should test settings page 0-2', () => {
        notBeVisible(['#no_ta_grade'], ['#discussion_grading_enable_container', '#grade_inquiry_enable_container']);

        beVisible(['#yes_ta_grade'], ['#discussion_grading_enable_container', '#grade_inquiry_enable_container']);

        cy.get('#syllabus_bucket').should('exist');

        notBeVisible(['#no_grade_inquiry_allowed'], ['#gi_component_enable_container']);

        beVisible(['#yes_grade_inquiry_allowed'], ['#grade_inquiry_enable_container', '#gi_component_enable_container']);
        beVisible(['#yes_grade_inquiry_per_component_allowed'], ['#grade_inquiry_enable_container', '#gi_component_enable_container']);
        beVisible(['#yes_discussion'], ['.discussion_id_wrapper']);

        beVisible(['#no_grade_inquiry_per_component_allowed'], ['#gi_component_enable_container']);
        notBeVisible(['#no_discussion'], ['.discussion_id_wrapper']);

        notBeVisible(['#page_1_nav', '#no_student_view'], ['#student_download_view', '#student_submit_view']);
        beVisible(['#no_student_view_after_grades'], ['#student_download_view', '#student_submit_view']);

        beVisible(['#yes_student_download'], ['#student_download_view']);
        beVisible(['#no_student_download'], ['#student_download_view']);

        beVisible(['#yes_student_submit'], ['#student_submit_view']);
        beVisible(['#no_student_submit'], ['#student_submit_view']);
        // This can be removed once the datepicker bug gets fixed,
        // submission date cannot be changed sometimes when this is set to no.
        beVisible(['#yes_student_submit'], ['#student_submit_view']);

        notBeVisible(['#no_student_view'], ['#student_download_view', '#student_submit_view']);

        cy.get('#gradeable-lock').contains('Select prerequisite gradeable (Off)');

        // Change to button when the link changes to button
        cy.get('[data-testid="config-button"]').click();
        cy.get('[data-testid="back-to-autograding"]').should('be.visible');
        cy.get('[data-testid="back-to-autograding"]').click();
        cy.get('[data-testid="config-button"]').should('be.visible');

        cy.get('#page_2_nav').click();

        cy.get('#point_precision_id').should('be.visible');

        beVisible(['#no_custom_marks'], ['#no_custom_marks']);
        beVisible(['#yes_custom_marks'], ['#yes_custom_marks']);

        beVisible(['#yes_pdf_page'], ['#pdf_page']);

        beVisible(['#no_pdf_page_student'], ['#pdf_page']);

        beVisible(['#yes_pdf_page_student'], ['#pdf_page']);

        notBeVisible(['#no_pdf_page'], ['#pdf_page']);

        cy.get('[value="Add New Component"]').click();

        cy.get('[title="Delete this component"]').eq(-1).as('delete-me-button');
        cy.get('@delete-me-button').click();
        cy.get('@delete-me-button').then(() => {
            cy.on('window:confirm', (str) => {
                expect(str).to.equal('Are you sure you want to delete this component?');
            });
        });
    });

    it('Should test settings page 3-5', () => {
        cy.get('#page_3_nav').click();

        beVisible(['#rotating_section'], ['#rotating_data']);

        beVisible(['#all_access'], ['#doc_all_access']);

        beVisible(['#registration_section'], ['#doc_registration']);

        beVisible(['#blind_instructor_grading'], ['#blind_instructor_grading']);

        beVisible(['#unblind_instructor_grading'], ['#unblind_instructor_grading']);

        beVisible(['#unblind_limited_access_grading'], ['#unblind_limited_access_grading']);

        beVisible(['#single_blind_peer_grading'], ['#single_blind_peer_grading']);

        beVisible(['#double_blind_peer_grading'], ['#double_blind_peer_grading']);

        beVisible(['#unblind_peer_grading'], ['#unblind_peer_grading']);

        cy.get('#page_4_nav').click();
        cy.get('#peer_graders_list').should('exist');

        cy.get('#clear_peer_matrix').click();
        cy.get('#clear_peer_matrix').then(() => {
            cy.on('window:confirm', () => false);
        });

        cy.get('#download_peer_csv').click();

        cy.get('#hidden_files').should('exist');
        cy.get('#add-peer-grader').click();
        cy.get('.form-button-container > a').click({ force: true, multiple: true });

        cy.visit(['sample', 'gradeable', 'open_peer_homework', 'update?nav_tab=5']);

        notBeVisible(['#has_due_date_no'], ['#date_due']);

        beVisible(['#has_due_date_yes'], ['#date_due']);

        beVisible(['#has_release_date_yes'], ['#date_released']);

        notBeVisible(['#has_release_date_no'], ['#date_released']);

        notBeVisible(['#no_late_submission'], ['#late_days']);

        beVisible(['#yes_late_submission'], ['#late_days']);
    });

    it('Should test the change of settings in student view', () => {
        cy.get('#page_1_nav').click();
        cy.get('#no_student_view').click();
        // student should not be able to see open_peer_homework
        logoutLogin('student', ['sample']);
        cy.get('#open_peer_homework').should('not.exist');

        logoutLogin('instructor', ['sample', 'gradeable', 'open_peer_homework', 'update']);
        cy.get('#page_1_nav').click();
        cy.get('#no_student_view_after_grades').click();

        logoutLogin('student', ['sample', 'gradeable', 'open_peer_homework']);

        cy.get('#upload1').should('not.exist');
        cy.get('#submission-version-select').should('not.exist');
        cy.contains('Submissions are no longer being accepted for this assignment').should('exist');

        // Makes sure we can undo the setting change
        logoutLogin('instructor', ['sample', 'gradeable', 'open_peer_homework', 'update']);
        cy.get('#page_1_nav').click();
        cy.get('#yes_student_download').click();
        cy.get('#yes_student_submit').click();

        logoutLogin('student', ['sample', 'gradeable', 'open_peer_homework']);
        cy.get('#upload1').should('exist');
        cy.contains('Submissions are no longer being accepted for this assignment').should('not.exist');
        cy.contains('No submissions for this assignment.');
    });

    it('Should test locking the gradeable', () => {
        // testing
        cy.get('#page_1_nav').click();
        // Locking the gradeable
        cy.get('#gradeable-lock').select('Autograde and TA Homework (C System Calls) [ grades_released_homework_autota ]');
        cy.get('#gradeable-lock-points').should('be.visible');
        // unlocking the gradeable
        cy.get('#gradeable-lock').select('');
        // Relocks the gradeable
        cy.get('#gradeable-lock').select('Autograde and TA Homework (C System Calls) [ grades_released_homework_autota ]');
        cy.get('#gradeable-lock-points').type('10');
        cy.get('body').click(0, 0);

        ['instructor', 'ta', 'grader', 'student'].forEach((user) => {
            logoutLogin(user, ['sample']);
            cy.get('[title="Please complete Autograde and TA Homework (C System Calls) first with a score of 10 point(s)."]').should('have.class', 'disabled');
        });

        logoutLogin('instructor', ['sample', 'gradeable', 'open_peer_homework', 'update']);
        cy.get('#page_1_nav').click();
        cy.get('#gradeable-lock').select('');
    });

    it('Should test the dates page', () => {
        cy.get('#page_5_nav').click();

        updateDates('#date_ta_view', past_date, 'All Changes Saved');
        updateDates('#date_submit', past_date, 'All Changes Saved');
        // Should start out as viewable by student
        logoutLogin('student', ['sample']);
        cy.get('#gradeables-content').should('contain.text', 'Open Peer Homework');

        logoutLogin('instructor', ['sample', 'gradeable', 'open_peer_homework', 'update?nav_tab=5']);

        // Select yes due date, and yes release date
        cy.get('#has_due_date_yes').click();

        cy.get('#has_release_date_yes').click();

        // The gradeable should be visible to everyone
        ['student', 'grader', 'ta'].forEach((user) => {
            logoutLogin(user, ['sample']);
            cy.get('#gradeables-content').should('contain.text', 'Open Peer Homework');
        });

        logoutLogin('instructor', ['sample', 'gradeable', 'open_peer_homework', 'update?nav_tab=5']);

        // This should not be allowed, its after the submission open date
        updateDates('#date_ta_view', future_date, 'Some Changes Failed!');
        // Reset to old date
        updateDates('#date_ta_view', past_date, 'All Changes Saved');

        // Make the submit date the future date
        updateDates('#date_submit', future_date, 'All Changes Saved');

        // Gradeable should not be visible to students, but visible to TA and graders
        ['ta', 'grader'].forEach((user) => {
            logoutLogin(user, ['sample']);
            cy.get('#gradeables-content').should('contain.text', 'Open Peer Homework');
        });

        logoutLogin('student', ['sample']);
        cy.get('#gradeables-content').should('not.contain.text', 'Open Peer Homework');

        logoutLogin('instructor', ['sample', 'gradeable', 'open_peer_homework', 'update?nav_tab=5']);

        // This should not be allowed, its before the submission open date
        updateDates('#date_due', past_date, 'Some Changes Failed!');
        // Reset to old date
        updateDates('#date_due', future_date, 'All Changes Saved');

        // This should not be allowed, its before the due date
        updateDates('#date_grade', past_date, 'Some Changes Failed!');
        // Reset to valid date
        updateDates('#date_grade', future_date, 'All Changes Saved');

        // This should not be allowed, its before the due date
        updateDates('#date_grade_due', past_date, 'Some Changes Failed!');
        // Reset to valid date
        updateDates('#date_grade_due', future_date, 'All Changes Saved');

        // Should all be allowed
        updateDates('#date_ta_view', past_date, 'All Changes Saved');
        updateDates('#date_submit', past_date, 'All Changes Saved');
        updateDates('#date_due', past_date, 'All Changes Saved');
        updateDates('#date_grade', past_date, 'All Changes Saved');
        updateDates('#date_grade_due', past_date, 'All Changes Saved');
    });
});
