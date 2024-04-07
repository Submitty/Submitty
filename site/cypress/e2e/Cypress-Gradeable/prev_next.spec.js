describe('Cyclic grading View Test', () => {
    ['ta', 'instructor'].forEach((user) => {
        it(`${user} grading_homework test`, () => {
            cy.login(user);
            cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'status']);
            cy.get('[data-testid="Grading Index"]').should('exist').click();
            /* when we login with "ta" (teaching assistant) we will get a pop up window
               in which it is mentioned that you are assigned for grade (something like this)
               so here we have to agree this if we close(Not cancel) it, it will pop up again
               when we go for grade(i mean clicking on grade button)*/
            if (user === 'ta') {
                cy.get('[data-testid="popup-window"]').should('exist');
                cy.get('[data-testid="close-button"]').should('exist');
                cy.get('[data-testid="close-hidden-button"]').should('exist');
                cy.get('[data-testid="agree-popup-btn"]').should('exist').click();
            }
            cy.get('[data-testid="view-sections"]').click();
            cy.get('[data-testid="grade-button"]').eq(12).click();
            cy.get('[data-testid="student_info_btn"]').click();
            cy.get('.rubric-title').should('contain', 'Joe Student');
            cy.get('#prev-student-navlink').click();
            cy.get('.rubric-title').should('contain', 'John Smith');
            cy.get('#grading-setting-btn > .invisible-btn > .fas').click();
            cy.get('#general-setting-list > tbody > :nth-child(2) > :nth-child(2) > .ta-grading-setting-option').select('Prev/Next Ungraded Student');
            cy.get('#settings-popup > .popup-box > [data-testid="popup-window"] > .form-title > [data-testid="close-button"]').click();
            cy.get('#next-student-navlink').click();
            cy.get('.rubric-title').should('contain', 'Joe Student');
        });
    });
    // cyclic grading view testing for Grading Team Homework
    ['ta', 'instructor'].forEach((user) => {
        it(`${user} grading_team_homework test`, () => {
            cy.login(user);
            cy.visit(['sample', 'gradeable', 'grading_team_homework', 'grading', 'status']);
            cy.get('[data-testid="Grading Index"]').click();
            /* when we login with "ta" (teaching assistant) we will get a pop up window
               in which it is mentioned that you are assigned for grade (something like this)
               so here we have to agree this if we close(Not cancel) it, it will pop up again
               when we go for grade(i mean clicking on grade button)*/
            if (user === 'ta') {
                cy.get('[data-testid="popup-window"]').should('exist');
                cy.get('[data-testid="close-button"]').should('exist');
                cy.get('[data-testid="close-hidden-button"]').should('exist');
                cy.get('[data-testid="agree-popup-btn"]').should('exist').click();
            }
            cy.get('[data-testid="view-sections"]').click();
            cy.get('[data-testid="grade-button"]').eq(1).click();
            cy.get('[data-testid="student_info_btn"]').click();
            cy.get('#grading-panel-student-name').should('exist');
            cy.get('#grading-panel-student-name').children();
            cy.get('#grading-panel-student-name').children().should('exist');
            cy.get('#grading-scroll-message').should('exist');
            cy.get('[data-testid="tab_bar_wrapper"').children().its('length').should('be.gte', 2);
            cy.get('#page_1_nav').should('contain', 'Ben Bitdiddle');
            cy.get('#page_2_nav').should('contain', 'Dannie Farrell');
            cy.get('#page_3_nav').should('contain',  'Adan Fisher');
            cy.get('#grading-panel-student-name').should('contain', 'Ben Bitdiddle').and('contain', 'Dannie Farrell').and('contain', 'Adan Fisher');
            cy.get('#next-student-navlink').click();
            cy.get('[data-testid="tab_bar_wrapper"').children().its('length').should('be.gte', 2);
            cy.get('#grading-panel-student-name').should('contain', 'Eunice Hamill ').and('contain', 'Edison King').and('contain', 'Justice Kuhic');
        });
    });
});
