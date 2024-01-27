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

    // it('Should upload file, submit, view gradeable', () => {
    //     cy.login('instructor');

    //     const testfile1 = 'cypress/fixtures/json1.json';

    //     cy.visit(['sample', 'gradeable']);

    //     //Makes sure the clear button is not disabled by adding a file
    //     cy.get('[data-testid='upload-gradeable-btn']').click();
    //     cy.get('[data-testid='popup-window']').should('be.visible');
    //     cy.get('[data-testid='popup-window']').should('contain.text', 'Upload JSON for Gradeable');
    //     cy.get('[data-testid='upload']').selectFile(testfile1, {action: 'drag-drop'});
    //     cy.get('[data-testid='submit']').click();
    //     cy.get('[data-testid='upload-gradeable-btn']', { timeout: 10000 }).should('not.exist');
    //     cy.get('body').should('contain.text', 'Edit Gradeable');
    // });

    it('Should get error JSON responses', () => {
        getKey('instructor', 'instructor').then((key) => {
            // Gradeable already exists
            // cy.request({
            //     method: 'POST',
            //     url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/upload`,
            //     body: {
            //         type: 'Checkpoints',
            //         id: 'TestingJson',
            //         title: 'Testing Json',
            //         'instructions_url': '',
            //         'bulk_upload': false,
            //         'vcs': false,
            //         'ta_grading': false,
            //         'grade_inquiry_allowed': false,
            //         'grade_inquiry_per_component_allowed': false,
            //         'discussion_based': false,
            //         'discussion_thread_id': '',
            //         'team_assignment': false,
            //         'team_size_max': 3,
            //         'eg_inherit_teams_from': '',
            //         'gradeable_teams_read': false,
            //         'vcs_radio_buttons': '',
            //         'external_repo': '',
            //         'using_subdirectory': false,
            //         'vcs_subdirectory': '',
            //         'syllabus_bucket': ''
            //     },
            //     headers: {
            //         Authorization: key,
            //     },
            // }).then((response) => {
            //     expect(response.body.message).to.eql('Gradeable already exists');
            // });
            
            // // Invalid type error message
            // cy.request({
            //     method: 'POST',
            //     url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/upload`,
            //     body: {
            //         'type': 'Invalid Type',
            //         'id': 'InvalidType',
            //         'title': 'Invalid Type',
            //         'instructions_url': '',
            //         'bulk_upload': false,
            //         'vcs': false,
            //         'ta_grading': false,
            //         'grade_inquiry_allowed': false,
            //         'grade_inquiry_per_component_allowed': false,
            //         'discussion_based': false,
            //         'discussion_thread_id': '',
            //         'team_assignment': false,
            //         'team_size_max': 3,
            //         'eg_inherit_teams_from': '',
            //         'gradeable_teams_read': false,
            //         'vcs_radio_buttons': '',
            //         'external_repo': '',
            //         'using_subdirectory': false,
            //         'vcs_subdirectory': '',
            //         'syllabus_bucket': ''
            //     },
            //     headers: {
            //         Authorization: key,
            //     },
            // }).then((response) => {
            //     expect(response.body.message).to.eql('Invalid type');
            // });

            // Electronic File parameter requirements
            cy.request({
                method: 'POST',
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/upload`,
                body: {
                    'type': 'Checkpoints',
                    'id': 'AllValuesRequired',
                    'title': 'Invalid Values',
                },
                headers: {
                    Authorization: key,
                },
            }).then((response) => {
                expect(response.body.message).to.eql('All values are required. See documentation for template.');
            });

            // No ID, Type, or Title
            cy.request({
                method: 'POST',
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/upload`,
                body: {
                    'type': '',
                    'id': '',
                    'title': '',
                    'instructions_url': '',
                    'bulk_upload': false,
                    'vcs': false,
                    'ta_grading': false,
                    'grade_inquiry_allowed': false,
                    'grade_inquiry_per_component_allowed': false,
                    'discussion_based': false,
                    'discussion_thread_id': '',
                    'team_assignment': false,
                    'team_size_max': 3,
                    'eg_inherit_teams_from': '',
                    'gradeable_teams_read': false,
                    'vcs_radio_buttons': '',
                    'external_repo': '',
                    'using_subdirectory': false,
                    'vcs_subdirectory': '',
                    'syllabus_bucket': ''
                },
                headers: {
                    Authorization: key,
                },
            }).then((response) => {
                expect(response.body.message).to.eql('JSON requires id, title, and type');
            });
            // API works correctly
            cy.request({
                method: 'POST',
                url: `${Cypress.config('baseUrl')}/api/${getCurrentSemester()}/sample/upload`,
                body: {
                    'type': 'Checkpoints',
                    'id': 'TestingJsonApi',
                    'title': 'Testing Json API',
                    'instructions_url': '',
                    'bulk_upload': false,
                    'vcs': false,
                    'ta_grading': false,
                    'grade_inquiry_allowed': false,
                    'grade_inquiry_per_component_allowed': false,
                    'discussion_based': false,
                    'discussion_thread_id': '',
                    'team_assignment': false,
                    'team_size_max': 3,
                    'eg_inherit_teams_from': '',
                    'gradeable_teams_read': false,
                    'vcs_radio_buttons': '',
                    'external_repo': '',
                    'using_subdirectory': false,
                    'vcs_subdirectory': '',
                    'syllabus_bucket': ''
                },
                headers: {
                    Authorization: key,
                },
            }).then((response) => {
                expect(response.body.data).to.eql('TestingJsonApi');
            });
        });
    });
});
