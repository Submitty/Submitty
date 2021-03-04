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

import {buildUrl} from './utils.js';
//These functions can be called like "cy.login(...)" and will yeild a result

/**
* Log into Submitty, assumes no one is logged in already
*
* @param {String} [username=instructor] - username & password of who to log in as
*/
Cypress.Commands.add("login", (username="instructor") => { 
	cy.visit([]);
	cy.get('input[name=user_id]').type(username);
	cy.get('input[name=password]').type(username);
	cy.get('input[name=login]').click();
});


Cypress.Commands.overwrite("visit", (originalFn, options) => { 
	let url = '';

	if(Array.isArray(options)){
		url = buildUrl(options);
	}else if((typeof options) === 'string'){
		url = buildUrl([]) + options;
	}else{
		url = buildUrl([]);
	}

	originalFn(url);
});
