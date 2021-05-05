function buildPostRequest(url, body, auth = null){
    const header = {'Content-Type': 'application/json'};
    if (auth !== null){
        header['Authorization'] = auth;
    }

    return {
        'method' : 'POST',
        'url' : url,
        'headers' : header,
        'body' : JSON.stringify(body),
    };
}

function buildGetRequest(url, auth = null){
    const header = {'Content-Type': 'application/json'};
    if (auth !== null){
        header['Authorization'] = auth;
    }

    return {
        'method' : 'POST',
        'url' : url,
        'headers' : header,
    };
}

describe('Test cases revolving around the API', () => {

    it('should authenticate a user', () => {
        cy.request(buildPostRequest('api/token', {
            'user_id' : 'instructor',
            'password': 'instructor',
        })).should((response) => {
            expect(response).to.have.property('body');

            const response_json = JSON.parse(response.body);
            expect(response_json['status']).to.equal('success');
            expect(response_json['data']).to.have.property('token');
        });
    });


    it('should require a user_id and password', () => {
        cy.request(buildPostRequest('api/token', {
            'foo' : 'bar',
        })).should((response) => {
            const data = JSON.parse(response.body);
            expect(data['status']).to.equal('fail');
            expect(data['message']).to.equal('Cannot leave user id or password blank');
        });
    });


    it.only('should invalidate older tokens on request', () => {

        cy.request(buildPostRequest('api/token', {
            'user_id' : 'instructor',
            'password': 'instructor',
        })).as('old_response');


        cy.get('@old_response').then((old) => {
            cy.request(buildPostRequest('api/token/invalidate', 'POST', {
                'user_id' : 'instructor',
                'password': 'instructor',
            }));

            cy.request(buildPostRequest('api/token', {
                'user_id' : 'instructor',
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
