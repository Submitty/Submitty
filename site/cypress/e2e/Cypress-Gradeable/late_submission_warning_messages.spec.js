const getCurrentTime = (time_travel = '') => {
    //Return current time in a specific format (EST timezone)
    const now = new Date();
    if (time_travel === 'threeDaysAgo') {
        now.setDate(now.getDate() - 3);
    }
    else if (time_travel === 'twoDaysAgo') {
        now.setDate(now.getDate() - 2);
    }
    else if (time_travel === 'few_seconds_future') {
        //set the seconds a bit ahead in order to be able
        //to see the countdown in the submission portal and make sure
        //there is no need for reloading the page
        now.setSeconds(now.getSeconds() + 10);
    }
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
};
const getRandomGradeableName = () => {
    const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let randomString = '';
    for (let i = 0; i < 7; i++) {
        randomString += characters[Math.floor(Math.random() * characters.length)];
    }
    return randomString;
};

const gradeable = getRandomGradeableName();
const team_gradeable = `${gradeable}_team`;

const giveLateDays = (timestamp, student_id, late_days = 2) => {
    //Give a student a specific number of late days
    cy.login('instructor');
    cy.visit(['sample', 'late_days']);
    cy.get('#user_id').type(student_id);
    cy.get('#datestamp').type(timestamp, {force: true});
    cy.get('#user_id').click();
    cy.get('#late_days').clear();
    cy.get('#late_days').type(late_days);
    cy.get('input[type=submit]').click();
};

const giveExtentions = (gradeable_name) => {
    //Grant an extention to the student
    cy.login('instructor');
    cy.visit(['sample', 'extensions']);
    cy.get('#gradeable-select').select(gradeable_name);
    cy.get('#user_id').type('student');
    cy.get('#late-days').clear().type(1, {force: true});
    cy.get('#extensions-form')
        .find('a')
        .contains('Submit')
        .click();
    if (gradeable_name.includes('_team')) {
        cy.get('#more_extension_popup', {timeout:20000});
        cy.get('#apply-to-all').click();
    }
};

const SubmitAndCheckMessage = (gradeable_type, upload_file1, invalid_late_day, valid_late_day = '') => {
    //Make a submission and make sure the message that shows up matches the expected behavior

    cy.login('student');
    if ( gradeable_type === 'non_team') {
        cy.visit(['sample', 'gradeable', gradeable]);
    }
    else {
        cy.visit(['sample', 'gradeable', team_gradeable]);
    }
    if (upload_file1 === 'upload_file1') {
        cy.get('#upload1').selectFile('cypress/fixtures/file1.txt', {action: 'drag-drop'});
    }

    cy.get('#upload1').selectFile('cypress/fixtures/file2.txt', {action: 'drag-drop'});
    cy.get('#gradeable-time-remaining-text', {timeout:20000}).should('have.text', 'Gradeable Time Remaining: Past Due');
    const team_warning_messages = [
        'Your submission will be 4 day(s) late. Are you sure you want to use 3 late day(s)?',
        'There is at least 1 member on your team that does not have enough late days for this submission. This will result in them receiving a marked grade of zero. Are you sure you want to continue?'];
    let counter = 0;

    cy.waitPageChange(() => {
        cy.wait(1000); //we need to wait here because if the submission is made exactly at the moment the deadline is past due, it will be flaky
        cy.get('#submit').click();
        cy.on('window:confirm', (t) => {
            if (invalid_late_day === 'invalid_1_day_late') {
                expect(t).to.equal('Your submission will be 1 day(s) late. You are not supposed to submit unless you have an excused absence. Are you sure you want to continue?');
            }
            else if (invalid_late_day === 'invalid_4_days_late') {
                expect(t).to.equal('Your submission will be 4 day(s) late. You are not supposed to submit unless you have an excused absence. Are you sure you want to continue?');
            }
            else if (valid_late_day === '1_day_late') {
                expect(t).to.equal('Your submission will be 1 day(s) late. Are you sure you want to use 1 late day(s)?');
            }
            else if (valid_late_day === '2_days_late+extention') {
                expect(t).to.equal('Your submission will be 3 day(s) late. Are you sure you want to use 2 late day(s)?');
            }
            else if (valid_late_day === 'both_messages' && invalid_late_day === 'both_messages' ) {
                expect(t).to.equal(team_warning_messages[counter++]);
            }
        });
    });
    cy.get('#submitted-files > div').should('contain', 'file1.txt');
    cy.get('#submitted-files > div').should('contain', 'file2.txt');
    //submit one more time to make sure no messages appears, if you're still in the same time window
    cy.get('[fname="file2.txt"] .file-trash').click();
    cy.waitPageChange(() => {
        cy.get('#submit').click();
        cy.on('window:confirm', (t) => {
            expect(t).to.equal('');
        });
    });
    cy.get('[fname = "file2.txt"]').should('not.exist');
    cy.logout();
};

