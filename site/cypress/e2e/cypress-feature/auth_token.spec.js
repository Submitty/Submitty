import {getCurrentSemester} from '../support/utils';

describe('Test cases revolving around authentication tokens', () => {
    beforeEach(() => {
        cy.visit('/');
        cy.login();
        cy.visit('/authentication_tokens');
    });

    it('Should create new token, receive it\'s value back, and pass vcs_login', () => {
        cy.get('#main > div.content > p:nth-child(16)').should('have.text', 'You don\'t have any Authentication Tokens.');

        cy.get('#new-auth-token-button').click();

        cy.get('#new-auth-token-name').type('Desktop');

        cy.get('#new-auth-token-expiration').select('Never Expires');

        cy.get('#new-auth-token-form > form > div > div > div.form-body > div.form-buttons > div > input').click();

        cy.get('#new-token-banner > p:nth-child(6)').contains('Value: ');

        let cookie;

        cy.getCookie('submitty_session').then(c => {
            cookie = c.value;
        });
        // Clear the cookie so we can access the vcs_login route
        cy.clearCookies();

        cy.get('#new-token-banner > p:nth-child(6)').invoke('text').then(text => {
            const token = text.trim().split(' ')[1];
            // Verify the token works as a password
            cy.request({
                method: 'POST',
                url: `${Cypress.config('baseUrl')}/${getCurrentSemester()}/sample/authentication/vcs_login`,
                form: true,
                body: {
                    user_id: 'instructor',
                    password: token,
                    gradeable_id: 'vcstest',
                    id: 'instructor',
                },
            }).then(res => {
                const body = JSON.parse(res.body);
                expect(res.status).to.eq(200);
                expect(body.status).to.eq('success');
            });

            // Verify normal password authentication still works
            cy.request({
                method: 'POST',
                url: `${Cypress.config('baseUrl')}/${getCurrentSemester()}/sample/authentication/vcs_login`,
                form: true,
                body: {
                    user_id: 'instructor',
                    password: 'instructor',
                    gradeable_id: 'vcstest',
                    id: 'instructor',
                },
            }).then(res => {
                const body = JSON.parse(res.body);
                expect(res.status).to.eq(200);
                expect(body.status).to.eq('success');
            });

            // Verify a bad password or token fails
            cy.request({
                method: 'POST',
                url: `${Cypress.config('baseUrl')}/${getCurrentSemester()}/sample/authentication/vcs_login`,
                form: true,
                body: {
                    user_id: 'instructor',
                    password: 'bad_password_or_token',
                    gradeable_id: 'vcstest',
                    id: 'instructor',
                },
            }).then(res => {
                const body = JSON.parse(res.body);
                expect(res.status).to.eq(200);
                expect(body.status).to.eq('fail');
            });

            // Restore the old cookie so we can test deleting the cookie
            cy.setCookie('submitty_session', cookie);

            cy.get('#main > div.content > table > tbody > tr:nth-child(2) > td:nth-child(1)').should('have.text', 'Desktop');
            cy.get('#main > div.content > table > tbody > tr:nth-child(2) > td:nth-child(2)').should('have.text', 'Never expires');

            cy.get('#main > div.content > table > tbody > tr:nth-child(2) > td:nth-child(3) > form > input.btn.btn-danger').click();
            cy.get('#main > div.content > p:nth-child(16)').should('have.text', 'You don\'t have any Authentication Tokens.');

        });
    });
});
