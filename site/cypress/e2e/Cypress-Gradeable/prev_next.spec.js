describe('Cyclic grading View Test', () => {
    ['ta', 'instructor'].forEach((user) => {
        it(`${user} cyclic grading view testing for Grading Homework`, () => {
            cy.login(user);
            cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'status']);
            cy.get('[data-testid="Grading Index"]').should('exist').click();
            if (user === 'ta') {
                cy.get('[data-testid="popup-window"]').should('exist');
                cy.get('[data-testid="close-button"]').should('exist');
                cy.get('[data-testid="close-hidden-button"]').should('exist');
                cy.get('[data-testid="agree-popup-btn"]').should('exist').click();
            }
            cy.get('[data-testid="view-sections"]').click();
            cy.get('[data-testid="grade-button"]').eq(12).click();
            cy.get('[data-testid="student-info-btn"]').click();
            cy.get('.rubric-title').should('contain', 'Joe Student');
            cy.get('#prev-student-navlink').click();
            cy.get('.rubric-title').should('contain', 'John Smith');
            cy.get('#grading-setting-btn').click(); // change
            cy.get('#general-setting-list').find('.ta-grading-setting-option').first().select('Prev/Next Ungraded Student');
            cy.get('.form-title > [data-testid="close-button"]').should('exist').eq(3).click();
            cy.get('#next-student-navlink').click();
            cy.get('.rubric-title').should('contain', 'Joe Student');
        });
    });
    ['ta', 'instructor'].forEach((user) => {
        it(`${user} cyclic grading view testing for Grading Team Homework`, () => {
            cy.login(user);
            cy.visit(['sample', 'gradeable', 'grading_team_homework', 'grading', 'status']);
            cy.get('[data-testid="Grading Index"]').should('exist').click();
            if (user === 'ta') {
                cy.get('[data-testid="popup-window"]').should('exist');
                cy.get('[data-testid="close-button"]').should('exist');
                cy.get('[data-testid="close-hidden-button"]').should('exist');
                cy.get('[data-testid="agree-popup-btn"]').should('exist').click();
            }
            cy.get('[data-testid="view-sections"]').click();
            cy.get('[data-testid="grade-button"]').eq(1).click();
            cy.get('[data-testid="student-info-btn"]').click();
            cy.get('#grading-panel-student-name').should('exist');
            cy.get('#grading-panel-student-name').children().should('exist');
            cy.get('#grading-scroll-message').should('exist');
            cy.get('[data-testid="tab-bar-wrapper"').children().its('length').should('be.eq', 3);
            cy.get('#page_1_nav').should('contain', 'Ben Bitdiddle');
            cy.get('#page_2_nav').should('contain', 'Dannie Farrell');
            cy.get('#page_3_nav').should('contain',  'Adan Fisher');
            cy.get('#grading-panel-student-name').should('contain', 'Ben Bitdiddle').and('contain', 'Dannie Farrell').and('contain', 'Adan Fisher');
            cy.get('#next-student-navlink').click();
            cy.get('[data-testid="tab-bar-wrapper"').children().its('length').should('be.gte', 2);
            cy.get('#grading-panel-student-name').should('contain', 'Eunice Hamill ').and('contain', 'Edison King').and('contain', 'Justice Kuhic');
        });
    });
});
