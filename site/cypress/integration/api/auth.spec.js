import {buildPostRequest, buildGetRequest} from '../../support/utils.js';

describe('Test cases revolving around the API', () => {
    it('should authenticate a user', () => {
        cy.request(buildPostRequest('api/token', {
            'user_id': 'instructor',
            'password': 'instructor',
        })).should((response) => {
            expect(response).to.have.property('body');

            const response_json = JSON.parse(response.body);
            expect(response_json['status']).to.equal('success');
            expect(response_json['data']).to.have.property('token');
        });
    });

    [
        ['no user_id or password', {'foo': 'bar'}],
        ['no password', { 'user_id': 'instructor'}],
        ['no user_id', {'password': 'instructor'}],
    ].forEach(([title, postBody]) => {
        it(`should require a user_id and password - ${title}`, () => {
            cy.request(buildPostRequest('api/token', postBody)).should((response) => {
                const data = JSON.parse(response.body);
                expect(data['status']).to.equal('fail');
                expect(data['message']).to.equal('Cannot leave user id or password blank');
            });
        });
    });

    it('should invalidate older tokens on request', () => {
        cy.request(buildPostRequest('api/token', {
            'user_id': 'instructor',
            'password': 'instructor',
        })).as('old_response');

        cy.get('@old_response').then((old) => {
            cy.request(buildPostRequest('api/token/invalidate', 'POST', {
                'user_id': 'instructor',
                'password': 'instructor',
            }));

            cy.request(buildPostRequest('api/token', {
                'user_id': 'instructor',
                'password': 'instructor',
            })).should((response) => {
                const data = JSON.parse(response.body);
                const old_data = JSON.parse(old.body);
                expect(data['data']['token']).to.not.equal(old_data['data']['token']);

                cy.request(buildGetRequest('api/courses', old_data['data']['token']))
                    .should((response) => {
                        const data = JSON.parse(response.body);
                        expect(data['status']).to.equal('fail');
                        expect(data['message']).to.equal('Unauthenticated access. Please log in.');
                    });
            });
        });
    });
});
