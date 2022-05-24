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
import {buildUrl} from './utils.js';
//These functions can be called like "cy.login(...)" and will yeild a result

/**
* Log into Submitty, assumes no one is logged in already and at login page
*
* @param {String} [username=instructor] - username & password of who to log in as
*/
Cypress.Commands.add('login', (username='instructor') => {
    cy.get('body')
        .then(body => {
            if (body.find('input[name=user_id]').length > 0) {
                cy.get('input[name=user_id]').type(username, {force: true});
                cy.get('input[name=password]').type(username, {force: true});
                cy.get('input[name=login]').click();
            }
            else {
                cy.get('#saml-login').should('have.attr', 'href').then(href => {
                    cy.request(href).then((res) => {
                        const url = new URL(res.redirects[1].split(" ")[1]);
                        const authState = url.searchParams.get('AuthState');
                        cy.request({
                            url: url.origin + url.pathname,
                            method: 'POST',
                            body: {
                                username: username,
                                password: username,
                                AuthState: authState
                            },
                            form: true
                        }).then(r => {
                            const parser = new DOMParser();
                            const html = parser.parseFromString(r.body, 'text/html');
                            const inputs = html.getElementsByTagName("input");
                            let samlRes = '';
                            let relay = '';
                            for (const input of inputs) {
                                switch (input.name) {
                                    case 'SAMLResponse':
                                        samlRes = input.value;
                                        break;
                                    case 'RelayState':
                                        relay = input.value;
                                        break;
                                }
                            }
                            const action = html.getElementsByTagName("form")[0].action;
                            expect(action).to.equal(`${Cypress.config('baseUrl')}/authentication/check_login`);
                            cy.request({
                                url: action,
                                method: 'POST',
                                body: {
                                    SAMLResponse: samlRes,
                                    RelayState: relay
                                },
                                form: true
                            }).then(res => {
                                expect(res.status).to.equal(200);
                                cy.visit(res.url);
                            });
                            //console.log(r);
                        });
                    });
                });
                /*cy.get('#saml-login').click();
                cy.get('input[name=username]').type(username, {force: true});
                cy.get('input[name=password]').type(username, {force: true});
                cy.get('#submit > td:nth-child(3) > button').click();*/
            }
        });
});

/**
* Log out of Submitty, assumes a user is already logged in
*/
Cypress.Commands.add('logout', () => {
    cy.get('#logout > .flex-line > .icon-title').click();
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

    if (Array.isArray(options)){
        url = buildUrl(options);
    }
    else if ((typeof options) === 'string'){
        url = options;
    }
    else {
        url = buildUrl([]);
    }

    originalFn(url);
});
