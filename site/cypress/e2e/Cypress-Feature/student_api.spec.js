import {getApiKey} from '../../support/utils';
import {getCurrentSemester} from '../../support/utils';

describe('Tests cases for the Student API', () => {
    it('Should get correct responses', () => {

        getApiKey('student', 'student').then((key) => {
            // Success
            cy.request({
                method: 'GET',
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/gradeable/subdirectory_vcs_homework/score?user_id=student`,
                headers: {
                    Authorization: key,
                }, body: {
                },
            }).then((response) => {
                expect(response.body.status).to.equal('success');
                // Gradeables aren't graded in CI to test for accurate score count.
            });
            // Success, successfully sent to be graded
            cy.request({
                method: 'POST',
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/gradeable/subdirectory_vcs_homework/grade`,
                headers: {
                    Authorization: key,
                }, body: {
                    'user_id': 'student',
                    'vcs_checkout': 'true',
                    'git_repo_id': 'none',
                },
            }).then((response) => {
                expect(response.body.status).to.equal('success');
                expect(response.body.data).to.contain('Successfully uploaded version').and.to.contain('for Subdirectory VCS Homework');
            });
            // Fail
            cy.request({
                method: 'POST',
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/gradeable/subdirectory_vcs_homework/score`,
                headers: {
                    Authorization: key,
                }, body:{},
            }).then((response) => {
                expect(response.body.status).to.equal('fail');
                expect(response.body.message).to.equal('Method not allowed.');
            });

            // Fail, invalid API key
            cy.request({
                method: 'GET',
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/gradeable/subdirectory_vcs_homework/score`,
                headers: {
                    Authorization: 'key',
                }, body:{},
            }).then((response) => {
                expect(response.body.status).to.equal('fail');
                expect(response.body.message).to.equal('Unauthenticated access. Please log in.');
            });
            // Fail, API key not for given user_id
            cy.request({
                method: 'GET',
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/gradeable/subdirectory_vcs_homework/score?user_id=not_student`,
                headers: {
                    Authorization: key,
                }, body:{
                },
            }).then((response) => {
                expect(response.body.status).to.equal('fail');
                expect(response.body.message).to.equal('API key and specified user_id are not for the same user.');
            });
            // Fail, endpoint not found.
            cy.request({
                method: 'GET',
                url: `${Cypress.config('baseUrl')}/api/not/found/url`,
                headers: {
                    Authorization: key,
                }, body:{},
            }).then((response) => {
                expect(response.body.status).to.equal('fail');
                expect(response.body.message).to.equal('Endpoint not found.');
            });

            // Specific fails for score API
            // Gradeable doesn't exist
            cy.request({
                method: 'GET',
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/gradeable/not_found_gradeable/score?user_id=student`,
                headers: {
                    Authorization: key,
                }, body: {
                },
            }).then((response) => {
                expect(response.body.status).to.equal('fail');
                expect(response.body.message).to.equal('Gradeable does not exist');
            });
            // Ungraded gradeable
            cy.request({
                method: 'GET',
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/gradeable/open_vcs_homework/score?user_id=student`,
                headers: {
                    Authorization: key,
                }, body: {
                },
            }).then((response) => {
                expect(response.body.status).to.equal('success');
                expect(response.body.message).to.equal(null);
            });
        });
    });
});
