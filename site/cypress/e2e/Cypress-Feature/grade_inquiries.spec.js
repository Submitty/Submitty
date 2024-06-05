describe('Test cases revolving around grade inquires', () => {
    const setGradeInquiriesForGradeable = (gradeableId, date = null) => {
        cy.visit(['sample', 'gradeable', gradeableId, 'update']);
        cy.get('[data-testid="yes-grade-inquiry-allowed"]').click();
        cy.contains('Dates').click();
        cy.get('[data-testid="date-grade-inquiry-due"]').click();
        cy.get('[data-testid="date-grade-inquiry-due"]').should('be.visible');
        cy.get('[data-testid="date-grade-inquiry-due"]').clear().type(date, { parseSpecialCharSequences: false, force: true });
    };

    it('should test normal submission grade inquiry panel', () => {
        cy.login();
        const gradeableId = 'grades_released_homework';
        const gradeInquiryDeadlineDate = '9998-01-01 00:00:00';
        setGradeInquiriesForGradeable(gradeableId, gradeInquiryDeadlineDate);
        cy.visit(['sample', 'gradeable', gradeableId, 'grading', 'details']);
        cy.get('[data-testid="view-sections"]').click();
        cy.get('[data-testid="grade-button"]').eq(3).click();
        cy.get('[data-testid="grade-inquiry-info-btn"]').click();
        cy.get('[data-testid="grading-label"]').should('contain', 'Grade Inquiry');
        cy.get('[data-testid="grade-inquiry-actions"]').contains('Submit Grade Inquiry').and('be.disabled');
        cy.get('[data-testid="reply-text-area-0"]').click().type('Submitty');
        cy.get('[data-testid="markdown-mode-tab-preview"]').first().should('exist');
        cy.get('[data-testid="grade-inquiry-actions"]').contains('Submit Grade Inquiry').and('not.be.disabled');
    });
    ['ta', 'grader'].forEach((user) => {
        it(`${user} can see grade inquiry panel`, () => {
            cy.login(user);
            const gradeableId = 'grades_released_homework';
            cy.visit(['sample', 'gradeable', gradeableId, 'grading', 'details']);
            cy.get('[data-testid="popup-window"]').should('exist');
            cy.get('[data-testid="close-button"]').should('exist');
            cy.get('[data-testid="close-hidden-button"]').should('exist');
            cy.get('[data-testid="agree-popup-btn"]').click();
            if ( user === 'ta') {
                cy.get('[data-testid="view-sections"]').click();
            }
            cy.get('[data-testid="grade-button"]').eq(3).click();
            cy.get('[data-testid="grade-inquiry-info-btn"]').click();
            cy.get('[data-testid="grading-label"]').should('contain', 'Grade Inquiry');
            cy.get('[data-testid="grade-inquiry-actions"]').contains('Submit Grade Inquiry').and('be.disabled');
            cy.get('[data-testid="reply-text-area-0"]').click().type('Submitty');
            cy.get('[data-testid="markdown-mode-tab-preview"]').first().should('exist');
            cy.get('[data-testid="grade-inquiry-actions"]').contains('Submit Grade Inquiry').and('not.be.disabled');
        });
    });
});
