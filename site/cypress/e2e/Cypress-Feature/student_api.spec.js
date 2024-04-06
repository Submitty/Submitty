import {getApiKey} from '../../support/utils';
import {getCurrentSemester} from '../../support/utils';

describe('Tests cases for the Student API', () => {
    it('Should get correct responses', () => {
        // Success (0)
            getApiKey('instructor', 'instructor').then((key) => {
            cy.request({
                method: 'GET',
                url: `${Cypress.config('baseUrl')}/api/student_api/${getCurrentSemester()}/sample/gradeable/subdirectory_vcs_homework/score`,
                headers: {
                    Authorization: key,
                },
            }).then((response) => {
                expect(response.body.data).to.eql(0);
            });
        // Success
            cy.request({
                method: 'POST',
                url: `${Cypress.config('baseUrl')}/api/student_api/${getCurrentSemester()}/sample/gradeable/subdirectory_vcs_homework/upload`,
                headers: {
                    Authorization: key,
                }, body: {
                    'git_repo_id': 'none',
                    'user_id': 'instructor',
                    'vcs_checkout': 'true'
                },
            }).then((response) => {
                console.log(response);
                expect(response.body.status).to.equal('success');
            });

            cy.request({
                method: 'POST',
                url: `${Cypress.config('baseUrl')}/api/student_api/${getCurrentSemester()}/sample/gradeable/subdirectory_vcs_homework/score`,
                headers: {
                    Authorization: key,
                },
            }).then((response) => {
                console.log(response);
                expect(JSON.parse(response.body)['status']).to.equal('fail');
                expect(response.body.status).to.equal('fail');
            });
        });
    });
});
