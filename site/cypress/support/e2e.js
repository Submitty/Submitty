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

Cypress.on('uncaught:exception', (err) => {
    if (err.message.includes('Cannot read properties of undefined (reading \'claim\')')) {
        // Ignore service worker-related exceptions as it's not required for end to end testing and Cypress inject's their own network interceptors
        return false;
    }

    // Ensure all other exceptions fail the test
    return true;
});
