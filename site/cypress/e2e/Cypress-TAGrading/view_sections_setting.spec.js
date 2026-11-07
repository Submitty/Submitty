describe('Only Assigned Sections setting', () => {
    const details = (gradeableId) => ['sample', 'gradeable', gradeableId, 'grading', 'details'];

    it('Is remembered across gradeables and is not changed by the calendar view', () => {
        cy.login('instructor');
        cy.visit(details('grading_homework'));
        cy.get('[data-testid="view-sections"]').should('be.checked').click();
        cy.get('[data-testid="view-sections"]').should('not.be.checked');
        cy.get('[data-testid="grade-button"]').should('have.length.greaterThan', 0);
        // saved at the site root so the choice also applies in other courses
        cy.getCookie('grading_view_sections').should('include', { value: 'all', path: '/' });

        cy.visit(details('grading_pdf_peer_homework'));
        cy.get('[data-testid="view-sections"]').should('not.be.checked');

        // the calendar view used to share the same cookie and flip this setting
        cy.visit('calendar');
        cy.get('#calendar-item-type-edit').select('two_week');
        cy.getCookie('calendar_view').should('have.property', 'value', 'two_week');
        cy.visit(details('grading_homework'));
        cy.get('[data-testid="view-sections"]').should('not.be.checked');
        cy.get('[data-testid="grade-button"]').should('have.length.greaterThan', 0);
    });
});
