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

require('@cypress/skip-test/support');

const LOGOUT_EXCLUDE = {
    // login.spec.js
    'Test cases revolving around the logging in functionality of the site': {
        'Test cases where the user should not be able to login': {
            'should reject bad passwords': {},
            'should reject bad usernames': {},
        },
    },
};

afterEach(() => {
    if (Cypress.currentTest.titlePath[0] in LOGOUT_EXCLUDE) {
        let currPath = LOGOUT_EXCLUDE[Cypress.currentTest.titlePath[0]];
        for (let i = 1; i < Cypress.currentTest.titlePath.length; i++) {
            if (Cypress.currentTest.titlePath[i] in currPath) {
                currPath = currPath[Cypress.currentTest.titlePath[i]];
            }
            else {
                currPath = null;
                break;
            }
        }
        if (currPath !== null) {
            return;
        }
    }
    cy.logout(true);
});
