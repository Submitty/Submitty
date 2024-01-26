import {getCurrentSemester} from '../../support/utils';

function getKey(user_id, password) {
    return cy.request({
        method: 'POST',
        url: `${Cypress.config('baseUrl')}/api/token`,
        body: {
            user_id: user_id,
            password: password,
        },
    }).then((response) => {
        return response.body.data.token;
    });
}

describe('Tests cases revolving around gradeable access and submition', () => {

    it('Should upload file, submit, view gradeable', () => {
        cy.login('instructor');

        const testfile1 = 'cypress/fixtures/json1.json';

        cy.visit(['sample', 'gradeable']);

        //Makes sure the clear button is not disabled by adding a file
        cy.get('[data-testid="upload-gradeable-btn"]').click();
        cy.get('[data-testid="popup-window"]').should('be.visible');
        cy.get('[data-testid="popup-window"]').should('contain.text', 'Upload JSON for Gradeable');
        cy.get('[data-testid="upload"]').selectFile(testfile1, {action: 'drag-drop'});
        cy.get('[data-testid="submit"]').click();
        cy.get('[data-testid="upload-gradeable-btn"]', { timeout: 10000 }).should('not.exist');
        cy.get('body').should('contain.text', 'Edit Gradeable');
    });

    it('Should get error JSON responses', () => {
        getKey('instructor', 'instructor').then((key) => {
            cy.request({
                method: 'POST',
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/upload`,
                body: {
                    type: 'Checkpoint',
                    id: 'TestingJson',
                    title: 'Testing Json',
                },
                headers: {
                    Authorization: key,
                },
            }).then((response) => {
                expect(response.body.message).to.eql('Gradeable already exists');
            });
        });
    });
});
