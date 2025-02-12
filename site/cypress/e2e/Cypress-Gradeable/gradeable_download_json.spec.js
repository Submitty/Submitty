import { getCurrentSemester } from '../../support/utils';
import { rubric } from '../../support/api_testing_json';

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
    it('Should download file, and have the correct values', () => {
        cy.login('instructor');

        cy.visit(['sample', 'gradeable', 'bulk_upload_test', 'update']);

        cy.get('[data-testid="download-gradeable-btn"]').click();

        cy.readFile('cypress/downloads/bulk_upload_test.json').then((test_json) => {
            expect(test_json.title).to.eql('Bulk Upload Scanned Exam');
            expect(test_json.type).to.eql('Electronic File');
            expect(test_json.id).to.eql('bulk_upload_test');
            expect(test_json.instructions_url).to.eql('');
            expect(test_json.syllabus_bucket).to.eql('homework');
            expect(test_json.bulk_upload).to.eql(true);
            expect(test_json.ta_grading).to.eql(true);
            expect(test_json.grade_inquiries).to.eql(true);
        });

        cy.visit(['sample', 'gradeable', 'subdirectory_vcs_homework', 'update']);

        cy.get('[data-testid="download-gradeable-btn"]').click();

        cy.readFile('cypress/downloads/subdirectory_vcs_homework.json').then((test_json) => {
            expect(test_json.title).to.eql('Subdirectory VCS Homework');
            expect(test_json.type).to.eql('Electronic File');
            expect(test_json.id).to.eql('subdirectory_vcs_homework');
            expect(test_json.instructions_url).to.eql('');
            expect(test_json.syllabus_bucket).to.eql('homework');
            expect(test_json.bulk_upload).to.eql(false);
            expect(test_json.ta_grading).to.eql(true);
            expect(test_json.grade_inquiries).to.eql(true);
            expect(test_json.vcs).to.exist;
            expect(test_json.vcs.subdirectory).to.eql('src');
            expect(test_json.vcs.repository_type).to.eql('submitty-hosted');
        });

        cy.visit(['sample', 'gradeable', 'open_team_homework', 'update']);

        cy.get('[data-testid="download-gradeable-btn"]').click();

        cy.readFile('cypress/downloads/open_team_homework.json').then((test_json) => {
            expect(test_json.title).to.eql('Open Team Homework');
            expect(test_json.type).to.eql('Electronic File');
            expect(test_json.id).to.eql('open_team_homework');
            expect(test_json.instructions_url).to.eql('');
            expect(test_json.syllabus_bucket).to.eql('homework');
            expect(test_json.bulk_upload).to.eql(false);
            expect(test_json.ta_grading).to.eql(true);
            expect(test_json.grade_inquiries).to.eql(true);
            expect(test_json.team_gradeable).to.exist;
            expect(test_json.team_gradeable.team_size_max).to.eql(3);
            expect(test_json.team_gradeable.inherit_from).to.eql('');
            expect(test_json.vcs).to.not.exist;
            expect(test_json.rubric).to.eql(rubric);
        });
    });

    it('API should return the same values', () => {
        getKey('instructor', 'instructor').then((key) => {
            // Gradeable already exists
            cy.request({
                method: 'GET',
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/open_team_homework/download`,
                headers: {
                    Authorization: key,
                },
                // Needs body to return a JSON object instead of string
                body: {
                },
            }).then((response) => {
                expect(response.body.data.title).to.eql('Open Team Homework');
                expect(response.body.data.type).to.eql('Electronic File');
                expect(response.body.data.id).to.eql('open_team_homework');
                expect(response.body.data.instructions_url).to.eql('');
                expect(response.body.data.syllabus_bucket).to.eql('homework');
                expect(response.body.data.bulk_upload).to.eql(false);
                expect(response.body.data.ta_grading).to.eql(true);
                expect(response.body.data.grade_inquiries).to.eql(true);
                expect(response.body.data.team_gradeable).to.exist;
                expect(response.body.data.team_gradeable.team_size_max).to.eql(3);
                expect(response.body.data.team_gradeable.inherit_from).to.eql('');
                expect(response.body.data.vcs).to.not.exist;
                expect(response.body.data.rubric).to.eql(rubric);
            });

            cy.request({
                method: 'GET',
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/bulk_upload_test/download`,
                headers: {
                    Authorization: key,
                },
                // Needs body to return a JSON object instead of string
                body: {
                },
            }).then((response) => {
                expect(response.body.data.title).to.eql('Bulk Upload Scanned Exam');
                expect(response.body.data.type).to.eql('Electronic File');
                expect(response.body.data.id).to.eql('bulk_upload_test');
                expect(response.body.data.instructions_url).to.eql('');
                expect(response.body.data.syllabus_bucket).to.eql('homework');
                expect(response.body.data.bulk_upload).to.eql(true);
                expect(response.body.data.ta_grading).to.eql(true);
                expect(response.body.data.grade_inquiries).to.eql(true);
            });
        });
    });
});
