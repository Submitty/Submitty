describe('TA Grading details page', () => {
    it('ta grading ui testing', () => {
        cy.login();
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);
        cy.get('[data-testid="view-sections"]').should('contain', 'View All');
        cy.get('[data-testid="view-sections"]').click();
        cy.get('[data-testid="view-sections"]').should('contain', 'View Your Sections');
        cy.get('[data-testid="random-default-order"]').should('contain', 'Switch to Random Order');
        cy.get('[data-testid="random-default-order"]').click();
        cy.get('[data-testid="random-default-order"]').should('contain', 'Switch to Default Order');
        cy.get('[data-testid="toggle-anon-button"]').should('contain', 'Enable Anonymous Mode');
        cy.get('[data-testid="toggle-anon-button"]').click();
        cy.get('[data-testid="toggle-anon-button"]').should('contain', 'Disable Anonymous Mode');
        cy.get('[data-testid="toggle-grade-inquiry"]').should('contain', 'Grade Inquiry Only: Off');
        cy.get('[data-testid="toggle-grade-inquiry"]').click();
        cy.get('[data-testid="toggle-grade-inquiry"]').should('contain', 'Grade Inquiry Only: On');
        cy.get('[data-testid="toggle-grade-inquiry"]').click();
        cy.get('[data-testid="stats-and-charts"]').should('contain', 'Statistics & Charts');
        cy.get('[data-testid="grade-button"]').should('be.visible');
        cy.get('[data-testid="collapse-all-sections"]').should('contain', 'Collapse All Sections');
        cy.get('[data-testid="collapse-all-sections"]').click();
        cy.get('[data-testid="grade-button"]').should('be.not.visible');
        cy.get('[data-testid="expand-all-sections"]').should('contain', 'Expand All Sections');
        cy.get('[data-testid="expand-all-sections"]').click();
        cy.get('[data-testid="grade-button"]').should('be.visible');
        // randomly re-assign will be present only for rotating section
        cy.visit(['sample', 'gradeable', 'grading_homework_team_pdf', 'grading', 'details']);
        cy.get('[data-testid="randomly-reassign-teams"]').should('contain', 'Randomly Re-Assign Teams to Rotating Sections');
        cy.get('[data-testid="export-team-members"]').should('contain', 'Export Teams Members');
        cy.visit(['sample', 'gradeable', 'grading_team_homework', 'grading', 'details']);
        cy.get('[data-testid="export-team-members"]').should('contain', 'Export Teams Members');
    });
});
