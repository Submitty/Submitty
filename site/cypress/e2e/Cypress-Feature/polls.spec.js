/*
 * This test relies on the polls and their initial state in the sample course
 * when running vagrant up. Modifications made to those polls will result in the
 * the failure of the tests below; however, any other existing polls should not
 * interfere with the tests.
 */

import { verifyWebSocketStatus } from '../../support/utils';

const visitPoll = (title, text) => {
    cy.contains(title).siblings(':nth-child(3)').contains(text).click();
    return cy.window().then((win) => {
        if (win.histogram) {
            // WebSocket histogram object is only initialized for active polls when first loading the page
            return cy.url().should('match', /\/polls\/\d+$/).then(() => verifyWebSocketStatus());
        }
    });
};

describe('Test cases revolving around polls functionality', () => {
    it('Should verify the default settings and functionality of the dropdown bars', () => {
        // log in from instructor account
        cy.visit(['sample', 'polls']);
        cy.login();

        // verify the today's and tomorrow's sections are open by default
        cy.get('#today-table').should('be.visible');
        cy.get('#tomorrow-table').should('be.visible');
        cy.get('#today-table-dropdown').click();
        cy.get('#today-table').should('not.be.visible');
        cy.get('#tomorrow-table-dropdown').click();
        cy.get('#tomorrow-table').should('not.be.visible');

        // verify that old and future sections are not
        cy.get('#old-table-dropdown').click();
        cy.get('#older-table').should('be.visible');
        cy.get('#future-table-dropdown').click();
        cy.get('#future-table').should('be.visible');

        // status of the dropdowns should persist after page refresh
        cy.reload();
        cy.get('#today-table').should('not.be.visible');
        cy.get('#tomorrow-table').should('not.be.visible');
        cy.get('#older-table').should('be.visible');
        cy.get('#future-table').should('be.visible');

        // log in from student account
        cy.logout();
        cy.visit(['sample', 'polls']);
        cy.login('student');

        // verify that today's and old sections are open by default
        cy.get('#today-table').should('be.visible');
        cy.get('#older-table').should('be.visible');

        // log back into instructor account
        cy.logout();
        cy.visit(['sample', 'polls']);
        cy.login();
        // status of the dropdowns should remain
        cy.get('#today-table').should('not.be.visible');
        cy.get('#tomorrow-table').should('not.be.visible');
        cy.get('#older-table').should('be.visible');
        cy.get('#future-table').should('be.visible');
    });

    it('Should verify all existing polls are on the instructor page', () => {
        // log in from instructor account
        cy.logout();
        cy.visit(['sample', 'polls']);
        cy.login();

        // today's and tomorrow's polls are in display by default
        // toggle future and old polls dropdowns
        cy.get('#old-table-dropdown').click();
        cy.get('#older-table').should('be.visible');
        cy.get('#future-table-dropdown').click();
        cy.get('#future-table').should('be.visible');

        // verify that existing polls exist and are in the expected state
        cy.get('#older-table').contains('Poll 1');
        cy.get('#poll_1_visible').should('be.checked');
        cy.get('#poll_1_view_results').should('not.be.checked');
        cy.get('#poll_1_responses').invoke('text').then(parseInt).should('be.gt', 0);

        cy.get('#older-table').contains('Poll 2');
        cy.get('#poll_2_visible').should('be.checked');
        cy.get('#poll_2_view_results').should('not.be.checked');
        cy.get('#poll_2_responses').invoke('text').then(parseInt).should('be.gt', 0);

        // poll 3 release date is initially set to today but we
        // can't rely on the test being run on the same day as
        // when the vagrant environment was created
        cy.get('#poll_3_visible').should('be.checked');
        cy.get('#poll_3_view_results').should('be.checked');
        cy.get('#poll_3_responses').invoke('text').then(parseInt).should('be.eq', 0);

        cy.get('#future-table').contains('Poll 4');
        cy.get('#poll_4_responses').invoke('text').then(parseInt).should('be.eq', 0);
    });

    it('Should verify all existing polls are on the student page', () => {
        // log in from instructor account
        cy.visit(['sample', 'polls']);
        cy.login('student');

        // verify that existing polls exist and are in the expected state
        cy.get('#older-table').contains('Poll 1');
        cy.contains('Poll 1').siblings(':nth-child(3)').children().children().should('have.class', 'btn-primary');
        cy.contains('Poll 1').siblings(':nth-child(3)').contains('View Poll');

        cy.get('#older-table').contains('Poll 2');
        cy.contains('Poll 2').siblings(':nth-child(3)').children().children().should('have.class', 'btn-primary');
        cy.contains('Poll 2').siblings(':nth-child(3)').contains('View Poll');

        cy.contains('Poll 3').siblings(':nth-child(2)').contains('No Response');
    });

    it('Should verify all polls result pages', () => {
        // log in from instructor account
        cy.visit(['sample', 'polls']);
        cy.login();

        // toggle all the drop down
        cy.get('#old-table-dropdown').click();
        cy.get('#older-table').should('be.visible');
        cy.get('#future-table-dropdown').click();
        cy.get('#future-table').should('be.visible');

        // go to poll 1 result page
        cy.contains('Poll 1').siblings().last().click();
        // make sure all the page elements are there
        cy.get('.content > h1').contains('Poll 1');
        cy.get('[data-testid="timer"]').contains('Poll Ended');
        cy.get('.markdown').contains('What animals swim in the sea?');
        cy.get('#chartContainer').contains('Poll 1');
        cy.get('#chartContainer').contains('Dolphin');
        cy.get('#chartContainer').contains('Dove');
        cy.get('#chartContainer').contains('Shark');
        cy.get('#chartContainer').contains('Frog');
        cy.get('#chartContainer').contains('Snail');
        cy.go('back');

        // go to poll 2 result page
        cy.contains('Poll 2').siblings().last().click();
        // make sure all the page elements are there
        cy.get('.content > h1').contains('Poll 2');
        cy.get('[data-testid="timer"]').contains('Poll Ended');
        cy.get('.markdown').contains('What color is the sky?');
        cy.get('#chartContainer').contains('Poll 2');
        cy.get('#chartContainer').contains('Green');
        cy.get('#chartContainer').contains('Blue');
        cy.get('#chartContainer').contains('White');
        cy.get('#chartContainer').contains('Red');
        cy.go('back');

        // go to poll 3 result page
        cy.contains('Poll 3').siblings().last().click();
        // make sure all the page elements are there
        cy.get('.content > h1').contains('Poll 3');
        cy.get('[data-testid="timer"]').should('not.exist');
        cy.get('.markdown').contains('What is your favorite food?');
        cy.get('#chartContainer').contains('Poll 3');
        cy.get('#chartContainer').contains('Pizza');
        cy.get('#chartContainer').contains('Hamburger');
        cy.get('#chartContainer').contains('Ice cream');
        cy.get('#chartContainer').contains('Candy');
        cy.get('#chartContainer').contains('Other');
        cy.go('back');

        // go to poll 4 result page
        cy.contains('Poll 4').siblings().last().click();
        // make sure all the page elements are there
        cy.get('.content > h1').contains('Poll 4');
        cy.get('.markdown').contains('Which of the following statements are true? Select all that apply.');
        cy.get('#chartContainer').contains('Poll 4');
        cy.get('#chartContainer').contains('2 + 2 = 4');
        cy.get('#chartContainer').contains('3 + 2 = 7');
        cy.get('#chartContainer').contains('2 * 3 = 6');
        cy.get('#chartContainer').contains('8 / 2 = 3');
        cy.get('#chartContainer').contains('1 * 3 = 4');
    });

    it('Should verify making, editing, deleting poll works as expected', () => {
        // log in from instructor account
        cy.visit(['sample', 'polls']);
        cy.login();

        // verify the new poll page
        cy.contains('New Poll').click();
        cy.url().should('include', 'sample/polls/newPoll');
        cy.get('#breadcrumbs > :nth-child(7) > span').should('have.text', 'New Poll');
        cy.get('[data-testid="poll-name"]').type('TEST');
        cy.get('#poll-type-single-response-single-correct').should('not.be.checked');
        cy.get('#poll-type-single-response-multiple-correct').should('be.checked');
        cy.get('#poll-type-single-response-survey').should('not.be.checked');
        cy.get('#poll-type-multiple-response-exact').should('not.be.checked');
        cy.get('#poll-type-multiple-response-flexible').should('not.be.checked');
        cy.get('#poll-type-multiple-response-survey').should('not.be.checked');

        // click cancel, verify url and make sure the poll wasn't created
        cy.contains('Cancel').click();
        cy.url().should('include', 'sample/polls');
        cy.should('not.contain', 'TEST');

        // make a poll
        cy.contains('New Poll').click();
        cy.get('[data-testid="poll-name"]').type('Poll Cypress Test');
        cy.get('[data-testid="poll-question"]').type('# Question goes here...?');
        cy.get('[data-testid="poll-date"]').clear({ force: true });
        // manually setting the release date to some time in the past
        cy.get('[data-testid="poll-date"]').type('1970-01-01', { force: true });
        // Testing the poll timer
        cy.get('#timer-inputs').should('not.be.visible');
        cy.get('#enable-timer').should('not.be.checked');
        cy.get('#enable-timer').check();
        cy.get('#timer-inputs').should('be.visible');
        cy.get('#enable-timer').should('be.checked');
        // Testing 'Show Answers' disappearing after poll becomes survey
        cy.get('[data-testid="show-answer"]').should('be.visible');
        cy.get('[data-testid="single-response-survey"]').check();
        cy.get('[data-testid="show-answer"]').should('not.be.visible');
        cy.get('[data-testid="single-response-multiple-answer"]').check();
        // Add 5 seconds to timer
        cy.get('#timer-inputs').within(() => {
            cy.get('#poll-hours').clear();
            cy.get('#poll-hours').type('0');
            cy.get('#poll-minutes').clear();
            cy.get('#poll-minutes').type('0');
            cy.get('#poll-seconds').clear();
            cy.get('#poll-seconds').type('5');
        });

        cy.get('h1').click(); // get rid of the date picker
        // test default release histogram and answer settings
        cy.get('#image-file').selectFile('cypress/fixtures/sea_animals.png');
        cy.contains('Add Response').click();
        cy.contains('Add Response').click();
        cy.contains('Add Response').click();
        cy.get('[data-testid="response-0-wrapper"]').children(':nth-child(3)').check();
        cy.get('[data-testid="response-0-wrapper"]').children(':nth-child(4)').type('Answer 1');
        cy.get('[data-testid="response-1-wrapper"]').children(':nth-child(4)').type('Answer 2');
        cy.get('[data-testid="response-2-wrapper"]').children(':nth-child(3)').check();
        cy.get('[data-testid="response-2-wrapper"]').children(':nth-child(4)').type('Answer 3');
        cy.get('#new-poll-title').click();

        // submit and verify on main polls page, poll should be closed
        cy.get('[data-testid="poll-form-submit"]').click();
        cy.url().should('include', 'sample/polls');
        cy.contains('Poll Cypress Test').siblings(':nth-child(5)').should('not.be.checked');
        cy.contains('Poll Cypress Test').siblings(':nth-child(6)').should('not.be.checked');

        // log into student and assert we can't see the poll yet
        cy.logout();
        cy.login('student');
        cy.visit(['sample', 'polls']);
        cy.contains('Poll Cypress Test').siblings(':nth-child(3)').contains('Closed');
        cy.contains('Poll Cypress Test').parent().find('a').invoke('attr', 'href').then((href) => {
            cy.visit(href);
            cy.get('[data-testid="popup-message"]').should('be.visible').and('contain', 'Poll is not available');
        });

        // log into instructor and change poll to visible
        cy.logout();
        cy.login();
        cy.visit(['sample', 'polls']);
        // verify the poll is in the old polls
        cy.get('#old-table-dropdown').click();
        cy.contains('Poll Cypress Test').siblings(':nth-child(5)').children().click();

        // log in from student and verify we can now view but not answer the poll
        cy.logout();
        cy.login('student');
        cy.visit(['sample', 'polls']);
        visitPoll('Poll Cypress Test', 'View Poll');
        cy.get('h1').contains('Poll Cypress Test');
        cy.get('img').should('be.visible');
        cy.get('h2').contains('Possible responses:');
        cy.get('.markdown').should('contain', 'Question goes here...?');
        cy.get('.markdown').should('not.contain', '#');
        // go through options, verify text and status of buttons
        cy.get('.poll-content').contains('td', 'No response');
        cy.get('.poll-content > tbody > tr:nth-child(1) > td:nth-child(2)').contains('No response');
        cy.get('.poll-content > tbody > tr:nth-child(1) > td:nth-child(1) > input').should('be.disabled');
        cy.get('.poll-content > tbody > tr:nth-child(1) > td:nth-child(1) > input').should('be.checked');
        cy.get('.poll-content > tbody > tr:nth-child(2) > td:nth-child(2)').contains('Answer 1');
        cy.get('.poll-content > tbody > tr:nth-child(2) > td:nth-child(1) > input').should('be.disabled');
        cy.get('.poll-content > tbody > tr:nth-child(3) > td:nth-child(2)').contains('Answer 2');
        cy.get('.poll-content > tbody > tr:nth-child(3) > td:nth-child(1) > input').should('be.disabled');
        cy.get('.poll-content > tbody > tr:nth-child(4) > td:nth-child(2)').contains('Answer 3');
        cy.get('.poll-content > tbody > tr:nth-child(4) > td:nth-child(1) > input').should('be.disabled');
        // verify the optional display buttons and histogram don't exist for student
        cy.should('not.contain', '#toggle-histogram-button');
        cy.should('not.contain', '#toggle-info-button');
        cy.should('not.contain', '#poll-histogram');

        // log into instructor and open the poll
        cy.logout();
        cy.login();
        cy.visit(['sample', 'polls']);
        cy.contains('Poll Cypress Test').siblings(':nth-child(6)').children().click();

        // Waiting for duration to reach 0, so poll ends.
        // eslint-disable-next-line cypress/no-unnecessary-waiting
        cy.wait(5000);

        cy.reload(); // Will not need this after websockets.
        cy.contains('Poll Cypress Test').siblings(':nth-child(6)').children().should('not.be.checked');
        cy.contains('Poll Cypress Test').siblings(':nth-child(8)').click();
        cy.get('[data-testid="timer"]').contains('Poll Ended');
        cy.go('back');

        // Removing duration to continue testing
        // Editing the poll to remove timer
        cy.contains('Poll Cypress Test').siblings(':nth-child(1)').children().click();
        // Checking if user input for duration saved
        cy.get('#enable-timer').should('be.checked');
        cy.get('#timer-inputs').within(() => {
            cy.get('#poll-hours').invoke('val').should('eq', '0');
            cy.get('#poll-minutes').invoke('val').should('eq', '0');
            cy.get('#poll-seconds').invoke('val').should('eq', '5');
            cy.get('#poll-seconds').clear();
            cy.get('#poll-hours').clear();
            cy.get('#poll-hours').type('3');
        });
        cy.get('button[type=submit]').click();
        cy.contains('Poll Cypress Test').siblings(':nth-child(6)').children().should('not.be.checked');
        cy.contains('Poll Cypress Test').siblings(':nth-child(6)').children().check();

        // log into student and verify we can answer the poll
        cy.logout();
        cy.login('student');
        cy.visit(['sample', 'polls']);
        visitPoll('Poll Cypress Test', 'Answer');
        cy.get('[data-testid="timer"]').should('be.visible');
        cy.get('.poll-content > tbody > tr:nth-child(1) > td:nth-child(1) > input').should('not.be.disabled');
        cy.get('.poll-content > tbody > tr:nth-child(1) > td:nth-child(1) > input').should('be.checked');
        cy.get('.poll-content > tbody > tr:nth-child(2) > td:nth-child(2)').contains('Answer 1');
        cy.get('.poll-content > tbody > tr:nth-child(2) > td:nth-child(1) > input').should('not.be.disabled');
        cy.get('.poll-content > tbody > tr:nth-child(3) > td:nth-child(2)').contains('Answer 2');
        cy.get('.poll-content > tbody > tr:nth-child(3) > td:nth-child(1) > input').should('not.be.disabled');
        cy.get('.poll-content > tbody > tr:nth-child(4) > td:nth-child(2)').contains('Answer 3');
        cy.get('.poll-content > tbody > tr:nth-child(4) > td:nth-child(1) > input').should('not.be.disabled');
        // switch answer to Answer 2
        cy.get('.poll-content > tbody > tr:nth-child(3) > td:nth-child(1) > input').check();
        cy.get('button[type=submit]').click();
        cy.url().should('include', 'sample/polls');
        cy.contains('Poll Cypress Test').siblings(':nth-child(2)').contains('Answer 2');

        // try switching the answer and verify it got saved
        visitPoll('Poll Cypress Test', 'Answer');
        cy.get('.poll-content > tbody > tr:nth-child(3) > td:nth-child(1) > input').should('be.checked');
        cy.get('.poll-content > tbody > tr:nth-child(4) > td:nth-child(1) > input').should('not.be.checked');
        cy.get('.poll-content > tbody > tr:nth-child(4) > td:nth-child(1) > input').check(); // Answer 3
        cy.get('button[type=submit]').click();
        cy.contains('Poll Cypress Test').siblings(':nth-child(2)').contains('Answer 3');

        // log into instructor, edit the poll
        cy.logout();
        cy.login();
        cy.visit(['sample', 'polls']);
        cy.contains('Poll Cypress Test').siblings(':nth-child(1)').children().click();
        cy.url().should('include', 'sample/polls/editPoll');
        cy.get('#breadcrumbs > :nth-child(7) > span').should('have.text', 'Edit Poll');
        cy.get('[data-testid="poll-name"]').invoke('val').should('eq', 'Poll Cypress Test');
        cy.get('[data-testid="poll-question"]').should('have.value', '# Question goes here...?');
        cy.get('#poll-type-single-response-single-correct').should('not.be.checked');
        cy.get('#poll-type-single-response-multiple-correct').should('be.checked');
        cy.get('#poll-type-single-response-survey').should('not.be.checked');
        cy.get('#poll-type-multiple-response-exact').should('not.be.checked');
        cy.get('#poll-type-multiple-response-flexible').should('not.be.checked');
        cy.get('#poll-type-multiple-response-survey').should('not.be.checked');
        cy.get('#enable-timer').should('be.checked');
        cy.get('#timer-inputs').should('be.visible');
        cy.get('#poll-hours').invoke('val').should('eq', '3');
        cy.get('#poll-minutes').invoke('val').should('eq', '0');
        cy.get('#poll-seconds').invoke('val').should('eq', '0');
        cy.get('#poll-hours').clear();
        cy.get('#poll-seconds').clear();
        cy.get('#poll-seconds').type('10');
        cy.get('[data-testid="poll-date"]').invoke('val').should('eq', '1970-01-01');
        // release histogram/answer's default values should be "never"
        cy.get('#student-histogram-release-setting').invoke('val').should('eq', 'never');
        cy.get('#student-answer-release-setting').invoke('val').should('eq', 'never');
        cy.get('.poll-response').should('contain', 'Answer 1');
        cy.get('.correct-box').eq(0).should('be.checked');
        cy.get('.poll-response').should('contain', 'Answer 2');
        cy.get('.correct-box').eq(1).should('not.be.checked');
        cy.get('.poll-response').should('contain', 'Answer 3');
        cy.get('.correct-box').eq(2).should('be.checked');
        cy.get('textarea').contains('Answer 1').then(($el) => {
            cy.wrap($el).clear();
            cy.wrap($el).type('Answer 0');
        });
        cy.get('#responses').children(':nth-child(3)').children(':nth-child(5)').click();
        cy.get('#responses').children(':nth-child(2)').children(':nth-child(4)').contains('Answer 3');
        cy.get('#responses').children(':nth-child(3)').children(':nth-child(4)').contains('Answer 2');
        cy.get('button[type=submit]').click();

        // log into student and verify the edits were made
        cy.logout();
        cy.login('student');
        cy.visit(['sample', 'polls']);
        visitPoll('Poll Cypress Test', 'Answer');
        cy.get('.poll-content > tbody > tr:nth-child(1) > td:nth-child(2)').contains('No response');
        cy.get('.poll-content > tbody > tr:nth-child(1) > td:nth-child(1) > input').should('not.be.checked');
        cy.get('.poll-content > tbody > tr:nth-child(2) > td:nth-child(2)').contains('Answer 0');
        cy.get('.poll-content > tbody > tr:nth-child(2) > td:nth-child(1) > input').should('not.be.checked');
        cy.get('.poll-content > tbody > tr:nth-child(3) > td:nth-child(2)').contains('Answer 3');
        cy.get('.poll-content > tbody > tr:nth-child(3) > td:nth-child(1) > input').should('be.checked');
        cy.get('.poll-content > tbody > tr:nth-child(4) > td:nth-child(2)').contains('Answer 2');
        cy.get('.poll-content > tbody > tr:nth-child(4) > td:nth-child(1) > input').should('not.be.checked');
        // verify we can't see histogram or answer
        cy.should('not.contain', '#toggle-info-button');
        cy.should('not.contain', '#toggle-histogram-button');
        cy.should('not.contain', '#poll-histogram');
        cy.should('not.contain', '.correct-tag');

        // log into instructor, enable histogram release when poll ends
        cy.logout();
        cy.login();
        cy.visit(['sample', 'polls']);
        cy.contains('Poll Cypress Test').siblings(':nth-child(1)').children().click();
        cy.url().should('include', 'sample/polls/editPoll');
        cy.get('#breadcrumbs > :nth-child(7) > span').should('have.text', 'Edit Poll');
        cy.get('#student-histogram-release-setting').invoke('val').should('eq', 'never');
        cy.get('#student-answer-release-setting').invoke('val').should('eq', 'never');
        cy.get('#student-histogram-release-setting').select('when_ended');
        cy.get('button[type=submit]').click();

        // log into student, we still can't see histogram since poll is open
        cy.logout();
        cy.login('student');
        cy.visit(['sample', 'polls']);
        visitPoll('Poll Cypress Test', 'Edit Answer');
        cy.should('not.contain', '#toggle-info-button');
        cy.should('not.contain', '#toggle-histogram-button');
        cy.should('not.contain', '#poll-histogram');
        cy.should('not.contain', '.correct-tag');

        // log into instructor, close the poll
        cy.logout();
        cy.login();
        cy.visit(['sample', 'polls']);
        // Wait 6 seconds to wait out the time remaining for poll to close
        // eslint-disable-next-line cypress/no-unnecessary-waiting
        cy.wait(6000);
        cy.reload();
        // Validate that the poll is closed.
        cy.contains('Poll Cypress Test').siblings(':nth-child(6)').children().should('not.be.checked');
        cy.contains('Poll Cypress Test').siblings(':nth-child(8)').click();
        cy.get('[data-testid="timer"]').should('be.visible');
        cy.get('[data-testid="timer"]').contains('Poll Ended');
        cy.go('back');

        // log into student, now we can see the histogram on closed poll
        cy.logout();
        cy.login('student');
        cy.visit(['sample', 'polls']);
        visitPoll('Poll Cypress Test', 'View Poll');
        cy.get('[data-testid="timer"]').should('contain', 'Poll Ended');
        cy.get('#toggle-info-button').should('be.visible');
        cy.get('#toggle-histogram-button').should('be.visible').click();
        cy.get('#poll-histogram').should('be.visible');
        cy.should('not.contain', '.correct-tag');

        // log into instructor, enable answer release when poll ends
        cy.logout();
        cy.login();
        cy.visit(['sample', 'polls']);
        cy.contains('Poll Cypress Test').siblings(':nth-child(1)').children().click();
        cy.get('#student-histogram-release-setting').invoke('val').should('eq', 'when_ended');
        cy.get('#student-histogram-release-setting').select('always'); // test always enable histogram
        cy.get('#student-answer-release-setting').invoke('val').should('eq', 'never');
        cy.get('#student-answer-release-setting').select('when_ended');
        cy.get('#student-answer-release-setting').invoke('val').should('eq', 'when_ended');
        cy.get('button[type=submit]').click();

        // log into student and verify we can see both histogram and answer
        cy.logout();
        cy.login('student');
        cy.visit(['sample', 'polls']);
        visitPoll('Poll Cypress Test', 'View Poll');
        cy.get('#toggle-info-button').should('be.visible');
        cy.get('#toggle-histogram-button').should('be.visible').click();
        cy.get('#poll-histogram').should('be.visible');
        cy.get('.correct-tag').should('be.visible');
        cy.get('.correct-tag').should('have.length', 2);
        // checkmarks are placed next to correct answers only
        cy.get('.correct-tag').prev().should('contain', 'Answer 0');
        cy.get('.correct-tag').prev().should('contain', 'Answer 3');
        cy.get('.correct-tag').prev().should('not.contain', 'Answer 2');

        // log into student and verify we can see both histogram and answer
        cy.logout();
        cy.login('student');
        cy.visit(['sample', 'polls']);
        visitPoll('Poll Cypress Test', 'View Poll');
        cy.get('#toggle-histogram-button').should('be.visible').click();
        cy.get('#poll-histogram').should('be.visible');
        cy.get('.correct-tag').should('be.visible');
        cy.get('.correct-tag').should('have.length', 2);

        // log into instructor and delete the poll
        cy.logout();
        cy.login();
        cy.visit(['sample', 'polls']);
        cy.contains('Poll Cypress Test').siblings(':nth-child(2)').click();

        // short wait must be inserted here to support the stability of poll deletion
        // eslint-disable-next-line cypress/no-unnecessary-waiting
        cy.wait(500);

        // log into student and verify the poll is no longer there
        cy.logout();
        cy.login('student');
        cy.visit(['sample', 'polls']);
        cy.get('.content').should('not.contain', 'Poll Cypress Test');
    });

    it('Should verify today, tomorrow, and future sections contain related polls', () => {
        // log in from instructor account
        cy.logout();
        cy.visit(['sample', 'polls']);
        cy.login();

        // toggle all the drop down
        cy.get('#old-table-dropdown').click();
        cy.get('#older-table').should('be.visible');
        cy.get('#future-table-dropdown').click();
        cy.get('#future-table').should('be.visible');

        // to test today and tomorrow's polls, we have to consider timezone offset
        const tzoffset = (new Date()).getTimezoneOffset() * 60000; // offset in milliseconds
        const today = new Date(new Date() - tzoffset);
        const tomorrow = new Date(today);
        tomorrow.setDate(today.getDate() + 1);

        // make a poll, set release date to today
        cy.contains('New Poll').click();
        cy.get('[data-testid="poll-name"]').type('Poll Today');
        cy.get('[data-testid="poll-question"]').type('# Question goes here...?');
        cy.get('[data-testid="poll-date"]').clear({ force: true });
        cy.get('[data-testid="poll-date"]').type(today.toISOString().substring(0, 10), { force: true });
        cy.get('#new-poll-title').click(); // get rid of the date picker
        cy.contains('Add Response').click();
        cy.get('[data-testid="response-0-wrapper"]').children(':nth-child(3)').check();
        cy.get('[data-testid="response-0-wrapper"]').children(':nth-child(4)').type('Answer 1');
        cy.get('#new-poll-title').click();
        cy.get('[data-testid="poll-form-submit"]').click();

        // make a poll, set release date to tomorrow
        cy.contains('New Poll').click();
        cy.get('[data-testid="poll-name"]').type('Poll Tomorrow');
        cy.get('[data-testid="poll-question"]').type('What is your favorite class?');
        cy.get('[data-testid="poll-date"]').clear({ force: true });
        cy.get('[data-testid="poll-date"]').type(tomorrow.toISOString().substring(0, 10), { force: true });
        cy.get('#new-poll-title').click();
        cy.contains('Add Response').click();
        cy.get('[data-testid="response-0-wrapper"]').children(':nth-child(3)').check();
        cy.get('[data-testid="response-0-wrapper"]').children(':nth-child(4)').type('Data Structures');
        cy.get('#new-poll-title').click();
        cy.get('[data-testid="poll-form-submit"]').click();

        // make a poll, set release date to some time in the future
        cy.contains('New Poll').click();
        cy.get('[data-testid="poll-name"]').type('Poll Future');
        cy.get('[data-testid="poll-question"]').type('Why do you want to pick this date?');
        cy.get('[data-testid="poll-date"]').clear({ force: true });
        cy.get('[data-testid="poll-date"]').type('2049-06-30', { force: true });
        cy.get('#new-poll-title').click();
        cy.contains('Add Response').click();
        cy.get('[data-testid="response-0-wrapper"]').children(':nth-child(3)').check();
        cy.get('[data-testid="response-0-wrapper"]').children(':nth-child(4)').type('Answer 1');
        cy.get('#new-poll-title').click();
        cy.get('[data-testid="poll-form-submit"]').click();

        // verify on main polls page, three newly created polls should be in their own time section
        cy.url().should('include', 'sample/polls');
        cy.get('#today-table').contains('Poll Today').should('be.visible');
        cy.get('#tomorrow-table').contains('Poll Tomorrow').should('be.visible');
        cy.get('#future-table').contains('Poll Future').should('be.visible');

        // change the release date of Poll Future to tomorrow
        cy.contains('Poll Future').siblings(':nth-child(1)').children().click();
        cy.url().should('include', 'sample/polls/editPoll');
        cy.get('[data-testid="poll-date"]').clear({ force: true });
        cy.get('[data-testid="poll-date"]').type(tomorrow.toISOString().substring(0, 10), { force: true });
        cy.get('#new-poll-title').click();
        cy.get('[data-testid="poll-form-submit"]').click();

        // change the release date of Poll tomorrow to today
        cy.contains('Poll Tomorrow').siblings(':nth-child(1)').children().click();
        cy.url().should('include', 'sample/polls/editPoll');
        cy.get('[data-testid="poll-date"]').clear({ force: true });
        cy.get('[data-testid="poll-date"]').type(today.toISOString().substring(0, 10), { force: true });
        cy.get('#new-poll-title').click();
        cy.get('[data-testid="poll-form-submit"]').click();

        // change the release date of Poll today to tomorrow
        cy.contains('Poll Today').siblings(':nth-child(1)').children().click();
        cy.url().should('include', 'sample/polls/editPoll');
        cy.get('[data-testid="poll-date"]').clear({ force: true });
        cy.get('[data-testid="poll-date"]').type(tomorrow.toISOString().substring(0, 10), { force: true });
        cy.get('#new-poll-title').click();
        cy.get('[data-testid="poll-form-submit"]').click();

        // changed Poll Future => tomorrow, Poll Tomorrow => today, Poll Today => tomorrow and verify
        cy.url().should('include', 'sample/polls');
        cy.get('#tomorrow-table').contains('Poll Future').should('be.visible');
        cy.get('#tomorrow-table').contains('Poll Today').should('be.visible');
        cy.get('#today-table').contains('Poll Tomorrow').should('be.visible');

        // delete the new polls
        cy.contains('Poll Today').siblings(':nth-child(2)').click();
        cy.get('Poll Today').should('not.exist');
        cy.contains('Poll Tomorrow').siblings(':nth-child(2)').click();
        cy.get('Poll Tomorrow').should('not.exist');
        cy.contains('Poll Future').siblings(':nth-child(2)').click();
        cy.get('Poll Future').should('not.exist');
    });

    it('Should verify that polls allowing custom student options are functional', () => {
        const tzoffset = (new Date()).getTimezoneOffset() * 60000; // Offset in milliseconds
        const today = new Date(new Date() - tzoffset);
        cy.logout();
        cy.visit(['sample', 'polls']);
        cy.login();

        // Creates poll allowing custom options
        cy.contains('New Poll').click();
        cy.get('[data-testid="poll-name"]').type('Custom Poll Today');
        cy.get('[data-testid="poll-question"]').type('# Question goes here...?');
        cy.get('[data-testid="poll-date"]').clear({ force: true });
        cy.get('[data-testid="poll-date"]').type(today.toISOString().substring(0, 10), { force: true });
        cy.get('h1').click(); // get rid of the date picker
        cy.contains('Add Response').click();
        cy.get('[data-testid="response-0-wrapper"]').children(':nth-child(3)').check();
        cy.get('[data-testid="response-0-wrapper"]').children(':nth-child(4)').type('Answer 1');
        cy.get('h1').click();
        cy.get('[data-testid="poll-custom-options"]').click();
        cy.get('[data-testid="poll-form-submit"]').click();

        // Open the poll
        cy.visit(['sample', 'polls']);
        cy.contains('Custom Poll Today').siblings(':nth-child(5)').children().click();

        // Login as student to answer with custom response that can be chosen by others
        cy.logout();
        cy.login('student');
        cy.visit(['sample', 'polls']);
        visitPoll('Custom Poll Today', 'Answer');
        cy.get('[data-testid="custom-response-text"]').type('Student Custom Response');
        cy.get('[data-testid="custom-response-submit"]').should('not.be.disabled').click();

        // Ensure custom option is selected by default and students can delete them
        cy.get('[data-testid="answer-1"]').should('be.checked');
        cy.get('[data-testid="custom-response-delete"]').should('be.visible').click();
        cy.get('[data-testid="answer-1"]').should('not.exist');

        cy.visit(['sample', 'polls']);
        visitPoll('Custom Poll Today', 'Answer');

        // Create new option for other students to select
        cy.get('[data-testid="custom-response-text"]').type('Second Custom Response');
        cy.get('[data-testid="custom-response-submit"]').should('not.be.disabled').click();
        cy.logout();

        // Login as other student
        cy.login('adamsg');
        cy.visit(['sample', 'polls']);
        visitPoll('Custom Poll Today', 'Answer');

        // Ensure response is present with no delete option for other student
        cy.contains('p', 'Second Custom Response').should('be.visible');
        cy.get('[data-testid="custom-response-delete"]').should('not.exist');

        // Choose custom option created by other student
        cy.get('[data-testid="answer-1"]').check();
        cy.get('[data-testid="submit-answer"]').first().click();
        cy.logout();

        // Login as original poster, but removal of custom option is not possible as other student has chosen it
        cy.login('student');
        cy.visit(['sample', 'polls']);
        visitPoll('Custom Poll Today', 'Edit Answer');
        cy.get('[data-testid="custom-response-delete"]').should('be.visible').click();
        cy.contains('Cannot delete response option that has already been submitted as an answer by another individual').should('exist');
        cy.contains('p', 'Second Custom Response').should('be.visible');
        cy.logout();

        // Edit the poll, ensuring custom option is visible within edit poll form
        cy.login('instructor');
        cy.visit(['sample', 'polls']);
        cy.contains('Custom Poll Today').siblings(':nth-child(1)').children().click();
        cy.url().should('include', 'sample/polls/editPoll');

        // Should contain original and custom options
        cy.get('[data-testid="poll-response"]').should('contain', 'Answer 1');
        cy.get('[data-testid="poll-response"]').should('contain', 'Second Custom Response');

        // Attempt to delete custom poll option, but it should not be deleted if a given student has chosen it like as standard option
        cy.on('window:alert', (alertText) => {
            expect(alertText).to.equal('Students and/or other staff users have already submitted this response as their answer. This response cannot be deleted unless they switch their answers to the poll.');
        });
        cy.get('[data-testid="response-delete-button"]').eq(1).should('be.visible').click();
        cy.get('[data-testid="poll-response"]').should('contain', 'Second Custom Response');

        // Close custom poll, ensuring no future custom options are possible to be added or deleted
        cy.visit(['sample', 'polls']);
        cy.contains('Custom Poll Today').siblings(':nth-child(5)').children().click();
        cy.logout();

        cy.login('student');
        cy.visit(['sample', 'polls']);
        visitPoll('Custom Poll Today', 'View Poll');
        cy.get('[data-testid="custom-response-text"]').should('not.exist');
        cy.get('[data-testid="custom-response-delete"]').should('exist').click();
        cy.contains('Poll is closed').should('be.visible');
        cy.logout();

        // Remove the custom poll
        cy.login('instructor');
        cy.visit(['sample', 'polls']);
        cy.contains('Custom Poll Today').siblings(':nth-child(2)').click();
    });

    // Done.
});
