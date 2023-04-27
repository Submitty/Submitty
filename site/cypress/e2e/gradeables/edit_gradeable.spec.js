describe('Tests cases revolving around modifying gradeables', () => {
    beforeEach(() => {
        cy.visit('/');
        cy.login('instructor');
        cy.visit(['sample','gradeable','open_peer_homework','update']);
    });

    it('Should test settings page 0-2', () => {
        cy.get('#no_ta_grade').click();
        cy.get('#discussion_grading_enable_container').should('not.be.visible');
        cy.get('#yes_ta_grade').click();
        cy.get('#discussion_grading_enable_container').should('be.visible');
        cy.get('#syllabus_bucket').should('exist');

        cy.get('#page_1_nav').click();
        cy.get('#no_student_view').click();
        cy.get('#student_download_view').should('not.be.visible');
        cy.get('#student_submit_view').should('not.be.visible');
        cy.get('#no_student_view_after_grades').click();

        cy.get('#yes_student_download').click();
        cy.get('#yes_student_submit').click();

        cy.get('#gradeable-lock').contains('Select prerequisite gradeable (Off)');

        //Change to button when the link changes to button
        cy.get('.settings > a').click();
        cy.get('.content > a').click();
        cy.get('#rebuild-log-button').click();

        cy.get('#page_2_nav').click();
        cy.get('#point_precision_id');
        cy.get('#no_custom_marks').click();
        cy.get('#yes_custom_marks').click();

        cy.get('#yes_pdf_page').click();
        cy.get('#pdf_page').should('be.visible');
        cy.get('#no_pdf_page').click();
        cy.get('#pdf_page').should('not.be.visible');
        cy.get('[value="Add New Component"]').click();

        cy.get('[value="Add New Mark"]').eq(-1).click();

        cy.get('[title="Delete this component"]').eq(-1).click().then(() => {
            cy.on('window:confirm', (str) => {
                expect(str).to.equal('Are you sure you want to delete this component?');
            });
        });
    });

    it('Should test settings page 3-5', () => {
        cy.get('#page_3_nav').click();
        cy.get('#all_access').click();
        cy.get('#registration_section').click();

        cy.get('#page_4_nav').click();
        cy.get('#peer_graders_list').should('exist');

        cy.get('#clear_peer_matrix').click().then(() => {
            cy.on('window:confirm', () => false);
        });

        cy.get('#download_peer_csv').click();

        cy.get('#hidden_files').should('exist');
        cy.get('#add-peer-grader').click();
        cy.get('.form-button-container > a').click({force:true,multiple:true});

        cy.get('#page_5_nav').click();

        cy.get('#has_due_date_no').click();
        cy.get('#date_due').should('not.be.visible');

        cy.get('#has_due_date_yes').click();
        cy.get('#date_due').should('be.visible');

        cy.get('#has_release_date_yes').click();
        cy.get('#date_released').should('be.visible');

        cy.get('#has_release_date_no').click();
        cy.get('#date_released').should('not.be.visible');

        cy.get('#no_late_submission').click();
        cy.get('#late_days').should('not.be.visible');
        cy.get('#yes_late_submission').click();
        cy.get('#late_days').should('be.visible');

        //Goes back to general
        cy.get('#page_0_nav').click();
    });

    it('Should test the change of settings in student view', () => {
        cy.get('#page_1_nav').click();
        cy.get('#no_student_view').click();
        //student should not be able to see open_peer_homework
        cy.get('.fa-power-off').eq(1).click();
        cy.login('student');
        cy.visit('sample');
        cy.get('#open_peer_homework').should('not.exist');

        cy.get('.fa-power-off').eq(1).click();
        cy.login('instructor');
        cy.visit(['sample','gradeable','open_peer_homework','update']);
        cy.get('#page_1_nav').click();
        cy.get('#no_student_view_after_grades').click();

        cy.get('.fa-power-off').eq(1).click();
        cy.login('student');
        cy.visit(['sample','gradeable','open_peer_homework']);

        cy.get('#upload1').should('not.exist');
        cy.get('#submission-version-select').should('not.exist');
        cy.contains('Submissions are no longer being accepted for this assignment').should('exist');

        //Makes sure we can undo the setting change
        cy.get('.fa-power-off').eq(1).click();
        cy.login('instructor');
        cy.visit(['sample','gradeable','open_peer_homework','update']);
        cy.get('#page_1_nav').click();
        cy.get('#yes_student_download').click();
        cy.get('#yes_student_submit').click();

        cy.get('.fa-power-off').eq(1).click();
        cy.login('student');
        cy.visit(['sample','gradeable','open_peer_homework']);
        cy.get('#upload1').should('exist');
        cy.get('#submission-version-select').should('exist');
        cy.contains('Submissions are no longer being accepted for this assignment').should('not.exist');
        cy.contains('No submissions for this assignment.').should('not.exist');

    });

    it('Should test locking the gradeable',() => {
        //testing
        cy.get('#page_1_nav').click();
        //Locking the gradeable
        cy.get('#gradeable-lock').select('Autograde and TA Homework (C System Calls) [ grades_released_homework_autota ]');
        cy.get('#gradeable-lock-points').should('be.visible');
        //unlocking the gradeable
        cy.get('#gradeable-lock').select('');
        //Relocks the gradeable
        cy.get('#gradeable-lock').select('Autograde and TA Homework (C System Calls) [ grades_released_homework_autota ]');
        cy.get('#gradeable-lock-points').type('10');

        ['instructor','student','grader','ta'].forEach((user) => {
            cy.get('.fa-power-off').eq(1).click();
            cy.login(user);
            cy.visit(['sample']);
            cy.get('[title="Please complete Autograde and TA Homework (C System Calls) first"]').click();
            if (user === 'ta' || user === 'instructor') {
                cy.on('window:confirm',() => true);
                cy.get('#upload1').should('exist');
            }
            else {
                cy.get('#upload1').should('not.exist');
            }
        });

        cy.get('.fa-power-off').eq(1).click();
        cy.login('instructor');
        cy.visit(['sample','gradeable','open_peer_homework','update']);
        cy.get('#page_1_nav').click();
        cy.get('#gradeable-lock').select('');

    });

    it('Should test the dates page',() => {
        const future_date = '9994-12-31 23:59:59';
        const past_date = '1970-10-10 23:59:59';
        cy.get('#page_5_nav').click();
        cy.get('#has_release_date_yes').click();

        cy.get('#date_ta_view').clear();
        cy.get('#date_ta_view').type(past_date);
        ['student','grader','ta'].forEach((user) => {
            cy.get('.fa-power-off').eq(1).click();
            cy.login(user);
        });

        cy.get('.fa-power-off').eq(1).click();
        cy.login('instructor');
        cy.visit(['sample','gradeable','open_peer_homework','update?nav_tab=5']);

        cy.get('#date_ta_view').clear();
        cy.get('#date_ta_view').type(future_date);
        //clicks out of the calendar
        cy.get('body').click(0,0);

        cy.get('#date_submit').clear();
        cy.get('#date_submit').type(past_date);
        ['student','grader','ta'].forEach((user) => {
            cy.get('.fa-power-off').eq(1).click();
            cy.login(user);
        });

        cy.get('.fa-power-off').eq(1).click();
        cy.login('instructor');
        cy.visit(['sample','gradeable','open_peer_homework','update?nav_tab=5']);

        cy.get('#date_submit').clear();
        cy.get('#date_submit').type(future_date);
        cy.get('body').click(0,0);

        cy.get('#date_due').type(past_date);
        ['student','grader','ta'].forEach((user) => {
            cy.get('.fa-power-off').eq(1).click();
            cy.login(user);
        });

        cy.get('.fa-power-off').eq(1).click();
        cy.login('instructor');
        cy.visit(['sample','gradeable','open_peer_homework','update?nav_tab=5']);

        cy.get('#date_due').clear();
        cy.get('#date_due').type(future_date);
        cy.get('body').click(0,0);

        cy.get('#date_grade').clear();
        cy.get('#date_grade').type(past_date);
        ['student','grader','ta'].forEach((user) => {
            cy.get('.fa-power-off').eq(1).click();
            cy.login(user);
        });

        cy.get('.fa-power-off').eq(1).click();
        cy.login('instructor');
        cy.visit(['sample','gradeable','open_peer_homework','update?nav_tab=5']);

        cy.get('#date_grade').clear();
        cy.get('#date_grade').type(future_date);
        cy.get('body').click(0,0);

        cy.get('#date_grade_due').type(past_date);
        ['student','grader','ta'].forEach((user) => {
            cy.get('.fa-power-off').eq(1).click();
            cy.login(user);
        });

        cy.get('.fa-power-off').eq(1).click();
        cy.login('instructor');
        cy.visit(['sample','gradeable','open_peer_homework','update?nav_tab=5']);

        cy.get('#date_grade_due').clear();
        cy.get('#date_grade_due').type(future_date);
        ['student','grader','ta'].forEach((user) => {
            cy.get('.fa-power-off').eq(1).click();
            cy.login(user);
        });

        cy.get('.fa-power-off').eq(1).click();
        cy.login('instructor');
        cy.visit(['sample','gradeable','open_peer_homework','update?nav_tab=5']);

        cy.get('#date_grade_due').type(past_date);

    });
});
