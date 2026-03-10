Cypress.on('uncaught:exception', () => false);

describe('Legal name privacy tests', () => {
    before(() => {
        cy.login('student');
        cy.visit('/user_profile');
        cy.get('.popup-form#edit-username-form').invoke('show').within(() => {
            cy.get('#user-givenname-change').clear().type('PreferredFirst');
            cy.get('#user-familyname-change').clear().type('PreferredLast');
        });
        cy.get('.popup-form#edit-username-form .form-buttons input[type="submit"]').click();
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
        cy.get('.popup-form#edit-username-form').invoke('show').within(() => {
            cy.get('#user-givenname-change').clear();
            cy.get('#user-familyname-change').clear();
        });
        cy.get('.popup-form#edit-username-form .form-buttons input[type="submit"]').click();
        cy.logout();
    });
});
