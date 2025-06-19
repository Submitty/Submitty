const gradeableId = 'grades_released_homework';
const gradeInquiryDeadlineDate = '9998-01-01 00:00:00';
const beforeGradeInquiryStartDate = '1970-01-01 00:00:00';
const originalGradeInquiryDate = '1974-01-08 23:59:59';

describe('Test cases revolving around grade inquiries', () => {
    const setGradeInquiriesForGradeable = (gradeableId, date = null) => {
        cy.visit(['sample', 'gradeable', gradeableId, 'update']);
        cy.get('[data-testid="yes-grade-inquiry-allowed"]').click();
        cy.get('[data-testid="yes-component"]').click();
        cy.contains('Dates').click();
        cy.get('[data-testid="grade-inquiry-due-date"]').click();
        cy.get('[data-testid="grade-inquiry-due-date"]').should('be.visible');
        cy.get('[data-testid="grade-inquiry-due-date"]').clear();
        cy.get('[data-testid="grade-inquiry-due-date"]').type(date);
        cy.get('[data-testid="grade-inquiry-due-date"]').type('{enter}');
        cy.get('[data-testid="save-status"]', { timeout: 10000 }).should('have.text', 'All Changes Saved');
    };

    it('should test normal submission grade inquiry panel', () => {
        // grade inquiry ended
        cy.login();
        cy.visit(['sample', 'gradeable', gradeableId, 'grading', 'details']);
        cy.get('[data-testid="view-sections"]').click();
        cy.get('[data-testid="grade-button"]').eq(4).click();
        cy.get('[data-testid="grade-inquiry-info-btn"]').click();
        cy.get('[data-testid="grading-label"]').should('contain', 'Grade Inquiry');
        cy.get('[data-testid="grade-inquiry-actions"]').should('not.exist');

        // grade inquiry in progress
        setGradeInquiriesForGradeable(gradeableId, gradeInquiryDeadlineDate);
        cy.get('[data-testid="grade-inquiry-dates-warning"]').should('not.be.visible');
        cy.visit(['sample', 'gradeable', gradeableId, 'grading', 'details']);
        cy.get('[data-testid="grade-button"]').eq(2).click();
        cy.get('[data-testid="grading-label"]').should('contain', 'Grade Inquiry');
        cy.get('[data-testid="grade-inquiry-actions"]').contains('Submit Grade Inquiry').should('be.disabled');
        cy.get('[data-testid="component-tab-36"]').click();
        cy.get('[data-testid="reply-text-area-36"]').click();
        cy.get('[data-testid="reply-text-area-36"]').type('Submitty');
        cy.get('[data-testid="markdown-mode-tab-preview"]').first().should('exist');
        cy.get('[data-testid="grade-inquiry-actions"]').contains('Submit Grade Inquiry').should('not.be.disabled');
        cy.reload();
        cy.get('[data-testid^="reply-text-area-"]').first().should('have.value', 'Submitty');
    });

    it('Should test if users can see grade inquiry panel/containers', () => {
        ['ta', 'grader'].forEach((user) => {
            cy.login(user);
            cy.visit(['sample', 'gradeable', gradeableId, 'grading', 'details']);
            cy.get('[data-testid="popup-window"]').should('exist');
            cy.get('[data-testid="close-button"]').should('exist');
            cy.get('[data-testid="close-hidden-button"]').should('exist');
            cy.get('[data-testid="agree-popup-btn"]').click();
            if (user === 'ta') {
                cy.get('[data-testid="view-sections"]').click();
            }
            cy.get('[data-testid="grade-button"]').eq(4).click();
            cy.get('[data-testid="grade-inquiry-info-btn"]').click();
            cy.get('[data-testid="grading-label"]').should('contain', 'Grade Inquiry');
            cy.get('[data-testid="grade-inquiry-actions"]').contains('Submit Grade Inquiry').should('be.disabled');
            cy.get('[data-testid="reply-text-area-36"]').click();
            cy.get('[data-testid="reply-text-area-36"]').type('Submitty');
            cy.get('[data-testid="markdown-mode-tab-preview"]').first().should('exist');
            cy.get('[data-testid="grade-inquiry-actions"]').contains('Submit Grade Inquiry').should('not.be.disabled');

            // need to clear local storage to refresh grader's responsibility page
            cy.clearLocalStorage();
        });

        // student view
        cy.login('beahaf');
        cy.visit(['sample', 'gradeable', gradeableId]);
        cy.get('[data-testid="grade-inquiry-container"]').should('contain.text', 'Grade inquiries are due by 9998-01-01 @ 12:00 AM EST');
    });
    it('should test cases regarding abnormal grade inquiry dates', () => {
        cy.login();
        setGradeInquiriesForGradeable(gradeableId, beforeGradeInquiryStartDate);
        cy.get('[data-testid="grade-inquiry-dates-warning"]').should('be.visible');
        cy.get('[data-testid="grade-inquiry-dates-warning"]').should('have.text', 'Warning: Grade Inquiry ends before it starts. Students will not be able to make Grade Inquires.');

        // TA / Instructor view
        cy.visit(['sample', 'gradeable', gradeableId, 'grading', 'details']);
        cy.get('[data-testid="grade-button"]').eq(2).click();
        cy.get('[data-testid="grade-inquiry-info-btn"]').click();
        cy.get('[data-testid="invalid-grade-inquiry"]').should('exist');
        cy.get('[data-testid="invalid-grade-inquiry"]').should('have.text', 'Grade inquiries will not start. Contact the instructor if this is unexpected.');
        cy.get('[data-testid="component-tab-36"]').should('not.exist');

        // student view
        cy.login('beahaf');
        cy.visit(['sample', 'gradeable', gradeableId]);
        cy.get('[data-testid="grade-inquiry-container"]').should('contain', 'There will be no grade inquiries. Contact the instructor if this is a mistake.');
    });

    after(() => {
        cy.login();
        setGradeInquiriesForGradeable(gradeableId, originalGradeInquiryDate);
    });
});
