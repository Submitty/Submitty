// https://on.cypress.io/writing-first-test
describe('Test cases revolving around polls functionality', () => {
    it('Should verify all existing polls are on the instructor page', () => {
        // log in from instructor account
        cy.visit('/');
        cy.login();
        cy.visit(['sample', 'polls']);

        // toggle all the drop down
        cy.get('#old-table-dropdown').click();
        cy.get('#future-table-dropdown').click();

        // verify that existing polls exist and are in the expected state
        cy.get('#today-table').contains('Poll 3');
        cy.get('#poll_3_visible').should('be.checked');
        cy.get('#poll_3_view_results').should('be.checked');
        cy.get('#poll_3_responses').invoke('text').then(parseInt).should('be.eq', 0);

        cy.get('#older-table').contains('Poll 1');
        cy.get('#poll_1_visible').should('be.checked');
        cy.get('#poll_1_view_results').should('not.be.checked');
        cy.get('#poll_1_responses').invoke('text').then(parseInt).should('be.gt', 0);

        cy.get('#older-table').contains('Poll 2');
        cy.get('#poll_2_visible').should('be.checked');
        cy.get('#poll_2_view_results').should('not.be.checked');
        cy.get('#poll_2_responses').invoke('text').then(parseInt).should('be.gt', 0);

        cy.get('#future-table').contains('Poll 4');
        cy.get('#poll_4_responses').invoke('text').then(parseInt).should('be.eq', 0);
    });

    it('Should verify all existing polls are on the student page', () => {
        // log in from instructor account
        cy.visit('/');
        cy.login('student');
        cy.visit(['sample', 'polls']);

        // verify that existing polls exist and are in the expected state
        cy.get('#today-table').contains('Poll 3');
        cy.get('#today-table > tbody > tr > :nth-child(2)').contains('No Response');
        cy.get('#today-table > tbody > tr > :nth-child(3) > a > .btn').should('have.class', 'btn-success');
        cy.get('#today-table > tbody > tr > :nth-child(3) > a > .btn').contains('Answer');


        cy.get('#older-table').contains('Poll 1');
        cy.get('#older-table > tbody > :nth-child(1) > :nth-child(3) > a > .btn').should('have.class', 'btn-primary');
        cy.get('#older-table > tbody > :nth-child(1) > :nth-child(3) > a > .btn').contains('View Poll');

        cy.get('#older-table').contains('Poll 2');
        cy.get('#older-table > tbody > :nth-child(2) > :nth-child(3) > a > .btn').should('have.class', 'btn-primary');
        cy.get('#older-table > tbody > :nth-child(2) > :nth-child(3) > a > .btn').contains('View Poll');

    });
});
