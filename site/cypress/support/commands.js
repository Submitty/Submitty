// ***********************************************
// commands.js creates various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
//
//
// -- This is a parent command --
// Cypress.Commands.add("login", (email, password) => { ... })
//
//
// -- This is a child command --
// Cypress.Commands.add("drag", { prevSubject: 'element'}, (subject, options) => { ... })
//
//
// -- This is a dual command --
// Cypress.Commands.add("dismiss", { prevSubject: 'optional'}, (subject, options) => { ... })
//
//
// -- This will overwrite an existing command --
// Cypress.Commands.overwrite("visit", (originalFn, url, options) => { ... })

import 'cypress-file-upload';
import { buildUrl } from './utils.js';
// These functions can be called like "cy.login(...)" and will yield a result

/**
* Log into Submitty using API, if a user is already logged in, you are redirected to
* the My Courses page (/home)
*
* @param {String} [username=instructor] - username & password of who to log in as
*/
Cypress.Commands.add('login', (username = 'instructor', password = username) => {
    cy.url({ decode: true }).then(($url) => {
        cy.request({
            method: 'POST',
            url: '/authentication/check_login'.concat('?', $url.split('?')[1]),
            form: true,
            followRedirect: false,
            body: {
                user_id: username,
                password: password,
                __csrf: username,
            },
        }).then((response) => {
            cy.visit(response.redirectedToUrl);
        });
    });
});

/**
* Log out of Submitty, assumes a user is already logged in
*/
Cypress.Commands.add('logout', () => {
    cy.request({
        method: 'POST',
        url: '/authentication/logout',
    });
    cy.visit('/');
});

/**
 * Waits for the current page to be changed (does not wait for the `load` event to run).
 * Will continue execution as soon as the current page is changed.
 * Provided by https://github.com/cypress-io/cypress/issues/1805#issuecomment-525482440
 *
 * @param {function} fn - the code to run that should navigate to a new page.
 */
Cypress.Commands.add('waitPageChange', (fn) => {
    cy.window().then((win) => {
        win._cypress_beforeReload = true;
    });
    cy.window().should('have.prop', '_cypress_beforeReload', true);
    fn();
    cy.window().should('not.have.prop', '_cypress_beforeReload');
});

/**
* Visit a url either by an array of parts or a completed url E.g:
* cy.vist(['sample', 'gradeables']) -> visit 'courses/s21/sample/gradeables'
* cy.vist('authentication/login') visit 'authentication/login'
*
* base url of localhost:1501 is used by default, see baseUrl in Cypress.json
*
* @param {String|String[]}
*/
Cypress.Commands.overwrite('visit', (originalFn, options) => {
    let url = '';

    if (Array.isArray(options)) {
        url = buildUrl(options);
    }
    else if ((typeof options) === 'string') {
        url = options;
    }
    else {
        url = buildUrl([]);
    }

    return originalFn(url);
});

/**
 * Sets checkLogout to true - logout in the global afterEach hook will check to
 * see if the logout button is available before attempting to logout.
 */
Cypress.Commands.add('checkLogoutInAfterEach', () => {
    cy.wrap(true).as('checkLogout');
});

/**
 * Wait and reload until
 * @param {} condition
 * @param {int} timeout
 * @param {int} wait
 */
Cypress.Commands.add('waitAndReloadUntil', (condition, timeout, wait = 100) => {
    // eslint-disable-next-line cypress/no-unnecessary-waiting
    cy.wait(wait);
    cy.reload();
    cy.then(() => {
        return condition().then((result) => {
            if (result || timeout <= 0) {
                return result;
            }
            // eslint-disable-next-line no-restricted-syntax
            return cy.waitAndReloadUntil(condition, timeout - wait, wait);
        });
    });
});
