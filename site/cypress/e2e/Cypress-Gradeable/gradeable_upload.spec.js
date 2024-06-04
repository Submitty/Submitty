import {getCurrentSemester} from '../../support/utils';
import {getApiKey} from '../../support/utils';
describe('Tests cases revolving around gradeable access and submition', () => {

    it('Should upload file, submit, view gradeable', () => {
        cy.login('instructor');

        const testfile1 = 'cypress/fixtures/json_ui.json';

        cy.visit(['sample', 'gradeable']);

        //Makes sure the clear button is not disabled by adding a file
        cy.get('[data-testid="upload-gradeable-btn"]').click();
        cy.get('[data-testid="popup-window"]').should('be.visible');
        cy.get('[data-testid="popup-window"]').should('contain.text', 'Upload JSON for Gradeable');
        cy.get('[data-testid="upload"]').selectFile(testfile1, {action: 'drag-drop'});
        cy.get('[data-testid="submit"]').click();
        cy.get('[data-testid="upload-gradeable-btn"]', { timeout: 10000 }).should('not.exist');
        cy.get('body').should('contain.text', 'Edit Gradeable');
        cy.get('[data-testid="ta_view_start_date"]').should('have.value', '2024-01-11 23:59:59');
        cy.get('[data-testid="team_lock_date"]').should('have.value', '2024-01-15 23:59:59');
        cy.get('[data-testid="submission_open_date"]').should('have.value', '2024-01-15 23:59:59');
        cy.get('[data-testid="submission_due_date"]').should('have.value', '2024-02-15 23:59:59');
        cy.get('[data-testid="release_date"]').should('have.value', '2024-03-15 23:59:59');
    });

    it('Should get error JSON responses', () => {
        getApiKey('instructor', 'instructor').then((key) => {
            // Gradeable already exists
            cy.request({
                method: 'POST',
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/upload`,
                body: {
                    'title': 'Testing Json',
                    'instructions_url': '',
                    'id': 'hw-1',
                    'type': 'Electronic File',
                    'vcs': {
                        'repository_type': 'submitty-hosted',
                        'vcs_path': 'path/to/vcs',
                        'vcs_subdirectory': 'subdirectory',
                    },
                    'bulk_upload': false,
                    'team_gradeable': {
                        'team_size_max': 3,
                        'gradeable_teams_read': false,
                    },
                    'grading_inquiry': {
                        'grade_inquiry_per_component_allowed': false,
                    },
                    'ta_grading': false,
                    'syllabus_bucket': 'Homework',
                },
                headers: {
                    Authorization: key,
                },
            }).then((response) => {
                expect(response.body.message).to.eql('Gradeable already exists');
            });

            // Invalid type error message
            cy.request({
                method: 'POST',
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/upload`,
                body: {
                    'title': 'Testing Json',
                    'instructions_url': '',
                    'id': 'hw-invalid',
                    'type': 'Invalid File',
                    'vcs': {
                        'repository_type': 'submitty-hosted',
                        'vcs_path': 'path/to/vcs',
                        'vcs_subdirectory': 'subdirectory',
                    },
                    'bulk_upload': false,
                    'team_gradeable': {
                        'team_size_max': 3,
                        'gradeable_teams_read': false,
                    },
                    'grading_inquiry': {
                        'grade_inquiry_per_component_allowed': false,
                    },
                    'ta_grading': false,
                    'syllabus_bucket': 'Homework',
                },
                headers: {
                    Authorization: key,
                },
            }).then((response) => {
                expect(response.body.message).to.eql('Invalid type');
            });

            // No ID, Type, or Title
            cy.request({
                method: 'POST',
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/upload`,
                body: {
                    'instructions_url': '',
                    'vcs': {
                        'repository_type': 'submitty-hosted',
                        'vcs_path': 'path/to/vcs',
                        'vcs_subdirectory': 'subdirectory',
                    },
                    'bulk_upload': true,
                    'team_gradeable': {
                        'team_size_max': 3,
                        'inherit_from': 'gradeable_id',
                        'gradeable_teams_read': false,
                    },
                    'grading_inquiry': {
                        'grade_inquiry_per_component_allowed': false,
                    },
                    'ta_grading': false,
                    'syllabus_bucket': 'Homework',
                },
                headers: {
                    Authorization: key,
                },
            }).then((response) => {
                expect(response.body.message).to.eql('JSON requires id, title, and type. See documentation for information');
            });

        });
    });
});
