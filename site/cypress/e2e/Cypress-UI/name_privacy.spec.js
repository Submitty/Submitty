describe('Legal name privacy tests', () => {
    const users = [
        { role: 'student', legalGiven: 'Joe' },
        { role: 'instructor', legalGiven: 'Quinn' },
        { role: 'ta', legalGiven: 'Jill' },
    ];

    before(() => {
        users.forEach((user) => {
            cy.login(user.role);
            cy.visit('/user_profile');
            cy.get('[data-testid="givenname-row"] button').first().click();
            cy.get('[data-testid="edit-preferred-name-form"]').within(() => {
                cy.get('[data-testid="preferred-givenname-input"]').clear();
                cy.get('[data-testid="preferred-givenname-input"]').type(`Pref${user.role}`);
                cy.get('[data-testid="preferred-familyname-input"]').clear();
                cy.get('[data-testid="preferred-familyname-input"]').type(`PrefLast${user.role}`);
            });
            cy.get('[data-testid="edit-preferred-name-submit"]').click();
            cy.logout();
        });
    });

    const checkNoLegalNames = () => {
        cy.document().then((doc) => {
            users.forEach((user) => {
                expect(doc.documentElement.innerHTML).to.not.include(user.legalGiven);
            });
        });
    };

    it('Legal names should not appear on instructor pages', () => {
        cy.login('instructor');
        const pages = ['users', 'graders', 'student_photos', 'forum', 'navigation'];
        pages.forEach((page) => {
            cy.visit(['sample', page]);
            checkNoLegalNames();
        });
        cy.logout();
    });

    it('Legal names should not appear on TA pages', () => {
        cy.login('ta');
        const pages = ['graders', 'forum', 'navigation'];
        pages.forEach((page) => {
            cy.visit(['sample', page]);
            checkNoLegalNames();
        });
        cy.logout();
    });

    it('Legal names should not appear on student pages', () => {
        cy.login('student');
        const pages = ['forum', 'navigation'];
        pages.forEach((page) => {
            cy.visit(['sample', page]);
            checkNoLegalNames();
        });
        cy.logout();
    });

    after(() => {
        users.forEach((user) => {
            cy.login(user.role);
            cy.visit('/user_profile');
            cy.get('[data-testid="givenname-row"] button').first().click();
            cy.get('[data-testid="edit-preferred-name-form"]').within(() => {
                cy.get('[data-testid="preferred-givenname-input"]').clear();
                cy.get('[data-testid="preferred-familyname-input"]').clear();
            });
            cy.get('[data-testid="edit-preferred-name-submit"]').click();
            cy.logout();
        });
    });
});
