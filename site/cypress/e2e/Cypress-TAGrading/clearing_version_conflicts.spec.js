describe('Test cases for checking the clear version conflicts button in the TA grading interface', () => {
    it('Clear conflict button should appear only when there is a version conflict, and work', () => {
        cy.login('instructor');

        cy.log('Button should not exist if there is no version conflict');
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=LHmpGciilVzjcEJ&sort=id&direction=ASC']);
        cy.get('[data-testid="grading-rubric-btn"]').click();
        cy.get('[data-testid="change-graded-version"]').should('not.exist');
        cy.get('[data-testid="version-warning"]').should('not.exist');

        cy.log('Button should exist if there is a version conflict');
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=K8jI3q4qpdCc1jw&sort=id&direction=ASC&gradeable_version=1']);
        cy.get('[data-testid="grading-rubric-btn"]').click();
        cy.get('[data-testid="change-graded-version"]').should('exist');
        cy.get('[data-testid="version-warning"]').should('exist');

        cy.log('Clicking the button should resolve the version conflict');
        cy.get('[data-testid="grading-rubric-btn"]').click();

        // wait until page is fully loaded
        cy.get('[data-testid="component-list"]').children().should('have.length.least', 1);

        cy.get('[data-testid="version-warning"]').should('exist');

        cy.get('[data-testid="change-graded-version"]').click();
        cy.get('[data-testid="change-graded-version"]', { timeout: 10000 }).should('not.be.visible');

        cy.get('[data-testid="version-warning"]').should('not.exist');

        // reset state
        cy.window().then(async (win) => {
            await win.ajaxChangeGradedVersion(win.getGradeableId(), win.getAnonId(), 2, win.getAllComponentsFromDOM().map((x) => x.id));
        });

        cy.reload();
        cy.get('[data-testid="version-warning"]').should('exist');
    });

    it('Clicking "save" on a component with a version conflict should resolve the conflict for that component', () => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=K8jI3q4qpdCc1jw&sort=id&direction=ASC&gradeable_version=1']);
        cy.get('[data-testid="grading-rubric-btn"]').click();

        // wait until page is fully loaded
        cy.get('[data-testid="component-list"]').children().should('have.length.least', 1);
        cy.get('[data-testid="component-list"]').children().eq(0).as('test-component');

        cy.get('@test-component').within(() => {
            cy.get('[data-testid="version-warning"]').should('exist');
            cy.get('@test-component').children().eq(0).children().eq(0).click();

            cy.get('[data-testid="save-tools-save"]').contains('Save and resolve version conflict');
            cy.get('[data-testid="save-tools-save"]').click();

            cy.get('[data-testid="version-warning"]', { timeout: 10000 }).should('not.exist');
        });

        cy.reload();

        cy.get('[data-testid="component-list"]').children().should('have.length.least', 1);
        cy.get('[data-testid="component-list"]').children().eq(0).as('test-component');
        cy.get('@test-component').within(() => {
            cy.get('[data-testid="version-warning"]').should('not.exist');
        });

        // reset state
        cy.get('@test-component').children().eq(0).then((el) => {
            cy.window().then(async (win) => {
                await win.ajaxChangeGradedVersion(win.getGradeableId(), win.getAnonId(), 2, [win.getComponentIdFromDOMElement(el[0])]);
            });
        });

        cy.reload();

        cy.get('[data-testid="component-list"]').children().should('have.length.least', 1);
        cy.get('[data-testid="component-list"]').children().eq(0).as('test-component');
        cy.get('@test-component').within(() => {
            cy.get('[data-testid="version-warning"]').should('exist');
        });
    });
});
