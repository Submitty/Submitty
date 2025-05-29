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
