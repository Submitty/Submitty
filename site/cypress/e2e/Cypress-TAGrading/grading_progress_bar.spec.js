describe('TA grading progress bar', () => {
    it('only counts the grader\'s sections, unless the NULL section filter is on', () => {
        // ta3 grades registration sections 1 and 3. Grading Homework PDF also has
        // submissions from students with no section, who used to be counted too (50%)
        cy.login('ta3');
        cy.visit(['sample', 'gradeable', 'grading_homework_pdf', 'grading', 'details']);
        cy.get('[data-testid="grade-button"]').first().invoke('attr', 'href').then((href) => cy.visit(href));
        cy.get('[data-testid="progress-bar"]').should('contain', '45%');

        cy.setCookie('include_null_section', 'include');
        cy.reload();
        cy.get('[data-testid="progress-bar"]').should('contain', '50%');
        cy.clearCookie('include_null_section');
    });
});
