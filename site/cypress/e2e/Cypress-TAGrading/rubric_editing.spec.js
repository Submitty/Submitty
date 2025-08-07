describe('Test cases for TA grading page', () => {
    after(() => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'grading_homework', 'update?nav_tab=2']);
        cy.get('[title="Delete this component"]').eq(-1).as('delete-me-button');
        cy.get('@delete-me-button').click();
        cy.get('@delete-me-button').then(() => {
            cy.on('window:confirm', (str) => {
                expect(str).to.equal('Are you sure you want to delete this component?');
            });
        });
    });
    it('Should test rubric editing', () => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'grading_homework', 'update?nav_tab=2']);

        cy.get('[value="Add New Component"]').click();

        cy.get('[data-testid^="component"]').should('have.length', 6);
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=6aVXWyYVWGYMvz3&sort=id&direction=ASC']); // beahaf anon id
        cy.get('body').type('{A}');
        cy.get('body').type('{G}');
        cy.get('#edit-mode-enabled').click();
        // Matching with ^ to find last component regardless of id
        cy.get('[data-testid^="component"]').eq(-1).click(20, 25);
        cy.get('[data-testid^="component"] [data-testid="save-tools-save"]')
            .should('contain', 'Save');
        cy.get('[data-testid="add-new-mark-button"]').click();
        cy.get('[data-testid="mark-title-input"]').eq(-1).type('First New Mark');
        cy.get('[data-testid="add-new-mark-button"]').click();
        cy.get('[data-testid="mark-title-input"]').eq(-1).type('Second New Mark');
        cy.get('[data-testid="save-tools-save"]').click();
        cy.get('[data-testid="add-new-mark-button"]')
            .should('not.exist');
        /* commented out due to issue #11309 becuase Cypress drag and drop is flaky TODO: Find permanent solution
        cy.get('[data-testid^="component"]').eq(-1).click(20, 25);
        cy.get('[data-testid^="component"] [data-testid="save-tools-save"]')
            .should('contain', 'Save');
        cy.get('[data-testid="mark-reorder"]').eq(-2).then(($el) => {
            const rect = $el[0].getBoundingClientRect();
            // Needed due to drag and drop weirdness
            // eslint-disable-next-line cypress/no-unnecessary-waiting, cypress/unsafe-to-chain-command
            cy.wrap($el)
                .trigger('mousedown', { which: 1 })
                .wait(1000)
                .trigger('mousemove', { which: 1, force: true, pageX: rect.left + 40, pageY: rect.top - 30 })
                .wait(1000)
                .trigger('mouseup', { force: true });
        });
        cy.get('#edit-mode-enabled').click();
        cy.get('[data-testid^="component"] [data-testid="save-tools-save"]')
            .should('contain', 'Save');
        cy.get('[data-testid^="component"] [data-testid="mark-title"]').eq(-3).should('contain', 'Second New Mark');
        cy.get('[data-testid^="component"] [data-testid="mark-title"]').eq(-2).should('contain', 'First New Mark');
        */
    });
});
