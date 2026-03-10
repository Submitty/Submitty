Cypress.on('uncaught:exception', () => false);

describe('Legal name privacy tests', () => {
    const legalTokens = [
        'LegalStudentFirst', 'LegalStudentLast',
        'LegalTaFirst', 'LegalTaLast',
        'LegalGraderFirst', 'LegalGraderLast',
        'LegalInstructorFirst', 'LegalInstructorLast',
    ];

    const checkNoLegalNames = () => {
        legalTokens.forEach(token => {
            cy.document().then(doc => {
                expect(doc.documentElement.innerHTML).to.not.include(token);
            });
        });
    };

    it('Legal names should not appear on the users page', () => {
        cy.visit(['sample', 'users']);
        cy.login('instructor');
        checkNoLegalNames();
    });

    it('Legal names should not appear on the graders page', () => {
        cy.visit(['sample', 'graders']);
        checkNoLegalNames();
    });

    it('Legal names should not appear on the student photos page', () => {
        cy.visit(['sample', 'student_photos']);
        checkNoLegalNames();
    });

    it('Legal names should not appear on the forum page', () => {
        cy.visit(['sample', 'forum']);
        checkNoLegalNames();
    });

    it('Legal names should not appear on the navigation page', () => {
        cy.visit(['sample', 'navigation']);
        checkNoLegalNames();
    });
});
