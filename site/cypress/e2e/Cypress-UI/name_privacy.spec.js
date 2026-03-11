describe('Legal name privacy tests', () => {
    before(() => {
        cy.login('student');
        cy.visit('/user_profile');
        cy.get('[data-testid="edit-preferred-name-form"]').invoke('show').within(() => {
            cy.get('#user-givenname-change').clear().type('PreferredFirst');
            cy.get('#user-familyname-change').clear().type('PreferredLast');
        });
        cy.get('[data-testid="edit-preferred-name-submit"]').click();
        cy.logout();
    });

    const checkNoLegalNames = () => {
        cy.document().then(doc => {
            expect(doc.documentElement.innerHTML).to.not.include('Joe');
        });
    };

    it('Legal names should not appear on any page', () => {
        cy.login('instructor');
        const pages = ['users', 'graders', 'student_photos', 'forum', 'navigation'];
        pages.forEach(page => {
            cy.visit(['sample', page]);
            checkNoLegalNames();
        });
        cy.logout();
    });

    after(() => {
        cy.login('student');
        cy.visit('/user_profile');
        cy.get('[data-testid="edit-preferred-name-form"]').invoke('show').within(() => {
            cy.get('#user-givenname-change').clear();
            cy.get('#user-familyname-change').clear();
        });
        cy.get('[data-testid="edit-preferred-name-submit"]').click();
        cy.logout();
    });
});
