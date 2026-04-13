const getCurrentTime = (time_travel = '') => {
    // Return current time in a specific format
    const now = new Date();
    if (time_travel === 'few_seconds_future') {
        // set the seconds a bit ahead in order to be able
        // to see the countdown in the submission portal and make sure
        // there is no need for reloading the page
        now.setSeconds(now.getSeconds() + 10);
    }
    else if (time_travel === 'few_seconds_past') {
        // Move slightly into the past so the submission page is already past due
        // when the files are uploaded.
        now.setSeconds(now.getSeconds() - 60);
    }
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: SERVER_TIMEZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    }).formatToParts(now);

    const getPart = (type) => parts.find((part) => part.type === type).value;
    const hour = getPart('hour') === '24' ? '00' : getPart('hour');
    return `${getPart('year')}-${getPart('month')}-${getPart('day')} ${hour}:${getPart('minute')}:${getPart('second')}`;
};

// The Submitty server timezone. Due dates set via the UI are interpreted in this zone.
const SERVER_TIMEZONE = 'America/New_York';

// Extract { year, month, day } components from `date` as seen in the server's timezone.
const getServerDateComponents = (date = new Date()) => {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: SERVER_TIMEZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(date);
    return {
        year: parseInt(parts.find((part) => part.type === 'year').value),
        month: parseInt(parts.find((part) => part.type === 'month').value),
        day: parseInt(parts.find((part) => part.type === 'day').value),
    };
};

// Return a due-date string for N calendar days ago (in server timezone) at midnight.
// Midnight makes late-day counts deterministic for the whole calendar day
// when checkDeadline computes 1 + floor((now - due) / 24h).
const getDueDateString = (daysAgo) => {
    const { year, month, day } = getServerDateComponents();
    // Use UTC arithmetic to subtract days without DST ambiguity
    const due = new Date(Date.UTC(year, month - 1, day - daysAgo));
    const y = due.getUTCFullYear();
    const m = String(due.getUTCMonth() + 1).padStart(2, '0');
    const d = String(due.getUTCDate()).padStart(2, '0');
    return `${y}-${m}-${d} 00:00:00`;
};

// Return a date string for N calendar days ago at 6 AM in the server's timezone.
// Used for late-day grant timestamps to ensure they fall before the noon due date.
const getMorningDateString = (daysAgo) => {
    const { year, month, day } = getServerDateComponents();
    const due = new Date(Date.UTC(year, month - 1, day - daysAgo));
    const y = due.getUTCFullYear();
    const m = String(due.getUTCMonth() + 1).padStart(2, '0');
    const d = String(due.getUTCDate()).padStart(2, '0');
    return `${y}-${m}-${d} 06:00:00`;
};
const getRandomGradeableName = () => {
    const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let randomString = '';
    for (let i = 0; i < 7; i++) {
        randomString += characters[Math.floor(Math.random() * characters.length)];
    }
    return randomString;
};
const makeNonTeamGradeable = (gradeableName) => {
    cy.login('instructor');
    cy.visit(['sample', 'gradeable']);
    // Enter gradeable info
    cy.get('[data-testid=create-gradeable-title]').type(gradeableName);
    cy.get('[data-testid=create-gradeable-id]').type(gradeableName);
    // force needed to click radio button
    cy.get('[data-testid=radio-student-upload]').check({ force: true });
    // Create Gradeable. Use waitPageChange to avoid waiting on full load event,
    // which can be flaky in CI for this form submit.
    cy.waitPageChange(() => {
        cy.get('[data-testid=create-gradeable-btn]').click();
    });
};

const gradeable = getRandomGradeableName();
const team_gradeable = `${gradeable}_team`;
const daylight_gradeable = `${gradeable}_daylight`;

const giveLateDays = (timestamp, student_id, late_days = 2) => {
    // Give a student a specific number of late days
    cy.login('instructor');
    cy.visit(['sample', 'late_days']);
    cy.get('[data-testid=user-id]').type(student_id);
    cy.get('[data-testid=datestamp]').type(timestamp, { force: true });
    cy.get('[data-testid=user-id]').click();
    cy.get('[data-testid=late-days]').clear();
    cy.get('[data-testid=late-days]').type(late_days);
    cy.get('[data-testid=submit-btn]').click();
    cy.get('.alert-success', { timeout: 10000 }).should('exist');
};

