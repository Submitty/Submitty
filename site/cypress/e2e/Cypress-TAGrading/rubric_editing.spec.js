describe('Test cases for TA grading page', () => {
    after(() => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'grading_homework', 'update?nav_tab=2']);
        cy.on('window:confirm', () => true);
        // Keep deleting the last component until only the original 4 remain
        cy.get('[title="Delete this component"]').then(function deleteExtra($buttons) {
            if ($buttons.length > 4) {
                cy.wrap($buttons.eq(-1)).click();
                cy.get('[title="Delete this component"]', { timeout: 10000 }).then(deleteExtra);
            }
        });
    });
    it('Should test rubric editing', () => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'grading_homework', 'update?nav_tab=2']);

        cy.get('[data-testid^="component"]').then(($el) => {
            const count = $el.length;
            cy.get('[value="Add New Component"]').click();
            cy.get('[data-testid^="component"]').should('have.length', count + 1).then(() => {
                cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=apfzuObm3E7o2vy&sort=id&direction=ASC']);
            });
        });
        cy.get('body').type('{A}');
        cy.get('body').type('{G}');
        cy.get('#edit-mode-enabled').click();
        // Matching with ^ to find last component regardless of id
        cy.get('[data-testid^="component"]').eq(-1).click(20, 25);
        cy.get('[data-testid^="component"] [data-testid="save-tools-save"]')
            .should('contain', 'Save');
        cy.get('[data-testid="mark-title-input"]').then(($titles) => {
            const initialMarkCount = $titles.length;
            $titles.each((_, element) => {
                expect(element.value).to.not.equal('');
            });
            cy.get('input[aria-label="mark value"]').each((_, element) => {
                expect(element.value).to.not.equal('');
            });
            cy.get('[data-testid="save-tools-cancel"]').click();
            cy.get('[data-testid^="component"]').eq(-1).click(20, 25);
            cy.get('[data-testid="mark-title-input"]').should('have.length', initialMarkCount);
            cy.get('[data-testid="mark-title-input"]').each((_, element) => {
                expect(element.value).to.not.equal('');
            });
            cy.get('input[aria-label="mark value"]').each((_, element) => {
                expect(element.value).to.not.equal('');
            });
        });
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
