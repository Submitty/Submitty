describe('Test cases for TA grading page', () => {
    it('Grader should be able to add and remove overall comments', () => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=apfzuObm3E7o2vy&sort=id&direction=ASC']);
        cy.get('body').type('{A}');
        cy.get('body').type('{G}');
        cy.get('[data-testid="grading-rubric"]').should('contain', 'Grading Rubric');
        cy.get('[data-testid="component-container"]').its('length').should('eq', 4);
        cy.get('[data-testid="component-container"]').eq(0).should('contain', 'Read Me');
        cy.get('[data-testid="component-container"]').eq(1).should('contain', 'Coding Style');
        cy.get('[data-testid="component-container"]').eq(2).should('contain', 'Documentation');
        cy.get('[data-testid="component-container"]').eq(3).should('contain', 'Extra Credit');
        cy.get('[data-testid="component-64"]').should('contain', 'Read Me');
        cy.get('[data-testid="component-64"]').click();
        cy.get('[data-testid="component-64"]')
            .should('contain', 'Full Credit')
            .and('contain', 'Minor errors in Read Me')
            .and('contain', 'Major errors in Read Me or Read Me missing');
        cy.get('body').type('{0}');
        cy.get('[data-testid="grading-total"]').eq(0).should('contain', '2 / 2');
        cy.get('[data-testid="save-tools-save"]').should('contain', 'Save');
        cy.get('[data-testid="save-tools-save"]').click();
        cy.get('[data-testid="component-65"]').should('contain', 'Coding Style');
        cy.get('[data-testid="component-65"]').click();
        cy.get('[data-testid="component-65"]')
            .should('contain', 'Full Credit')
            .and('contain', 'Code is unreadable')
            .and('contain', 'Code is very difficult to understand')
            .and('contain', 'Code is difficult to understand');
        cy.get('body').type('{3}');
        cy.get('[data-testid="grading-total"]').eq(1).should('contain', '4 / 5');
        cy.get('[data-testid="save-tools-save"]').should('contain', 'Save');
        cy.get('[data-testid="save-tools-save"]').click();
        cy.get('[data-testid="component-66"]').should('contain', 'Documentation');
        cy.get('[data-testid="component-66"]').click();
        cy.get('[data-testid="component-66"]')
            .should('contain', 'Full Credit')
            .and('contain', 'No documentation')
            .and('contain', 'Very little documentation or documentation makes no sense')
            .and('contain', 'Way too much documentation and/or documentation makes no sense');
        cy.get('body').type('{2}');
        cy.get('[data-testid="grading-total"]').eq(2).should('contain', '2 / 5');
        cy.get('[data-testid="save-tools-save"]').should('contain', 'Save');
        cy.get('[data-testid="save-tools-save"]').click();
        cy.get('[data-testid="component-67"]').should('contain', 'Extra Credit');
        cy.get('[data-testid="component-67"]').click();
        cy.get('[data-testid="component-67"]')
            .should('contain', 'Full Credit')
            .and('contain', 'Extra credit done poorly')
            .and('contain', 'Extra credit is acceptable');
        cy.get('body').type('{0}');
        cy.get('[data-testid="grading-total"]').eq(3).should('contain', '0 / 0');
        cy.get('[data-testid="save-tools-save"]').should('contain', 'Save');
        cy.get('[data-testid="save-tools-save"]').click();
        cy.get('[data-testid="grading-total"]').eq(0).should('contain', '2 / 2');
        cy.get('[data-testid="grading-total"]').eq(1).should('contain', '4 / 5');
        cy.get('[data-testid="grading-total"]').eq(2).should('contain', '2 / 5');
        cy.get('[data-testid="grading-total"]').eq(3).should('contain', '0 / 0');
        cy.get('[data-testid="grading-total"]').eq(4).should('contain', '8 / 12');
    });
});
