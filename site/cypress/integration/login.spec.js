import {buildUrl} from '../support/utils.js';

describe('Test cases revolving around the logging in functionality of the site', () => {
    describe('Test cases where the user should succesfully login', () => {
        it('should log in through root endpoint', () => {
            //should hit the login form
            cy.visit('/');
            cy.url().should('eq', `${Cypress.config('baseUrl')}/authentication/login` );

            cy.login();

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
            cy.visit('authentication/login');
            cy.url().should('eq', `${Cypress.config('baseUrl')}/authentication/login` );
            cy.login();

            cy.get('#logout .icon-title').should((val) => {
                expect( val.text().trim() ).to.equal('Logout Quinn');
            });
        });


        it('should redirect after logging in', () => {
            //try to visit a page not logged in, then log in and see where we are
            const full_url = buildUrl(['sample', 'config'], true);

            cy.visit(['sample', 'config']);
            cy.url().should('eq', `${Cypress.config('baseUrl')}/authentication/login?old=${encodeURIComponent(full_url)}`);

            cy.login();

            cy.url().should('eq', full_url);
        });


        it('should check if you can access a course', () => {
            cy.visit('authentication/login');
            cy.url().should('eq', `${Cypress.config('baseUrl')}/authentication/login` );
            cy.login('pearsr');
            cy.get('#courses > h1').contains('My Courses');
            cy.visit(['sample']);
            cy.url().should('eq', `${Cypress.config('baseUrl')}/courses/s22/sample/no_access`);
            cy.get('.content').contains('You don\'t have access to this course.');
        });
    });


    describe('Test cases where the user should not be able to login', () => {
        it('should reject bad passwords', () => {
            cy.checkLogoutInAfterEach();
            cy.visit([]);
            cy.url().should('eq', `${Cypress.config('baseUrl')}/authentication/login` );

            cy.get('body')
                .then(body => {
                    if (body.find('input[name=user_id]').length > 0) {
                        cy.get('input[name=user_id]').type('instructor');
                        cy.get('input[name=password]').type('bad-password');
                        cy.get('input[name=login]').click();
                        cy.get('#error-0').should((val) => {
                            expect( val.text().trim() ).to.equal('Could not login using that user id or password');
                        });
                    }
                    else {
                        cy.get('#saml-login').click();
                        cy.get('input[name=username]').type('instructor', {force: true});
                        cy.get('input[name=password]').type('bad-password', {force: true});
                        cy.get('#submit > td:nth-child(3) > button').click();
                        cy.get('#content > div > p:nth-child(3) > strong').should((val) => {
                            expect(val.text().trim()).to.equal('Incorrect username or password');
                        });
                    }
                });
        });


        it('should reject bad usernames', () => {
            cy.checkLogoutInAfterEach();
            cy.visit([]);
            cy.url().should('eq', `${Cypress.config('baseUrl')}/authentication/login` );

            cy.get('body')
                .then(body => {
                    if (body.find('input[name=user_id]').length > 0) {
                        cy.get('input[name=user_id]').type('bad-username');
                        cy.get('input[name=password]').type('instructor');
                        cy.get('input[name=login]').click();
                        cy.get('#error-0').should((val) => {
                            expect( val.text().trim() ).to.equal('Could not login using that user id or password');
                        });
                    }
                    else {
                        cy.get('#saml-login').click();
                        cy.get('input[name=username]').type('bad-username', {force: true});
                        cy.get('input[name=password]').type('instructor', {force: true});
                        cy.get('#submit > td:nth-child(3) > button').click();
                        cy.get('#content > div > p:nth-child(3) > strong').should((val) => {
                            expect(val.text().trim()).to.equal('Incorrect username or password');
                        });
                    }
                });
        });
    });
});
