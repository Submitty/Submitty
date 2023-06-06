import {getCurrentSemester} from '../support/utils.js';

function switch_settings(setting, pdf_type) {
    cy.login('instructor');
    cy.visit(`/courses/${getCurrentSemester()}/sample`);
    cy.get(`a[href*="/sample/gradeable/${pdf_type}/update"]`).click();
    cy.get('#page_3_nav').click();
    cy.get('#minimum_grading_group').find('option').contains(setting)
        .then(($option) => {
            cy.get('#minimum_grading_group').select($option.val());
        });
    cy.get('#minimum_grading_group').find('option:selected').should('have.text', setting);
    cy.get('input[name="grader_assignment_method"][value="1"]').check();
    cy.logout();
}

function pdf_access(user_id, tr_number, td_number, gradeable_id, pdf_name) {
    cy.login(user_id);
    cy.visit(`/courses/${getCurrentSemester()}/sample`);
    cy.get(`a[href*="/sample/gradeable/${gradeable_id}/grading/details"]`).click();
    cy.get('#agree-button').click({ force: true });
    if (user_id !== 'student') {
        cy.get(`a[href*='/sample/gradeable/${gradeable_id}/grading/status']`).click();
        cy.contains('Grading Index').should('be.visible').click();
    }
    cy.get('#details-table').should('be.visible');
    if (user_id !== 'student') {
        cy.get('.markers-container .btn-default').each(($el) => {
            if ($el.text().trim() === 'View All') {
                cy.wrap($el).click();
            }
        });
    }
    cy.get(`#details-table > tbody.details-content.panel-content-active > tr:nth-child(${tr_number}) > td:nth-child(${td_number}) > a`).eq(0).click();
    cy.wait(500); //wait for #submission_browser to get loaded
    cy.get('#submission_browser_btn').then(($el) => {
        if (!$el.hasClass( 'active' )) {
            cy.log($el);
            cy.get($el).find('button').click();
        }
    });
    cy.get('#submissions').click();
    cy.get('#div_viewer_sd1').find(`a[file-url*='${pdf_name}']`).click();
    cy.get('#pageContainer1').should('be.visible');
    cy.get('a[onclick*="collapseFile"]').click();
}

describe('Test cases for PDFs access', () => {
    let pdf_type;

    beforeEach(() => {
        cy.visit('/');
    });

    it('users should have access to basic pdfs', () => {
        pdf_type = 'grading_homework_pdf';
        switch_settings('Limited Access Grader', pdf_type);

        pdf_access('instructor', '3', '8' ,pdf_type, 'words_1463.pdf');
        cy.logout();
        pdf_access('ta', '3', '8', pdf_type, 'words_1463.pdf');
        cy.logout();
        pdf_access('grader', '4', '8', pdf_type, 'words_881.pdf');
    });

    it('users should have access to team pdfs', () => {
        pdf_type = 'grading_homework_team_pdf';
        switch_settings('Limited Access Grader', pdf_type);

        pdf_access('instructor', '3', '9', pdf_type, 'words_1463.pdf');
        cy.logout();
        pdf_access('ta', '3', '9', pdf_type, 'words_1463.pdf');
        cy.logout();
        pdf_access('grader','1', '7', pdf_type, 'words_881.pdf');
    });

    it('users should have access to peer pdfs', () => {
        pdf_type = 'grading_pdf_peer_homework';
        switch_settings('Limited Access Grader', pdf_type);

        pdf_access('instructor', '3', '8', pdf_type, 'words_249.pdf');
        cy.logout();
        pdf_access('ta', '3', '8', pdf_type, 'words_249.pdf');
        cy.logout();
        pdf_access('grader','2', '8', pdf_type, 'words_249.pdf');
        cy.logout();
        pdf_access('student','9', '5', pdf_type, 'words_881.pdf');
    });

    it('users should have access to peer team pdfs', () => {
        pdf_type = 'grading_pdf_peer_team_homework';
        switch_settings('Limited Access Grader', pdf_type);

        pdf_access('instructor', '2', '9', pdf_type, 'words_1463.pdf');
        cy.logout();
        pdf_access('ta', '2', '9', pdf_type, 'words_1463.pdf');
        cy.logout();
        pdf_access('grader', '4', '7', pdf_type, 'words_1463.pdf');
    });

});
