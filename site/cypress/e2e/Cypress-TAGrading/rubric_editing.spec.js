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
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=apfzuObm3E7o2vy&sort=id&direction=ASC']);
        cy.get('body').type('{A}');
        cy.get('body').type('{G}');
        cy.get('#edit-mode-enabled').click();
        cy.get('[data-testid^="component"]').eq(-1).click(20, 25);
        cy.get('[data-testid^="component"] [data-testid="save-tools-save"]')
            .should('contain', 'Save');
        cy.get('[data-testid="add-new-mark-button"]').click();
        cy.get('[aria-label="mark title"]').eq(-1).type('First New Mark');
        cy.get('[data-testid="add-new-mark-button"]').click();
        cy.get('[aria-label="mark title"]').eq(-1).type('Second New Mark');
        cy.get('[data-testid="save-tools-save"]').click();
        cy.get('[data-testid="add-new-mark-button"]')
            .should('not.exist');
        cy.get('[data-testid^="component"]').eq(-1).click(20, 25);
        cy.get('[data-testid^="component"] [data-testid="save-tools-save"]')
            .should('contain', 'Save');
        cy.get('[data-testid="mark-reorder"]').eq(-2).then(($el) => {
            const rect = $el[0].getBoundingClientRect();
            // eslint-disable-next-line cypress/unsafe-to-chain-command, cypress/no-unnecessary-waiting
            cy.wrap($el)
                .wait(300)
                .trigger('mousedown', { which: 1 })
                .wait(300)
                .trigger('mousemove', { which: 1, force: true, pageX: rect.left + 40, pageY: rect.top - 30 })
                .wait(300)
                .trigger('mouseup', { force: true });
        });
        cy.get('#edit-mode-enabled').click();
        cy.get('[data-testid^="component"] [data-testid="save-tools-save"]')
            .should('contain', 'Save');
        cy.get('[data-testid^="component"] .mark-title').eq(-3).should('contain', 'Second New Mark');
        cy.get('[data-testid^="component"] .mark-title').eq(-2).should('contain', 'First New Mark');
    });
});
