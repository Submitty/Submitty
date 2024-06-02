describe('Test cases revolving around grade inquires', () => {
    const setGradeInquiriesForGradeable = (gradeableId, date = null, allowed = true) => {
        cy.visit(['sample', 'gradeable', gradeableId, 'update']);

        if (allowed) {
            cy.get('[data-testid="yes-grade-inquiry-allowed"]').click();
        }
        else {
            cy.get('[data-testid="no-grade-inquiry-allowed"]').click();
        }

        if (date) {
            cy.contains('Dates').click();
            cy.get('[data-testid="date-grade-inquiry-due"]').click();

            cy.get('[data-testid="date-grade-inquiry-due"]').should('be.visible');

            cy.get('[data-testid="date-grade-inquiry-due"]').clear().type(date, { parseSpecialCharSequences: false, force: true });
        }
    };

    it('should test normal submission grade inquiry panel', () => {
        cy.visit(['sample']);
        cy.login();
        const gradeableId = 'grades_released_homework';
        const gradeInquiryDeadlineDate = '9998-01-01 00:00:00';
        setGradeInquiriesForGradeable(gradeableId, gradeInquiryDeadlineDate, true);
        cy.visit(['sample', 'gradeable', gradeableId, 'grading', 'details']);
        cy.get('[data-testid="view-sections"]').click();
        cy.get('[data-testid="grade-button"]').first().click();
        cy.get('[data-testid="grade-inquiry-info-btn"]').click();
        cy.get('[data-testid="grading-label"]').should('contain', 'Grade Inquiry');
        cy.get('[data-testid="grade-inquiry-submit-button"]').should('contain', 'Submit Grade Inquiry').and('be.disabled');
        cy.get('[data-testid="reply-text-area-0"]').click().type('Submitty');
        cy.get('[data-testid="markdown-mode-tab-preview"]').first().should('exist');
        cy.get('[data-testid="grade-inquiry-submit-button"]').should('contain', 'Submit Grade Inquiry').and('not.be.disabled');
    });
    ['grader', 'ta'].forEach((user) => {
        it(`${user} can see grade inquiry panel`, () => {
            cy.logout();
            cy.visit(['sample']);
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
            cy.get('[data-testid="grade-button"]').first().click();
            cy.get('[data-testid="grade-inquiry-info-btn"]').click();
            cy.get('[data-testid="grading-label"]').should('contain', 'Grade Inquiry');
            cy.get('[data-testid="grade-inquiry-submit-button"]').should('contain', 'Submit Grade Inquiry').and('be.disabled');
            cy.get('[data-testid="reply-text-area-0"]').click().type('Submitty');
            cy.get('[data-testid="markdown-mode-tab-preview"]').first().should('exist');
            cy.get('[data-testid="grade-inquiry-submit-button"]').should('contain', 'Submit Grade Inquiry').and('not.be.disabled');
        });
    });
});
