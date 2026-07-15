import { getApiKey } from '../../support/utils';

describe('Tests cases revolving around term creation', () => {
    const timestamp = Date.now();

    it('Should create a new term and show error when term already exists', () => {
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
                    expect(response.body.message).to.eql('Term with that ID already exists.');
                });
            });
        });
    });

    it('Should show error when end date is before start date', () => {
        getApiKey('superuser', 'superuser').then((key) => {
            cy.request({
                method: 'POST',
                url: `${Cypress.config('baseUrl')}/api/terms`,
                body: {
                    term_id: `bad_date${timestamp}`,
                    term_name: 'Bad Date Term',
                    start_date: '2021-05-31',
                    end_date: '2021-01-01',
                },
                headers: {
                    Authorization: key,
                },
            }).then((response) => {
                expect(response.body.message).to.eql('End date should be after Start date.');
            });
        });
    });

    it('Should show error when fields are missing', () => {
        getApiKey('superuser', 'superuser').then((key) => {
            cy.request({
                method: 'POST',
                url: `${Cypress.config('baseUrl')}/api/terms`,
                body: {
                    term_name: 'Missing Term Data',
                },
                headers: {
                    Authorization: key,
                },
            }).then((response) => {
                expect(response.body.message).to.eql('Term ID, term name, start date, or end date not set.');
            });
        });
    });

    it('Should show error when term length exceeds 360 days', () => {
        getApiKey('superuser', 'superuser').then((key) => {
            cy.request({
                method: 'POST',
                url: `${Cypress.config('baseUrl')}/api/terms`,
                body: {
                    term_id: `long_term${timestamp}`,
                    term_name: 'Too Long Term',
                    start_date: '2022-01-01',
                    end_date: '2023-06-01',
                },
                headers: {
                    Authorization: key,
                },
            }).then((response) => {
                expect(response.body.status).to.eql('fail');
                expect(response.body.message).to.match(/^Term length cannot exceed 360 days \(this term spans \d+ days\)\.$/);
            });
        });
    });
});
