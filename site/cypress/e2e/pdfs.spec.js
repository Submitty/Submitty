import {getCurrentSemester} from '../support/utils.js';

function switch_settings(setting) {
    cy.login("instructor");
    cy.visit(`/courses/${getCurrentSemester()}/sample`);
    cy.get('a[href*="/sample/gradeable/grading_homework_pdf/update"]').click();
    cy.get("#page_3_nav").click();
    cy.get("#minimum_grading_group").select(setting);
    cy.get("#minimum_grading_group").find('option:selected').should('have.text', setting)
    cy.logout();
}

function pdf_access(user_id, tr_number, td_number, gradeable_id, pdf_name, version) {
    cy.login(user_id)
    cy.visit(`/courses/${getCurrentSemester()}/sample`);
    cy.get(`a[href*="/sample/gradeable/${gradeable_id}/grading/details"]`).click();
    cy.get("#agree-button").click({ force: true });
    if (user_id !== "student") {
        cy.get(`a[href*="/sample/gradeable/${gradeable_id}/grading/status"]`).click();
        cy.contains("Grading Index").should("be.visible").click();
    }
    cy.get("#details-table").should("be.visible");
    if (user_id !== "student") {
        cy.get(".markers-container .btn-default").each(($el) => {
            if ($el.text().trim() == "View All") {
                cy.wrap($el).click();
            }
        });
    }
    cy.get(`#details-table > tbody.details-content.panel-content-active > tr:nth-child(${tr_number}) > td:nth-child(${td_number}) > a`).eq(0).click();
    cy.wait(500); //wait for #submission_browser to get loaded
    cy.get("#submission_browser_btn").then(($el) => {
        if (!$el.hasClass( "active" )) {
            cy.log($el);
            cy.get($el).find('button').click();
        }
    });
    // cy.get("#submission_browser").should("be.visible");
    cy.get("#submissions").click();
    cy.get("#div_viewer_sd1").find(`a[file-url*="${pdf_name}"]`).click();
    cy.get("#pageContainer1").should("be.visible");
    cy.get('a[onclick*="collapseFile"]').click();
}

describe("Test cases for PDFs access", () => {
    beforeEach(() => {
        cy.visit("/").then(()=>{
            // switch_settings("Limited Access Grader");
        })
    });
    
    it("users should have access to basic pdfs", () => {
        pdf_access("instructor", "3", "8" ,"grading_homework_pdf", "words_1463.pdf", "2");
        cy.logout();
        pdf_access("ta", "3", "8", "grading_homework_pdf", "words_1463.pdf", "2");
        cy.logout();
        pdf_access("grader", "4", "8", "grading_homework_pdf", "words_881.pdf", "1");
    });

    // it("users should have access to team pdfs", () => {
    //     pdf_access("instructor", "3", "9", "grading_homework_team_pdf", "words_881.pdf", "1")
    //     cy.logout();

    //     pdf_access("ta", "3", "9", "grading_homework_team_pdf", "words_881.pdf", "1")
    //     cy.logout();

    //     pdf_access("grader","1", "6", "grading_homework_team_pdf", "words_881.pdf", "1")
    // });

    // it("users should have access to peer pdfs", () => {
    //     pdf_access("instructor", "3", "8", "grading_pdf_peer_homework", "words_1463.pdf", "1")
    //     pdf_access("ta", "3", "8", "grading_pdf_peer_homework", "words_1463.pdf", "1")
    //     pdf_access("grader","2", "8", "grading_pdf_peer_homework", "words_249.pdf", "1")
    //     pdf_access("student","9", "5", "grading_pdf_peer_homework", "words_881.pdf", "1")
    // });

    // it("users should have access to peer team pdfs", () => {
    //     pdf_access("instructor", "2", "8", "grading_pdf_peer_team_homework", "words_1463.pdf", "1")
    //     pdf_access("ta", "2", "6", "grading_pdf_peer_team_homework", "words_1463.pdf", "1")
    //     pdf_access("grader", "2", "6", "grading_pdf_peer_team_homework", "words_1463.pdf", "1")
    //     // pdf_access("bauchg", "1", "5", "grading_pdf_peer_team_homework", "words_881.pdf", "1")
    // });

});
