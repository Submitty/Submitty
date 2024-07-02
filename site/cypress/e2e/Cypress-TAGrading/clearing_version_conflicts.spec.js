describe('Test cases for checking the clear version conflicts button in the TA grading interface', () => {
    it('Button should not appear if there are no version conflicts', () => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=LHmpGciilVzjcEJ&sort=id&direction=ASC']);
        cy.get('#grading_rubric_btn').click();
        cy.get('#change-graded-version').should('not.exist');
        cy.get('.version-warning').should('not.exist');
    });

    it('Button should appear if there are version conflicts', () => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=K8jI3q4qpdCc1jw&sort=id&direction=ASC&gradeable_version=1']);
        cy.get('#grading_rubric_btn').click();
        cy.get('#change-graded-version').should('exist');
        cy.get('.version-warning').should('exist');
    });

    it('Clicking the button should resolve those version conflicts', () => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=K8jI3q4qpdCc1jw&sort=id&direction=ASC&gradeable_version=1']);
        cy.get('#grading_rubric_btn').click();

        // wait until page is fully loaded
        cy.get('#component-list').children().should('have.length.least', 1);

        cy.get('.version-warning').should('exist');

        cy.get('#change-graded-version').click();
        cy.get('#change-graded-version').should('not.exist');

        // wait until page is fully loaded
        cy.get('#component-list').children().should('have.length.least', 1);

        cy.get('.version-warning').should('not.exist');

        // reset state
        cy.window().then(async (win) => {
            await win.ajaxChangeGradedVersion(win.getGradeableId(), win.getAnonId(), 2);
        });

        cy.reload();
        cy.get('.version-warning').should('exist');
    });
});
