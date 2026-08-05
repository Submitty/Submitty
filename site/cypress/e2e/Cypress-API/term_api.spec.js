import { getApiKey } from '../../support/utils';

describe('Tests term creation success path', () => {
    const timestamp = Date.now();

    it('Should successfully create a new term', () => {
        getApiKey('superuser', 'superuser').then((key) => {
            cy.request({
                method: 'POST',
                url: `${Cypress.config('baseUrl')}/api/terms`,
                body: {
                    term_id: `test${timestamp}`,
                    term_name: `Test Term ${timestamp}`,
                    start_date: '2020-01-01',
                    end_date: '2020-05-31',
                },
                headers: {
                    Authorization: key,
                },
            }).then((response) => {
                expect(response.body.status).to.eql('success');
                expect(response.body.data.term_id).to.eql(`test${timestamp}`);
                expect(response.body.data.term_name).to.eql(`Test Term ${timestamp}`);
                expect(response.body.data.start_date).to.eql('2020-01-01');
                expect(response.body.data.end_date).to.eql('2020-05-31');
            });
        });
    });
});
