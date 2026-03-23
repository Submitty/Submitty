// Cypress test for Manage Teams page repository message logic
// Ensures the repository error message only appears for VCS gradeables

describe('Manage Teams Page - Repository Message', () => {
    // Utility: Login as a student and visit the manage teams page for a gradeable
    function visitManageTeams(gradeableId) {
        cy.login('student');
        cy.visit(`/course/gradeable/${gradeableId}/team`);
    }

    it('should show repo error for VCS gradeable', () => {
        // Replace 'vcs_team_gradeable' with a real VCS team gradeable id in your test data
        visitManageTeams('vcs_team_gradeable');
        cy.contains('Your repository does not exist.').should('exist');
        cy.contains('Contact your instructor or sysadmin for assistance in creating your repository for this assignment.').should('exist');
    });

    it('should NOT show repo error for direct upload team gradeable', () => {
        // Replace 'upload_team_gradeable' with a real direct-upload team gradeable id in your test data
        visitManageTeams('upload_team_gradeable');
        cy.contains('Your repository does not exist.').should('not.exist');
        cy.contains('Contact your instructor or sysadmin for assistance in creating your repository for this assignment.').should('not.exist');
    });
});