const giveExtensions = (gradeable_name) => {
    // Grant an extension to the student
    cy.visit(['sample', 'extensions']);
    cy.get('[data-testid=gradeable-select]', { timeout: 20000 }).select(gradeable_name);
    cy.get('[data-testid=extension-user-id]').type('student');
    cy.get('[data-testid=extension-late-days]').clear();
    cy.get('[data-testid=extension-late-days]').type(1, { force: true });
    cy.get('[data-testid=extensions-form]')
        .find('a')
        .contains('Submit')
        .click();
    if (gradeable_name.includes('_team')) {
        cy.get('#more_extension_popup', { timeout: 20000 }).should('be.visible');
        cy.get('[data-testid=more-extension-apply-to-all]').click();
    }
};

// daysLate: total calendar days late the server will report (daysAgo + 1)
// extensionDays: number of extension days granted (reduces late days consumed)
const SubmitAndCheckMessage = (gradeable_type, upload_file1, invalid_late_day, valid_late_day = '', daysLate = 0, extensionDays = 0) => {
    // Make a submission and make sure the message that shows up matches the expected behavior

    cy.login('student');
    if (gradeable_type === 'non_team') {
        cy.visit(['sample', 'gradeable', gradeable]);
    }
    else {
        cy.visit(['sample', 'gradeable', team_gradeable]);
    }
    if (upload_file1 === 'upload_file1') {
        cy.get('#upload1').selectFile('cypress/fixtures/file1.txt', { action: 'drag-drop' });
    }

    cy.get('#upload1').selectFile('cypress/fixtures/file2.txt', { action: 'drag-drop' });
    cy.get('#gradeable-time-remaining-text', { timeout: 20000 }).should('have.text', 'Gradeable Time Remaining: Past Due');
    cy.get('#submit').should('be.enabled');
    const team_warning_messages = [
        `Your submission will be ${daysLate} day(s) late. Are you sure you want to use ${daysLate - extensionDays} late day(s)?`,
        'There is at least 1 member on your team that does not have enough late days for this submission. This will result in them receiving a marked grade of zero. Are you sure you want to continue?'];
    let counter = 0;

    cy.waitPageChange(() => {
        // we need to wait here because if the submission is made exactly at the moment the deadline is past due, it will be flaky
        // eslint-disable-next-line cypress/no-unnecessary-waiting
        cy.wait(1000);
        cy.get('#submit').click();
        cy.on('window:confirm', (t) => {
            if (invalid_late_day === 'invalid_1_day_late') {
                expect(t).to.equal('Your submission will be 1 day(s) late. You are not supposed to submit unless you have an excused absence. Are you sure you want to continue?');
            }
            else if (invalid_late_day === 'invalid_4_days_late') {
                expect(t).to.equal(`Your submission will be ${daysLate} day(s) late. You are not supposed to submit unless you have an excused absence. Are you sure you want to continue?`);
            }
            else if (valid_late_day === '1_day_late') {
                expect(t).to.equal('Your submission will be 1 day(s) late. Are you sure you want to use 1 late day(s)?');
            }
            else if (valid_late_day === '2_days_late+extension') {
                expect(t).to.equal(`Your submission will be ${daysLate} day(s) late. Are you sure you want to use ${daysLate - extensionDays} late day(s)?`);
            }
            else if (valid_late_day === 'both_messages' && invalid_late_day === 'both_messages') {
                expect(t).to.equal(team_warning_messages[counter++]);
            }
        });
    });
    if (upload_file1 === 'upload_file1') {
        cy.get('#submitted-files > div').should('contain', 'file1.txt');
    }
    cy.get('#submitted-files > div').should('contain', 'file2.txt');
    // submit one more time to make sure no messages appears, if you're still in the same time window
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

const waitForSaveStatusSettled = () => {
    cy.get('[data-testid=save-status]', { timeout: 20000 }).should('have.text', 'All Changes Saved');
};

const checkDaylightBanner = (late_days, existence) => {
    cy.visit(['sample', 'gradeable', daylight_gradeable, 'update?nav_tab=5']);
    cy.get('[data-testid=late-days]').clear();
    cy.get('[data-testid=late-days]').type(late_days);
    cy.get('[data-testid=late-days]').type('{enter}');
    waitForSaveStatusSettled();
    cy.visit(['sample', 'gradeable', daylight_gradeable]);
    cy.get('[data-testid=daylight-savings-banner]').should(existence);
};

const calculateAndCheckDaylightBanner = (late_days) => {
    const today = new Date();
    const newDate = new Date(today);

    newDate.setDate(today.getDate() + late_days);
    checkDaylightBanner(late_days, newDate.getTimezoneOffset() !== today.getTimezoneOffset() ? 'exist' : 'not.exist');
};

const changeAllDates = (gradeable_name, date) => {
    cy.visit(['sample', 'gradeable', gradeable_name, 'update?nav_tab=5']);
    cy.get('[data-testid=ta-view-start-date]').clear();
    cy.get('[data-testid=ta-view-start-date]').type(date);
    cy.get('[data-testid=ta-view-start-date]').type('{enter}');
    waitForSaveStatusSettled();
    cy.get('[data-testid=submission-open-date]').clear();
    cy.get('[data-testid=submission-open-date]').type(date);
    cy.get('[data-testid=submission-open-date]').type('{enter}');
    waitForSaveStatusSettled();
    cy.get('[data-testid=submission-due-date]').clear();
    cy.get('[data-testid=submission-due-date]').type(date);
    cy.get('[data-testid=submission-due-date]').type('{enter}');
    waitForSaveStatusSettled();
};

describe('Checks whether daylight savings warning message should be appearing given varying amounts of late days.', () => {
    before(() => {
        makeNonTeamGradeable(daylight_gradeable);

        const date = getCurrentTime();
        // change all the due dates to today
        changeAllDates(daylight_gradeable, date);
    });

    it('test if daylight savings banner should appear for different amount of late days', () => {
        cy.login('instructor');
        checkDaylightBanner(1000, 'exist');
        checkDaylightBanner(0, 'not.exist');
        calculateAndCheckDaylightBanner(1);
        calculateAndCheckDaylightBanner(50);
        calculateAndCheckDaylightBanner(100);
    });

    it('test that daylight savings banner should not appear when we are past the due date', () => {
        cy.login('instructor');

        // first day in 2001
        const date = '2001-01-01 12:00:00';
        changeAllDates(daylight_gradeable, date);
        checkDaylightBanner(0, 'not.exist');
        checkDaylightBanner(200, 'not.exist');
        checkDaylightBanner(1000, 'not.exist');
    });
});

describe('Test warning messages for non team gradeable', () => {
    before(() => {
        makeNonTeamGradeable(gradeable);
        // Date page, input 2 old dates for opening dates (Ta and students)
        cy.get('#page_5_nav').click();
        cy.get('[data-testid=ta-view-start-date]').clear();
        cy.get('[data-testid=ta-view-start-date]').type('1992-06-15');
        cy.get('[data-testid=ta-view-start-date]').type('{enter}');
        waitForSaveStatusSettled();
        cy.get('[data-testid=submission-open-date]').clear();
        cy.get('[data-testid=submission-open-date]').type('2004-12-18');
        cy.get('[data-testid=submission-open-date]').type('{enter}');
        waitForSaveStatusSettled();
    });

    it('Warning before submission with 0 allowed and 0 remaining late days', () => {
        // 0 allowed late days and 0 remaining late days for student ==> Warning message
        giveLateDays(getCurrentTime(), 'student', 0);
        cy.visit(['sample', 'gradeable', gradeable, 'update?nav_tab=5']);
        cy.get('[data-testid=late-days]').clear();
        cy.get('[data-testid=late-days]').type(0);
        cy.get('[data-testid=late-days]').type('{enter}');
        waitForSaveStatusSettled();
        cy.get('[data-testid=submission-due-date]').clear();
        cy.get('[data-testid=submission-due-date]').type(getCurrentTime('few_seconds_past'));
        cy.get('[data-testid=submission-due-date]').type('{enter}');
        waitForSaveStatusSettled();
        cy.logout();
        SubmitAndCheckMessage('non_team', 'upload_file1', 'invalid_1_day_late');
    });

    it('Warning before submission with 1 allowed and 0 remaining late days ', () => {
        /* 1 allowed late day and 0 remaining late day for student ==> Warning message
        This is a basic case which is already included in part of the testing
        below with extensions (3 days in the past test)
        If testing runs for too long, you can remove this test bloc */
        giveLateDays(getCurrentTime(), 'student', 0);
        cy.visit(['sample', 'gradeable', gradeable, 'update?nav_tab=5']);
        cy.get('[data-testid=late-days]').clear();
        cy.get('[data-testid=late-days]').type(1, { force: true });
        cy.get('[data-testid=late-days]').type('{enter}');
        waitForSaveStatusSettled();
        cy.get('[data-testid=submission-due-date]').clear();
        cy.get('[data-testid=submission-due-date]').type(getCurrentTime('few_seconds_past'));
        cy.get('[data-testid=submission-due-date]').type('{enter}');
        waitForSaveStatusSettled();
        cy.logout();
        SubmitAndCheckMessage('non_team', 'upload_file2', 'invalid_1_day_late');
    });

    it('Confirmation before submission with 1 allowed and 1 remaining late days', () => {
        // 1 allowed late day and 1 remaining late day for student ==> Confirmation message
        giveLateDays(getCurrentTime(), 'student', 1);
        cy.visit(['sample', 'gradeable', gradeable, 'update?nav_tab=5']);
        cy.get('[data-testid=late-days]').clear();
        cy.get('[data-testid=late-days]').type(1, { force: true });
        cy.get('[data-testid=late-days]').type('{enter}');
        waitForSaveStatusSettled();
        cy.get('[data-testid=submission-due-date]').clear();
        cy.get('[data-testid=submission-due-date]').type(getCurrentTime());
        cy.get('[data-testid=submission-due-date]').type('{enter}');
        waitForSaveStatusSettled();
        cy.logout();
        SubmitAndCheckMessage('non_team', 'upload_file2', 'valid_usage', '1_day_late');
    });

    it('Warning before submission with 0 allowed and 1 remaining late day', () => {
        // 0 allowed late day and 1 remaining late day for student ==> Warning message
        giveLateDays(getCurrentTime(), 'student', 1);
        cy.visit(['sample', 'gradeable', gradeable, 'update?nav_tab=5']);

        cy.get('[data-testid=late-days]').clear();
        cy.get('[data-testid=late-days]').type(0);
        cy.get('[data-testid=late-days]').type('{enter}');
        waitForSaveStatusSettled();

        cy.get('[data-testid=submission-due-date]').clear();
        cy.get('[data-testid=submission-due-date]').type(getCurrentTime('few_seconds_past'));
        cy.get('[data-testid=submission-due-date]').type('{enter}');
        waitForSaveStatusSettled();
        cy.logout();
        SubmitAndCheckMessage('non_team', 'upload_file2', 'invalid_1_day_late');
        cy.login('student');
        cy.visit(['sample', 'gradeable', gradeable]);
        cy.get('#do_not_grade').click();
    });

    it('Confirmation for the first submission with 2 remaining late days and 1 extension', () => {
        // Part 1/2 of a test case
        // The first submission will be done 2 days after the due date and use 2 valid late days
        cy.login('instructor');
        giveExtensions(gradeable);
        // Use a grant timestamp safely before both due-date scenarios in this test.
        giveLateDays(getMorningDateString(4), 'student');
        cy.visit(['sample', 'gradeable', gradeable, 'update?nav_tab=5']);
        cy.get('[data-testid=late-days]').clear();
        cy.get('[data-testid=late-days]').type(3);
        cy.get('[data-testid=late-days]').type('{enter}');
        waitForSaveStatusSettled();
        cy.get('[data-testid=submission-due-date]').clear();
        cy.get('[data-testid=submission-due-date]').type(getDueDateString(2));
        cy.get('[data-testid=submission-due-date]').type('{enter}');
        waitForSaveStatusSettled();
        cy.logout();
        // Due 2 days ago: server reports 2+1=3 days late; 1 extension day, 2 late days consumed
        SubmitAndCheckMessage('non_team', 'upload_file1', 'valid_usage', '2_days_late+extension', 3, 1);
    });
    it('Warning message for the second submission with 0 valid remaining late day ', () => {
        /* Part 2/2 of a test case
        This submission is invalid because the late days remaining are earned at the extension date,
        not the original due date. */
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', gradeable, 'update?nav_tab=5']);
        cy.get('[data-testid=submission-due-date]').clear();
        cy.get('[data-testid=submission-due-date]').type(getDueDateString(3));
        cy.get('[data-testid=submission-due-date]').type('{enter}');
        waitForSaveStatusSettled();
        cy.logout();
        // Due 3 days ago: server reports 3+1=4 days late
        SubmitAndCheckMessage('non_team', 'upload_file2', 'invalid_4_days_late', '', 4);
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', gradeable, 'update?nav_tab=5']);
        cy.get('#no_late_submission').click(); // disable late submissions
        waitForSaveStatusSettled();
    });
});

describe('Test warning messages for team gradeable', () => {
    before(() => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable']);
        // Enter gradeable info
        cy.get('#g_title').type(team_gradeable);
        cy.get('#g_id').type(team_gradeable);
        cy.get('#radio_ef_student_upload').check();
        cy.get('#radio_ef_student_upload').click();
        cy.get('#team_yes_radio', { timeout: 20000 }).check();
        cy.get('#team_yes_radio').click();
        // Create Gradeable
        cy.get('#create-gradeable-btn').click();

        // Date page
        cy.get('#page_5_nav').click();
        cy.get('[data-testid=ta-view-start-date]').clear();
        cy.get('[data-testid=ta-view-start-date]').type('1992-06-15');
        cy.get('[data-testid=ta-view-start-date]').type('{enter}');
        waitForSaveStatusSettled();

        cy.get('[data-testid=submission-open-date]').clear();
        cy.get('[data-testid=submission-open-date]').type('2004-12-18');
        cy.get('[data-testid=submission-open-date]').type('{enter}');
        waitForSaveStatusSettled();
        cy.get('[data-testid=late-days]').clear();
        cy.get('[data-testid=late-days]').type(3);
        cy.get('[data-testid=late-days]').type('{enter}');
        waitForSaveStatusSettled();
        // Create team
        cy.logout();
        cy.login('student');
        cy.visit(['sample', 'gradeable', team_gradeable, 'team']);
        cy.get('#create_new_team').click();
        cy.get('#invite_id').type('aphacker');
        cy.get('#invite_id').type('{enter}');
        cy.logout();
        cy.login('aphacker');
        cy.visit(['sample', 'gradeable', team_gradeable, 'team']);
        cy.get('#accept_invitation').click();
        cy.logout();
    });

    it('Confirmation for the first submission with 2 remaining late days and 1 extension for teams', () => {
        // Part 1/2 of a test case
        // The first submission will be done 2 days after the due date and use 2 valid late days for each team member
        cy.login('instructor');
        giveExtensions(team_gradeable);
        // Keep grant timestamps before both due dates used in this pair of tests.
        giveLateDays(getMorningDateString(4), 'student', 3); // this is important for part 2/2
        giveLateDays(getMorningDateString(4), 'aphacker', 2);
        cy.visit(['sample', 'gradeable', team_gradeable, 'update?nav_tab=5']);
        cy.get('[data-testid=submission-due-date]').clear();
        cy.get('[data-testid=submission-due-date]').type(getDueDateString(2));
        cy.get('[data-testid=submission-due-date]').type('{enter}');
        waitForSaveStatusSettled();
        cy.logout();
        // Due 2 days ago: server reports 2+1=3 days late; 1 extension day, 2 late days consumed
        SubmitAndCheckMessage('team', 'upload_file1', 'valid_usage', '2_days_late+extension', 3, 1);
    });

    it('Warning message for the second submission with one team member having 0 remaining late days ', () => {
        /* Second submission happens after 3 days have passed. For student, it will be a valid submission,
        so student will see a confirmation message first. However since aphacker doesn't have enough late days,
        student will see a second warning message saying that aphacker will have a bad submission. */
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', team_gradeable, 'update?nav_tab=5']);
        cy.get('[data-testid=submission-due-date]').clear();
        cy.get('[data-testid=submission-due-date]').type(getDueDateString(3));
        cy.get('[data-testid=submission-due-date]').type('{enter}');
        waitForSaveStatusSettled();
        cy.logout();
        // Due 3 days ago: server reports 3+1=4 days late; 1 extension day, 3 late days consumed
        SubmitAndCheckMessage('team', 'upload_file2', 'both_messages', 'both_messages', 4, 1);
    });

    it('should cleanup everything that was added during testing', () => {
        // Disable late submissions for team gradeable
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', team_gradeable, 'update?nav_tab=5']);
        cy.get('#no_late_submission').click();
        waitForSaveStatusSettled();
        // Delete all late days
        cy.visit(['sample', 'late_days']);
        for (let i = 0; i < 3; i++) {
            cy.get('table').then((table) => {
                if (table.find('#Delete').length > 0) {
                    cy.get('#delete-button').click();
                    cy.get('.alert-success').invoke('text').should('contain', 'Late days entry removed');
                    cy.get('#remove_popup').click();
                }
            });
        }
        // Delete extensions granted
        cy.visit(['sample', 'extensions']);
        cy.get('#gradeable-select').select(team_gradeable);
        cy.get('body').then((body) => {
            if (body.find('#extensions-table').length > 0) {
                cy.wrap(body).find('#Delete').first().click();
                cy.get('#more_extension_popup', { timeout: 20000 });
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
    // TO DO https://github.com/Submitty/Submitty/issues/9549 , Add test case to make sure the bugfix worked
});
