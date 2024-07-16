import { getApiKey } from '../../support/utils';
import { getCurrentSemester } from '../../support/utils';

describe('Tests cases for the Student API', () => {
    it('Should get correct responses', () => {
        getApiKey('instructor', 'instructor').then((key) => {
            cy.request({
                method: 'GET',
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/gradeable/subdirectory_vcs_homework/values?user_id=student`,
                headers: {
                    Authorization: key,
                }, body: {
                },
            }).then((response) => {
                expect(response.body.status).to.equal('success');
                // Can't test exact values due to randomness of CI speed
                const data = JSON.stringify(response.body.data);
                expect(data).to.contain('is_queued');
                expect(data).to.contain('queue_position'),
                expect(data).to.contain('is_grading'),
                expect(data).to.contain('has_submission'),
                expect(data).to.contain('autograding_complete'),
                expect(data).to.contain('has_active_version'),
                expect(data).to.contain('highest_version'),
                expect(data).to.contain('total_points'),
                expect(data).to.contain('total_percent');
            });

            cy.request({
                method: 'GET',
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/gradeable/subdirectory_vcs_homework/values?user_id=not_a_student`,
                headers: {
                    Authorization: key,
                }, body: {
                },
            }).then((response) => {
                expect(response.body.status).to.equal('fail');
                expect(response.body.message).to.equal('Graded gradeable for user with id not_a_student does not exist');
            });
        });

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
                // Can't test exact values due to randomness of CI speed
                const data = response.body.data;
                const data_string = JSON.stringify(response.body.data);
                expect(data_string).to.contain('is_queued');
                expect(data_string).to.contain('queue_position'),
                expect(data_string).to.contain('is_grading'),
                expect(data_string).to.contain('has_submission'),
                expect(data_string).to.contain('autograding_complete'),
                expect(data_string).to.contain('has_active_version'),
                expect(data_string).to.contain('highest_version'),
                expect(data_string).to.contain('total_points'),
                expect(data_string).to.contain('total_percent');
                expect(data_string).to.contain('test_cases');
                // CI doesn't have grades
                // Requires VCS Subdirectory gradeable to be graded
                if (Cypress.env('run_area') !== 'CI') {
                    const python_test = {
                        name: 'Python test',
                        details: 'python3 *.py',
                        is_extra_credit: false,
                        points_available: 5,
                        points_received: 5,
                        testcase_message: '',
                    };
                    const submitted_pdf = {
                        name: 'Submitted a .pdf file',
                        details: '',
                        is_extra_credit: false,
                        points_available: 1,
                        points_received: 1,
                        testcase_message: '',
                    };
                    const words = {
                        name: 'Required 500-1000 Words',
                        details: '',
                        is_extra_credit: false,
                        points_available: 1,
                        points_received: 0,
                        testcase_message: '',
                    };
                    expect(data.test_cases[0]).to.contain(python_test);
                    expect(data.test_cases[1]).to.contain(submitted_pdf);
                    expect(data.test_cases[2]).to.contain(words);
                }
            });

            // Success, successfully sent to be graded
            cy.request({
                method: 'POST',
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/gradeable/subdirectory_vcs_homework/grade`,
                headers: {
                    Authorization: key,
                }, body: {
                    user_id: 'student',
                    vcs_checkout: 'true',
                    git_repo_id: 'none',
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
                }, body: {},
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
                }, body: {},
            }).then((response) => {
                expect(response.body.status).to.equal('fail');
                expect(response.body.message).to.equal('Unauthenticated access. Please log in.');
            });
            // Fail, API key not for given user_id
            cy.request({
                method: 'GET',
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/gradeable/subdirectory_vcs_homework/values?user_id=not_a_student`,
                headers: {
                    Authorization: key,
                }, body: {
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
                }, body: {},
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
