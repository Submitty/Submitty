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
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/gradeable/subdirectory_vcs_homework/values`,
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
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/gradeable/not_found_gradeable/values`,
                headers: {
                    Authorization: key,
                }, body: {
                },
            }).then((response) => {
                expect(response.body.status).to.equal('fail');
                expect(response.body.message).to.equal('Gradeable does not exist');
            });

            // Lists gradeables
            cy.request({
                method: 'GET',
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/gradeables`,
                headers: {
                    Authorization: key,
                }, body: {
                },
            }).then((response) => {
                expect(response.body.status).to.equal('success');
                const data = response.body.data;
                // VCS Subdirectory homework
                expect(data.subdirectory_vcs_homework.id).equal('subdirectory_vcs_homework');
                expect(data.subdirectory_vcs_homework.title).equal('Subdirectory VCS Homework');
                expect(data.subdirectory_vcs_homework.instructions_url).equal('');
                expect(data.subdirectory_vcs_homework.syllabus_bucket).equal('homework');
                expect(data.subdirectory_vcs_homework.section).equal(2);
                expect(data.subdirectory_vcs_homework.section_name).equal('OPEN');
                expect(data.subdirectory_vcs_homework.due_date.date).equal('9996-12-31 23:59:59.000000');
                expect(data.subdirectory_vcs_homework.due_date.timezone_type).equal(3);
                expect(data.subdirectory_vcs_homework.due_date.timezone).equal('America/New_York');
                expect(data.subdirectory_vcs_homework.gradeable_type).equal('Electronic File');
                expect(data.subdirectory_vcs_homework.vcs_repository).equal(`http://localhost/git/${getCurrentSemester()}/sample/subdirectory_vcs_homework/student`);
                expect(data.subdirectory_vcs_homework.vcs_subdirectory).equal('src');

                // Open homework
                expect(data.open_homework.id).equal('open_homework');
                expect(data.open_homework.title).equal('Open Homework');
                expect(data.open_homework.instructions_url).equal('');
                expect(data.open_homework.syllabus_bucket).equal('homework');
                expect(data.open_homework.section).equal(2);
                expect(data.open_homework.section_name).equal('OPEN');
                expect(data.open_homework.due_date.date).equal('9996-12-31 23:59:59.000000');
                expect(data.open_homework.due_date.timezone_type).equal(3);
                expect(data.open_homework.due_date.timezone).equal('America/New_York');
                expect(data.open_homework.gradeable_type).equal('Electronic File');
                expect(data.open_homework.vcs_repository).equal('');
                expect(data.open_homework.vcs_subdirectory).equal('');
            });

            // Test /api/me
            cy.request({
                method: 'GET',
                url: `${Cypress.config('baseUrl')}/api/me`,
                headers: {
                    Authorization: key,
                }, body: {
                },
            }).then((response) => {
                expect(response.body.status).to.equal('success');
                const data = response.body.data;
                expect(data.user_id).to.equal('student');
                expect(data.user_given_name).to.equal('Joe');
                expect(data.user_family_name).to.equal('Student');
            });
        });
    });
});
