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


//Adding a cypress function requires each function to yield a value instead of returning it directly
//These functions are defined in normal JS and can be imported instead


/**
* Generate a 3 letter semester code e.g s21, f20 based on today's data
* This functions the same as the submitty python util's get_current_semester
*
* @returns {String}
*/
export function getCurrentSemester(){
	const today = new Date();
	const year = today.getFullYear().toString().slice(2,4);	//get last two digits
	const semester = ((today.getMonth() + 1) < 7) ? "s" : "f";	//first half of year 'spring' rest is fall

	return semester + year;
}

/**
* Build a courseURL based on an array of 'parts', e.g [foo, bar] -> courses/s21/foo/bar
* 
* @param {String[]} [parts=[]] array of parts to string together
* @param {Boolean} [include_base=false] weather to include the url base (http://localhost:1501/) or not
*/
export function buildUrl(parts = [], include_base = false){
	let url = "";
	if(include_base){
		url = Cypress.config('baseUrl') + '/';
	}

	return `${url}courses/${getCurrentSemester()}/${ parts.join('/') }`
}


//These functions can be called like "cy.foo(...)" and will yeild a result

/**
* Log into Submitty, assumes no one is logged in already
*
* @param {String} [username=instructor] - username & password of who to log in as
*/
Cypress.Commands.add("login", (username="instructor") => { 
	cy.visit('/');
	cy.get('input[name=user_id]').type(username);
	cy.get('input[name=password]').type(username);
	cy.get('input[name=login]').click();

	//basic check to see if we're logged in
	cy.get('#nav-Wsidebar-my-courses .icon-title').should((val) => {
		const text = val.text().trim();

		expect(text).to.equal('My Courses');
	});
});

