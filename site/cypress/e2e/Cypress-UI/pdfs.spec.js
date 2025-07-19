function switch_settings(gradeable_id) {
    cy.visit(['sample', 'gradeable', gradeable_id, 'update']);
    cy.get('#page_3_nav').click();
    cy.get('input[data-testname="grader_assignment_method"][value="1"]').check();
}

function revert_settings(gradeable_id, setting) {
    cy.visit(['sample', 'gradeable', gradeable_id, 'update']);
    cy.get('#page_3_nav').click();
    cy.get(`input[data-testname="grader_assignment_method"][id=${setting}`).check();
}

function select_gradeable() {
    // This gets a gradeable that has been graded already, so there are submissions available.
    const button_labels = ['/ 12', 'Grade', 'Incomplete'];
    const button_labels_regex = new RegExp(button_labels.join('|'));
    cy.get('[data-testid="grade-table"]').contains(button_labels_regex).click({ force: true });
    cy.get('[data-testid="show-autograding"]').click();
    cy.get('[data-testid="show-submission"]').click();
    cy.get('[data-testid="folders"]').contains('submissions').click();
    cy.get('#div_viewer_sd1').contains('words_').click();
    cy.get('#pageContainer1').should('be.visible');
}

function check_pdf_access(gradeable_id) {
    cy.visit(['sample', 'gradeable', gradeable_id, 'grading', 'details']);
    cy.get('#agree-button').click({ force: true });
    cy.get('[data-testid="details-table"]').should('be.visible');
    cy.get('[data-testid="view-sections"]').then(($button) => {
        if ($button[0].checked) {
            $button.click();
        }
    });
    select_gradeable();
}

function minimum_pdf_access(gradeable_id) {
    cy.visit(['sample', 'gradeable', gradeable_id, 'grading', 'details']);
    cy.get('#agree-button').click({ force: true });
    cy.get('[data-testid="details-table"]').should('be.visible');
    cy.get('[data-testid="view-sections-label"]').should('not.exist');
    select_gradeable();
}

function no_pdf_access(gradeable_id) {
    cy.visit(['sample', 'gradeable', gradeable_id, 'grading', 'details']);
    cy.get('[data-testid="popup-message"]').should('be.visible').and('contain.text', 'You do not have permission');
}

const gradeable_type = [
    'grading_homework_team_pdf',
    'grading_homework_pdf',
    'grading_pdf_peer_homework',
    'grading_pdf_peer_team_homework',
];

describe('Test cases for PDFs access', () => {
    before(() => {
        cy.login('instructor');
        // Change the settings that aren't already what we need
        switch_settings('grading_homework_team_pdf');
        switch_settings('grading_pdf_peer_homework');
        switch_settings('grading_pdf_peer_team_homework');
        cy.logout();
    });

    after(() => {
        cy.login('instructor');
        revert_settings('grading_homework_team_pdf', 'rotating_section');
        revert_settings('grading_pdf_peer_homework', 'all_access');
        revert_settings('grading_pdf_peer_team_homework', 'all_access');
        cy.logout();
    });

    it('ta should have access to pdfs', () => {
        cy.login('ta');
        gradeable_type.forEach((gradeable_id) => {
            check_pdf_access(gradeable_id);
        });
    });

    it('instructor should have access to pdfs', () => {
        cy.login('instructor');
        gradeable_type.forEach((gradeable_id) => {
            check_pdf_access(gradeable_id);
        });
    });

    it('student should have access to some pdfs', () => {
        cy.login('student');
        minimum_pdf_access('grading_pdf_peer_homework');
        minimum_pdf_access('grading_pdf_peer_team_homework');
        no_pdf_access('grading_homework_team_pdf');
        no_pdf_access('grading_homework_pdf');
    });

    it('grader should have access to some pdfs', () => {
        cy.login('grader');
        gradeable_type.forEach((gradeable_id) => {
            minimum_pdf_access(gradeable_id);
        });
    });
});
