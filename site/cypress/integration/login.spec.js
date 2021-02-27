import {buildUrl} from '../support/commands.js';


describe('Test cases revolving around the logging in functionality of the site', () => {
	it('should log in through root endpoint', () => {
		const user = 'instructor';

		//should hit the login form
		cy.visit('/');
		cy.url().should('eq', `${Cypress.config('baseUrl')}/authentication/login` );

		//fill out the login form and hit submit
		cy.get('input[name=user_id]').type(user);
		cy.get('input[name=password]').type(user);
		cy.get('input[name=login]').click();

		//should now be logged in as instructor and have a loggout button
		cy.get('#logout .icon-title').should((val) => {
			expect( val.text().trim() ).to.equal('Logout Quinn');
		});

		cy.getCookies().then((cookies) => {
			expect(cookies[2]).to.have.property('name', 'submitty_token');
			expect(cookies[2]['value']).to.match(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
		});
	});


	it('should login through login endpoint', () => {
		const url = '/authentication/login';

		cy.visit(url);
		cy.url().should('eq', `${Cypress.config('baseUrl')}/authentication/login` );

		cy.login();
		cy.get('#logout .icon-title').should((val) => {
			expect( val.text().trim() ).to.equal('Logout Quinn');
		});
	});


	it('should reject bad passwords', () => {
		cy.visit('/')

		cy.get('input[name=user_id]').type('instructor');
		cy.get('input[name=password]').type('bad-password');
		cy.get('input[name=login]').click();

		cy.get('#error-0').should((val) => {
			expect( val.text().trim() ).to.equal('Could not login using that user id or password');
		});
	});


	it('should reject bad usernames', () => {
		cy.visit('/')

		cy.get('input[name=user_id]').type('bad-username');
		cy.get('input[name=password]').type('instructor');
		cy.get('input[name=login]').click();

		cy.get('#error-0').should((val) => {
			expect( val.text().trim() ).to.equal('Could not login using that user id or password');
		});
	});


	it('should redirect after logging in', () => {
		//try to visit a page not logged in, then log in and see where we are
		const url = buildUrl(['sample', 'gradeable', 'open_homework']); 
		const full_url = buildUrl(['sample', 'gradeable', 'open_homework'], true);
		const user = 'instructor';

		cy.visit(url);
		cy.url().should('eq', `${Cypress.config('baseUrl')}/authentication/login?old=${encodeURIComponent(full_url)}`);

		cy.get('input[name=user_id]').type(user);
		cy.get('input[name=password]').type(user);
		cy.get('input[name=login]').click();

		cy.url().should('eq', full_url);
	});


	it('should check if you can access a course', () => {
		const url = buildUrl(['sample']);

		cy.login('pearsr');
		cy.visit(url);

		cy.get('.content').should('have.text', "\n    You don't have access to this course. \n    This is sample for Spring 2021. \n    If you think this is a mistake, please contact your instructor to gain access. \n    click  here  to back to homepage and see your courses list.\n");
	})


});