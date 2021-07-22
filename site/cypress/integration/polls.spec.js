/*
 * This test relies on the polls and their initital state in the sample course
 * when running vagrant up. Modifications made to those polls will result in the
 * the failure of the tests below; however, any other existing polls should not
 * interfere with the tests.
 */

describe('Test cases revolving around polls functionality', () => {
    it('Should verify all existing polls are on the instructor page', () => {
        // log in from instructor account
        cy.visit('/');
        cy.login();
        cy.visit(['sample', 'polls']);

        // toggle all the drop down
        cy.get('#old-table-dropdown').click();
        cy.wait(500);
        cy.get('#future-table-dropdown').click();
    
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
        cy.visit('/');
        cy.login('student');
        cy.visit(['sample', 'polls']);

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
        cy.visit('/');
        cy.login();
        cy.visit(['sample', 'polls']);

        // toggle all the drop down
        cy.get('#old-table-dropdown').click();
        cy.wait(500);
        cy.get('#future-table-dropdown').click();

        // go to poll 1 result page
        cy.contains('Poll 1').siblings().last().click();
        // make sure all the page elements are there
        cy.get('.content > h1').contains('Viewing poll results for Poll 1');
        cy.get('.content > h2').contains('Question:');
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
        cy.get('.content > h1').contains('Viewing poll results for Poll 2');
        cy.get('.content > h2').contains('Question:');
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
        cy.get('.content > h1').contains('Viewing poll results for Poll 3');
        cy.get('.content > h2').contains('Question:');
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
        cy.get('.content > h1').contains('Viewing poll results for Poll 4');
        cy.get('.content > h2').contains('Question:');
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
        cy.visit('/');
        cy.login();
        cy.visit(['sample', 'polls']);

        // make a new poll
        cy.contains('New Poll').click();
        cy.get('#breadcrumbs > :nth-child(7) > span').should('have.text', 'New Poll');
        cy.get('#poll-name').type('Poll 5');
        cy.get('#poll-question').type('Question goes here?');
        cy.get('#poll-type-single-response-single-correct').should('not.be.checked');
        cy.get('#poll-type-single-response-multiple-correct').should('be.checked');
        cy.get('#poll-type-single-response-survey').should('not.be.checked');
        cy.get('#poll-type-multiple-response-exact').should('not.be.checked');
        cy.get('#poll-type-multiple-response-flexible').should('not.be.checked');
        cy.get('#poll-type-multiple-response-survey').should('not.be.checked');
        cy.get('#poll-date').type('9999-12-30');
        cy.contains('+ Add Response').click();
        cy.contains('+ Add Response').click();
        cy.contains('+ Add Response').click();
        cy.get('#response_0_wrapper').children(':nth-child(3)').check();
        cy.get('#response_0_wrapper').children(':nth-child(4)').type('Answer 1');
        cy.get('#response_1_wrapper').children(':nth-child(4)').type('Answer 2');
        cy.get('#response_2_wrapper').children(':nth-child(3)').check();
        cy.get('#response_2_wrapper').children(':nth-child(4)').type('Answer 3');
        cy.get('h1').click();
        cy.get('#poll-form-submit').click();
    });
});