describe('Test warning messages for non team gradeable', () => {

    it('should create non-team gradeable for testing', () => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable']);
        // Enter gradeable info
        cy.get('#g_title').type(gradeable);
        cy.get('#g_id').type(gradeable);
        cy.get('#radio_ef_student_upload').check().click();
        // Create Gradeable
        cy.get('#create-gradeable-btn').click();

        //Date page, input 2 old dates for opening dates (Ta and students)
        cy.get('#page_5_nav').click();
        cy.get('#date_ta_view')
            .clear()
            .type('1992-06-15')
            .type('{enter}');
        cy.get('#save_status', {timeout:20000}).should('have.text', 'All Changes Saved');
        cy.get('#date_submit')
            .clear()
            .type('2004-12-18')
            .type('{enter}');
        cy.get('#save_status', {timeout:20000}).should('have.text', 'All Changes Saved');
    });

    it('should show a warning message before late submission', () => {
        //0 allowed late days and 0 remaining late days for student ==> Warning message
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', gradeable, 'update?nav_tab=5']);
        cy.get('#date_due')
            .clear()
            .type(getCurrentTime('few_seconds_future'))
            .type('{enter}');
        cy.get('#save_status', {timeout:20000}).should('have.text', 'All Changes Saved');
        cy.logout();
        SubmitAndCheckMessage('non_team', 'upload_file1', 'invalid_1_day_late');
    });

    it('should show a warning message before late submission', () => {
        /*1 allowed late day and 0 remaining late day for student ==> Warning message
        This is a basic case which is already included in part of the testing
        below with extentions (3 days in the past test)
        If testing runs for too long, you can remove this test bloc*/
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', gradeable, 'update?nav_tab=5']);
        cy.get('#late_days')
            .clear()
            .type(1, {force: true})
            .type('{enter}');
        cy.get('#save_status', {timeout:20000}).should('have.text', 'All Changes Saved');
        cy.get('#date_due')
            .clear()
            .type(getCurrentTime())
            .type('{enter}');
        cy.get('#save_status', {timeout:20000}).should('have.text', 'All Changes Saved');
        cy.logout();
        SubmitAndCheckMessage('non_team', 'upload_file2', 'invalid_1_day_late');
    });

    it('should show a confirmation message before late submission', () => {
        //1 allowed late day and 1 remaining late day for student ==> Confirmation message
        cy.login('instructor');
        giveLateDays(getCurrentTime(), 'student');
        cy.visit(['sample', 'gradeable', gradeable, 'update?nav_tab=5']);
        cy.get('#date_due')
            .clear()
            .type(getCurrentTime())
            .type('{enter}');
        cy.get('#save_status', {timeout:20000}).should('have.text', 'All Changes Saved');
        cy.logout();
        SubmitAndCheckMessage('non_team', 'upload_file2', 'valid_usage', '1_day_late');
    });

    it('should show a warning message before late submission', () => {
        //0 allowed late day and 1 remaining late day for student ==> Warning message
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', gradeable, 'update?nav_tab=5']);

        cy.get('#late_days')
            .clear()
            .type(0)
            .type('{enter}');
        cy.get('#save_status', {timeout:20000}).should('have.text', 'All Changes Saved');

        cy.get('#date_due')
            .clear()
            .type(getCurrentTime())
            .type('{enter}');
        cy.get('#save_status', {timeout:20000}).should('have.text', 'All Changes Saved');
        cy.logout();
        SubmitAndCheckMessage('non_team', 'upload_file2', 'invalid_1_day_late');
        cy.login('student');
        cy.visit(['sample', 'gradeable', gradeable]);
        cy.get('#do_not_grade').click();
    });

    it('should show a confirmation message for the first submission', () => {
        //Part 1/2 of a test case
        //The first submission will be done 2 days after the due date and use 2 valid late days
        cy.login('instructor');
        giveExtentions(gradeable);
        giveLateDays(getCurrentTime('threeDaysAgo'), 'student'); //Give valid late days (the current ones are after the original due date)
        cy.visit(['sample', 'gradeable', gradeable, 'update?nav_tab=5']);
        cy.get('#late_days')
            .clear()
            .type(3)
            .type('{enter}');
        cy.get('#save_status', {timeout:20000}).should('have.text', 'All Changes Saved');
        cy.get('#date_due')
            .clear()
            .type(getCurrentTime('twoDaysAgo'))
            .type('{enter}');
        cy.get('#save_status', {timeout:20000}).should('have.text', 'All Changes Saved');
        cy.logout();
        SubmitAndCheckMessage('non_team', 'upload_file1', 'valid_usage', '2_days_late+extention');
    });
    it('should show a warning message for the second submission ', () => {
        /*Part 2/2 of a test case
        This submission is invalid because the late days remaining are earned at the extention date,
        not the original due date.*/
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', gradeable, 'update?nav_tab=5']);
        cy.get('#date_due')
            .clear()
            .type(getCurrentTime('threeDaysAgo'))
            .type('{enter}');
        cy.get('#save_status', {timeout:20000}).should('have.text', 'All Changes Saved');
        cy.logout();
        SubmitAndCheckMessage('non_team', 'upload_file2', 'invalid_4_days_late');
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', gradeable, 'update?nav_tab=5']);
        cy.get('#no_late_submission').click(); //disable late submissions
        cy.get('#save_status', {timeout:20000}).should('have.text', 'All Changes Saved');
    });
});

