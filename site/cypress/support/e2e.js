// ***********************************************************
// This example support/index.js is processed and
// loaded automatically before your test files.
//
// This is a great place to put global configuration and
// behavior that modifies Cypress.
//
// You can change the location of this file or turn off
// automatically serving support files with the
// 'supportFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/configuration
// ***********************************************************

import './commands';

import('@cypress/skip-test/support');

beforeEach(() => {
    cy.wrap(false).as('checkLogout');
});

afterEach(() => {
    cy.get('@checkLogout').then((checkLogout) => {
        cy.logout(true, checkLogout);
    });
});

// See this issue: https://github.com/Submitty/Submitty/issues/11815
// The dependency bump from Mermaid 10.9.1 to 11+ causes a cypress test failure with the error below.
// We chose to ignore this specific error for now.
Cypress.on('uncaught:exception', (err) => {
    // console.log(err);
    // console.log(err.message);
    if (err.message.includes('Cannot read properties of undefined (reading \'claim\')')) {
        // Ignore Mermaid service worker-related errors
        // return false;
    }

    // Ensure all other exceptions fail the test
    return true;
});
