import { getCurrentSemester } from '/cypress/support/utils.js';

describe('Peer Participation panel', () => {
    const gradeableId = 'grading_pdf_peer_homework';
    const peerGraderId = 'baliss';
    // Links for both graded and not graded students, so the tests don't depend on how many of each exist
    const studentLinks = '[data-testid="peer-participation-student-link"], [data-testid="peer-participation-ungraded-link"]';

    const openPanel = (anonymize) => {
        // Same cookie the "Anonymize Student Names" checkbox on the Details page sets
        cy.setCookie('anon_mode', anonymize ? 'on' : 'off', { path: `/courses/${getCurrentSemester()}/sample` });
        cy.visit(['sample', 'gradeable', gradeableId, 'grading', `grade?who_id=${peerGraderId}&sort=id&direction=ASC`]);
        cy.get('[data-testid="peer-participation-btn"]').click();
        cy.get('[data-testid="peer-participation"]').should('be.visible');
    };

    beforeEach(() => {
        cy.login('instructor');
    });

    it('should show how many assigned students have been graded', () => {
        openPanel(false);
        cy.get('[data-testid="peer-participation-summary"]').invoke('text').then((text) => {
            const [graded, assigned, ungraded] = text.match(/Graded (\d+) of (\d+) assigned students? \((\d+) ungraded\)/).slice(1).map(Number);
            expect(graded + ungraded).to.eq(assigned);
            cy.get('[data-testid="peer-participation-ungraded-link"]').should('have.length', ungraded);
        });
    });

    it('should link each listed student to their grading page', () => {
        openPanel(false);
        cy.get(studentLinks).each(($link) => {
            const studentId = $link.text().trim();
            // Links use the student's anonymous ID, not the real ID that is shown
            cy.wrap($link).should('have.attr', 'href')
                .and('include', `/gradeable/${gradeableId}/grading/grade?who_id=`)
                .and('not.match', new RegExp(`who_id=${studentId}$`));
        });
        cy.get(studentLinks).first().then(($link) => {
            const studentId = $link.text().trim();
            cy.wrap($link).click();
            cy.get('[data-testid="grading-panel-student-name"]').should('contain', `(${studentId})`);
        });
    });

    it('should only show anonymous IDs when student names are anonymized', () => {
        openPanel(true);
        cy.get(studentLinks).each(($link) => {
            // The shown ID is the same anonymous ID the link uses
            cy.wrap($link).should('have.attr', 'href').and('match', new RegExp(`who_id=${$link.text().trim()}$`));
        });
        cy.get(studentLinks).first().then(($link) => {
            const anonId = $link.text().trim();
            cy.wrap($link).click();
            cy.url().should('include', `who_id=${anonId}`);
        });
    });
});
