import {getApiKey} from '../../support/utils';
import {getCurrentSemester} from '../../support/utils';

describe('Tests cases revolving around gradeable access and submition', () => {
    it('Should get Success responses', () => {
            getApiKey('instructor', 'instructor').then((key) => {
            cy.request({
                method: 'GET',
                url: `${Cypress.config('baseUrl')}/api/student_api/${getCurrentSemester()}/sample/gradeable/subdirectory_vcs_homework/score`,
                headers: {
                    Authorization: key,
                },
            }).then((response) => {
                expect(JSON.parse(response.body)['data']).to.eql(0);
            });
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
        });
    });
});
