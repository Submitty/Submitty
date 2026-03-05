describe('Test cases for Overridden Banner', () => {
    const gradeable_name = 'Grading Homework';
    const score_to_set = '10';
    const student_who_id = 'hG1b13ljpDjKu32';

    before(() => {
        // Clean up any leftover overrides
        cy.login('instructor');
        cy.visit(['sample', 'grade_override']);
        cy.get('[data-testid="grade-override-select-gradeable"]').select(gradeable_name);
        cy.get('[data-testid="student-grade-override"]').clear().type('student{downarrow}{enter}');
        cy.get('body').then(($body) => {
            if ($body.find('[data-testid="grade-override-delete"]').length > 0) {
                cy.get('[data-testid="grade-override-delete"]').click();
            }
        });
    });

    it('Overridden banner should appear for grader and student, and disappear after deletion', () => {
        cy.login('instructor');

        // Step 1: Create override
        cy.visit(['sample', 'grade_override']);
        cy.get('[data-testid="grade-override-select-gradeable"]').select(gradeable_name);
        cy.get('[data-testid="student-grade-override"]').clear().type('student');
        cy.get('.ui-menu-item-wrapper', { timeout: 10000 }).should('be.visible');
        cy.get('[data-testid="student-grade-override"]').type('{downarrow}{enter}');
        cy.get('[data-testid="student-grade-override"]').type('{esc}');
        cy.get('body').click(0, 0);
        cy.get('[data-testid="grade-override-score"]').scrollIntoView().click({ force: true }).clear({ force: true }).type(String(score_to_set), { force: true });
        cy.get('[data-testid="grade-override-submit"]').click();
        cy.get('[data-testid="popup-message"]', { timeout: 10000 }).should('contain', 'Updated');

        // Step 2: Grader should see the banner
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', `grade?who_id=${student_who_id}&sort=id&direction=ASC`]);
        cy.get('[data-testid="grading-rubric-btn"]').click();
        cy.get('[data-testid="overridden-grades-banner"]', { timeout: 10000 }).should('be.visible').should('contain', 'Overridden Grades');

        // Step 3: Student should see the banner
        cy.logout();
        cy.login('student');
        cy.visit(['sample', 'gradeable', 'grading_homework']);
        cy.get('[data-testid="overridden-grades-student-banner"]', { timeout: 10000 }).should('be.visible');
        cy.logout();

        // Step 4: Delete override - banner should disappear for grader
        cy.login('instructor');
        cy.visit(['sample', 'grade_override']);
        cy.get('[data-testid="grade-override-select-gradeable"]').select(gradeable_name);
        cy.get('[data-testid="student-grade-override"]').clear().type('student{downarrow}{enter}');
        cy.get('[data-testid="grade-override-delete"]').click();
        cy.get('[data-testid="popup-message"]', { timeout: 10000 }).should('contain', 'deleted');

        // Step 5: Banner should no longer exist
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', `grade?who_id=${student_who_id}&sort=id&direction=ASC`]);
        cy.get('[data-testid="grading-rubric-btn"]').click();
        cy.get('[data-testid="overridden-grades-banner"]').should('not.exist');
    });
    it('Overridden banner should appear for grader when student has no submission', () => {
        cy.login('instructor');

        // Create override for student with no submission
        cy.visit(['sample', 'grade_override']);
        cy.get('[data-testid="grade-override-select-gradeable"]').select(gradeable_name);
        cy.get('[data-testid="student-grade-override"]').clear().type('aphacker');
        cy.get('.ui-menu-item-wrapper', { timeout: 10000 }).should('be.visible');
        cy.get('[data-testid="student-grade-override"]').type('{downarrow}{enter}');
        cy.get('[data-testid="student-grade-override"]').type('{esc}');
        cy.get('body').click(0, 0);
        cy.get('[data-testid="grade-override-score"]').scrollIntoView().click({ force: true }).clear({ force: true }).type(String(score_to_set), { force: true });
        cy.get('[data-testid="grade-override-submit"]').click();
        cy.get('[data-testid="popup-message"]', { timeout: 10000 }).should('contain', 'Updated');

        // Banner should appear even with no submission
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=9UjRZZmcdYidOrA&sort=id&direction=ASC']);
        cy.get('[data-testid="grading-rubric-btn"]').click();
        cy.get('[data-testid="overridden-grades-banner"]', { timeout: 10000 }).should('be.visible').should('contain', 'Overridden Grades');

        // Cleanup
        cy.visit(['sample', 'grade_override']);
        cy.get('[data-testid="grade-override-select-gradeable"]').select(gradeable_name);
        cy.get('[data-testid="student-grade-override"]').clear().type('aphacker{downarrow}{enter}');
        cy.get('[data-testid="grade-override-delete"]').click();
        cy.get('[data-testid="popup-message"]', { timeout: 10000 }).should('contain', 'deleted');
      });
});
