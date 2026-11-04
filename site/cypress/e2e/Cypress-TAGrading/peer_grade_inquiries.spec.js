describe('Peer graders should not see grade inquiry indicators', () => {
    const gradeableId = 'grading_pdf_peer_homework';
    const peerGraderId = 'baliss';

    const setGradeInquiriesForGradeable = (date) => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', gradeableId, 'update']);

        cy.get('[data-testid="yes-grade-inquiry-allowed"]').click();
        cy.get('[data-testid="yes-component"]').click();

        cy.contains('Dates').click();
        cy.get('[data-testid="grade-inquiry-due-date"]').click();
        cy.get('[data-testid="grade-inquiry-due-date"]').clear();
        cy.get('[data-testid="grade-inquiry-due-date"]').type(date);
        cy.get('[data-testid="grade-inquiry-due-date"]').type('{enter}');

        cy.get('[data-testid="save-status"]', { timeout: 10000 })
            .should('have.text', 'All Changes Saved');
    };

    it('should hide grade inquiry indicators from peer graders', () => {
        setGradeInquiriesForGradeable('9998-01-01 00:00:00');

        cy.login(peerGraderId);

        cy.visit([
            'sample',
            'gradeable',
            gradeableId,
            'grading',
            'details',
        ]);

        cy.get('[data-testid="popup-save-button"]').click();

        cy.get('[data-testid="grade-button"]')
            .its('length')
            .should('be.greaterThan', 0);

        cy.get('[data-testid="grade-button"]').each(($button) => {
            cy.wrap($button)
                .should('not.contain', 'Grade Inquiry');

            cy.wrap($button)
                .should('not.have.attr', 'data-grade-inquiry');

            cy.wrap($button)
                .find('.notification-badge')
                .should('not.exist');
        });

        cy.screenshot('peer-grader-no-grade-inquiry');

        cy.visit('/');
        cy.logout();
    });

    after(() => {
        setGradeInquiriesForGradeable('1974-01-13 23:59:59');

        cy.visit('/');
        cy.logout();
    });
});