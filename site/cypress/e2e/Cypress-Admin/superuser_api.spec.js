import { getApiKey } from '../../support/utils';

describe('Tests cases revolving around term creation', () => {
    it('Should create a new term', () => {
        getApiKey('superuser', 'superuser').then((key) => {
            cy.request({
                method: 'POST',
                url: `${Cypress.config('baseUrl')}/api/terms`,
                body: {
                    term_id: 's20',
                    term_name: 'Spring 2020',
                    start_date: '2020-01-01',
                    end_date: '2020-05-31',
                },
                headers: {
                    Authorization: key,
                },
            }).then((response) => {
                expect(response.body.status).to.eql('success');
                expect(response.body.data.term_id).to.eql('s20');
                expect(response.body.data.term_name).to.eql('Spring 2020');
                expect(response.body.data.start_date).to.eql('2020-01-01');
                expect(response.body.data.end_date).to.eql('2020-05-31');
            });
        });
    });

    it('Should show error when term already exists', () => {
        getApiKey('superuser', 'superuser').then((key) => {
            cy.request({
                method: 'POST',
                url: `${Cypress.config('baseUrl')}/api/terms`,
                body: {
                    term_id: 's26',
                    term_name: 'Spring 2026',
                    start_date: '2026-01-02',
                    end_date: '2026-06-30',
                },
                headers: {
                    Authorization: key,
                },
            }).then((response) => {
                expect(response.body.message).to.eql('Term with that ID already exists.');
            });
        });
    });

    it('Should show error when end date is before start date', () => {
        getApiKey('superuser', 'superuser').then((key) => {
            cy.request({
                method: 'POST',
                url: `${Cypress.config('baseUrl')}/api/terms`,
                body: {
                    term_id: 's21',
                    term_name: 'Spring 2021',
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
                    term_id: 's22',
                },
                headers: {
                    Authorization: key,
                },
            }).then((response) => {
                expect(response.body.message).to.eql('Term ID, term name, start date, or end date not set.');
            });
        });
    });
});
