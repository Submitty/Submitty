describe('Tests leaderboard access', () => {
    ['instructor', 'student'].forEach((user) => {
        it(`Leaderboard visibility check for ${user}`, () => {    
            cy.login(user);        
            cy.visit(['sample']);
            if (user === 'instructor') {
                // Change the date as instructor

                cy.get('#leaderboard')
                .find('a.fas.fa-pencil-alt.black-btn[title="Edit Gradeable Configuration"]')
                .click();

                cy.get('#page_5_nav').click();
                cy.get('[data-testid="submission-open-date"]').type('2100-01-15 23:59:59');

                cy.logout();
            } else if (user === 'student') {
                cy.visit(['sample', 'gradeable', 'leaderboard', 'leaderboard']);
                cy.get('.content').should(
                    'contain.text',
                    'leaderboard is not a valid electronic submission gradeable. Contact your instructor if you think this is an error.'
                  );
                cy.logout();
                  
            }
        });
    });
});