describe('Test warning messages for team gradeable', () => {

    it('should create team gradeable for testing', () => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable']);
        // Enter gradeable info
        cy.get('#g_title').type(team_gradeable);
        cy.get('#g_id').type(team_gradeable);
        cy.get('#radio_ef_student_upload').check().click();
        cy.get('#team_yes_radio', {timeout:20000}).check().click();
        // Create Gradeable
        cy.get('#create-gradeable-btn').click();

        //Date page
        cy.get('#page_5_nav').click();
        cy.get('#date_ta_view')
            .clear()
            .type('1992-06-15')
            .type('{enter}');
        cy.get('#save_status', {timeout:20000}).should('have.text', 'All Changes Saved');

        cy.get('#date_submit')
            .clear()
            .type('2004-12-18')
            .type('{enter}');
        cy.get('#save_status', {timeout:20000}).should('have.text', 'All Changes Saved');
        cy.get('#late_days')
            .clear()
            .type(3)
            .type('{enter}');
        cy.get('#save_status', {timeout:20000}).should('have.text', 'All Changes Saved');
        //Create team
        cy.logout();
        cy.login('student');
        cy.visit(['sample', 'gradeable', team_gradeable, 'team']);
        cy.get('#create_new_team').click();
        cy.get('#invite_id').type('aphacker').type('{enter}');
        cy.logout();
        cy.login('aphacker');
        cy.visit(['sample', 'gradeable', team_gradeable, 'team']);
        cy.get('#accept_invitation').click();
    });

    it('should show a confirmation message for the first submission', () => {
        //Part 1/2 of a test case
        //The first submission will be done 2 days after the due date and use 2 valid late days for each team member
        cy.login('instructor');
        giveExtentions(team_gradeable);
        giveLateDays(getCurrentTime('threeDaysAgo'), 'student', 3); //this is important for part 2/2
        giveLateDays(getCurrentTime('threeDaysAgo'), 'aphacker', 2);
        cy.visit(['sample', 'gradeable', team_gradeable, 'update?nav_tab=5']);
        cy.get('#date_due')
            .clear()
            .type(getCurrentTime('twoDaysAgo'))
            .type('{enter}');
        cy.get('#save_status', {timeout:20000}).should('have.text', 'All Changes Saved');
        cy.logout();
        SubmitAndCheckMessage('team', 'upload_file1', 'valid_usage', '2_days_late+extention');
    });
    it('should show team warning message for the second submission ', () => {
        /* Second submission happens after 3 days have passed. For student, it will be a valid submission,
        so student will see a confirmation message first. However since aphacker doesn't have enough late days,
        student will see a second warning message saying that aphacker will have a bad submission.*/
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', team_gradeable, 'update?nav_tab=5']);
        cy.get('#date_due')
            .clear()
            .type(getCurrentTime('threeDaysAgo'))
            .type('{enter}');
        cy.get('#save_status', {timeout:20000}).should('have.text', 'All Changes Saved');
        cy.logout();
        SubmitAndCheckMessage('team', 'upload_file2', 'both_messages', 'both_messages');
    });

    it('should cleanup everything that was added during testing', () => {
        //Disable late submissions for team gradeable
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', team_gradeable, 'update?nav_tab=5']);
        cy.get('#no_late_submission').click();
        cy.get('#save_status', {timeout:20000}).should('have.text', 'All Changes Saved');
        //Delete all late days
        cy.visit(['sample', 'late_days']);
        const deleteLateDays = () => {
            cy.get('table').then((table) => {
                if (table.find('#Delete').length > 0) {
                    cy.get('#delete-button').click();
                    cy.get('.alert-success').invoke('text').should('contain', 'Late days entry removed');
                    cy.get('#remove_popup').click();
                    deleteLateDays();
                }
            });
        };
        deleteLateDays();
        //Delete extentions granted
        cy.visit(['sample', 'extensions']);
        cy.get('#gradeable-select').select(team_gradeable);
        cy.get('body').then((body) => {
            if (body.find('#extensions-table').length > 0) {
                cy.wrap(body).find('#Delete').first().click();
                cy.get('#more_extension_popup', {timeout:20000});
                cy.get('#apply-to-all').click();
            }
        });
        cy.get('.alert-success').invoke('text').should('contain', 'Extensions have been updated');
        cy.get('#remove_popup').click();
        cy.get('#gradeable-select').select(gradeable);
        cy.get('body').then((body) => {
            if (body.find('#extensions-table').length > 0) {
                cy.wrap(body).find('#Delete').first().click();
            }
        });
    });
    //TO DO https://github.com/Submitty/Submitty/issues/9549 , Add test case to make sure the bugfix worked
});
