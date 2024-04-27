import {getApiKey} from '../../support/utils';
import {getCurrentSemester} from '../../support/utils';

describe('Tests cases for the Student API', () => {
    it('Should get correct responses', () => {

        getApiKey('student', 'student').then((key) => {
            // Success
            cy.request({
                method: 'GET',
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/gradeable/subdirectory_vcs_homework/values?user_id=student`,
                headers: {
                    Authorization: key,
                }, body: {
                },
            }).then((response) => {
                expect(response.body.status).to.equal('success');
                // Remove these because they are different locally
                if (Cypress.env('run_area') !== 'CI') {
                    delete response.body.data['highest_version'];
                    delete response.body.data['total_points'];
                    delete response.body.data['total_percent'];
                    delete response.body.data['queue_position'];
                    expect(JSON.stringify(response.body.data)).to.equal(
                        JSON.stringify(
                            {
                                'is_queued': false,
                                'is_grading': false,
                                'has_submission': true,
                                'autograding_complete': true,
                                'has_active_version': true,
                            }));
                }
                else {
                    expect(JSON.stringify(response.body.data)).to.equal(
                        JSON.stringify(
                            {
                                'is_queued': false,
                                'queue_position': 0,
                                'is_grading': true,
                                'has_submission': true,
                                'autograding_complete': false,
                                'has_active_version': true,
                                'highest_version': 2,
                                'total_points': 0,
                                'total_percent': 0,
                            }));
                }
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
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/gradeable/subdirectory_vcs_homework/values`,
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
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/gradeable/subdirectory_vcs_homework/values`,
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
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/gradeable/subdirectory_vcs_homework/values?user_id=not_student`,
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

            // Specific fails for values API
            // Gradeable doesn't exist
            cy.request({
                method: 'GET',
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/gradeable/not_found_gradeable/values?user_id=student`,
                headers: {
                    Authorization: key,
                }, body: {
                },
            }).then((response) => {
                expect(response.body.status).to.equal('fail');
                expect(response.body.message).to.equal('Gradeable does not exist');
            });
        });
    });
});
