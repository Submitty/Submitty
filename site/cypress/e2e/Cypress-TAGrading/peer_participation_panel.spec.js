import { getCurrentSemester } from '/cypress/support/utils.js';

describe('Peer Participation panel', () => {
    const gradeableId = 'grading_pdf_peer_homework';
    // baliss has peer graded haleyd in the sample data. Peer assignments come from
    // .setup/data/random/graders.txt and students.txt, so they are the same on every build.
    const peerGraderId = 'baliss';
    const assignedIds = ['adamsg', 'efferm', 'haleyd', 'heided', 'hirthn', 'lefflv', 'rogahb', 'schads', 'schupb', 'zulauk'];

    // Same cookie the "Anonymize Student Names" checkbox on the Details page sets
    const setAnonMode = (on) => {
        cy.setCookie('anon_mode', on ? 'on' : 'off', { path: `/courses/${getCurrentSemester()}/sample` });
    };

    const openPanel = () => {
        cy.visit(['sample', 'gradeable', gradeableId, 'grading', `grade?who_id=${peerGraderId}&sort=id&direction=ASC`]);
        cy.get('[data-testid="peer-participation-btn"]').click();
        cy.get('[data-testid="peer-participation"]').should('be.visible');
    };

    const linkTexts = ($panel, testId) => $panel.find(`[data-testid="${testId}"]`).map((i, el) => el.textContent.trim()).get();
    const linkWhoIds = ($panel, testId) => $panel.find(`[data-testid="${testId}"]`)
        .map((i, el) => new URL(el.href).searchParams.get('who_id')).get();

    beforeEach(() => {
        cy.login('instructor');
    });

    it('should count the assigned students and list each one as graded or not graded', () => {
        setAnonMode(false);
        openPanel();
        cy.get('[data-testid="peer-participation-summary"]').invoke('text').then((text) => {
            const match = text.match(/Graded (\d+) of (\d+) assigned students? \((\d+) ungraded\)/);
            expect(match, 'summary line').to.not.equal(null);
            const [graded, assigned, ungraded] = match.slice(1).map(Number);
            expect(assigned).to.eq(assignedIds.length);
            expect(graded + ungraded).to.eq(assigned);
            cy.get('[data-testid="peer-participation-ungraded-link"]').should('have.length', ungraded);
        });
        cy.get('[data-testid="peer-participation"]').then(($panel) => {
            const gradedIds = linkTexts($panel, 'peer-participation-student-link');
            const ungradedIds = linkTexts($panel, 'peer-participation-ungraded-link');
            expect(gradedIds, 'graded students').to.not.be.empty;
            expect(ungradedIds, 'ungraded students').to.not.be.empty;
            // Every assigned student is listed exactly once, either as graded or as not graded
            expect(gradedIds.filter((id) => ungradedIds.includes(id))).to.be.empty;
            expect([...gradedIds, ...ungradedIds]).to.include.members(assignedIds);
            ungradedIds.forEach((id) => expect(assignedIds).to.include(id));
        });
    });

    it('should link graded and ungraded students to their grading pages', () => {
        setAnonMode(false);
        openPanel();
        cy.get('[data-testid="peer-participation-student-link"]').first().then(($link) => {
            const studentId = $link.text().trim();
            cy.wrap($link).click();
            cy.get('[data-testid="grading-panel-student-name"]').should('contain', `(${studentId})`);
        });

        openPanel();
        cy.get('[data-testid="peer-participation-ungraded-link"]').first().then(($link) => {
            const studentId = $link.text().trim();
            cy.wrap($link).click();
            cy.get('[data-testid="grading-panel-student-name"]').should('contain', `(${studentId})`);
        });
    });

    it('should only show anonymous IDs when student names are anonymized', () => {
        setAnonMode(true);
        openPanel();
        cy.get('[data-testid="peer-participation"]').then(($panel) => {
            const testIds = ['peer-participation-student-link', 'peer-participation-ungraded-link'];
            const shownIds = testIds.flatMap((testId) => linkTexts($panel, testId));
            const linkedIds = testIds.flatMap((testId) => linkWhoIds($panel, testId));
            expect(shownIds, 'listed students').to.not.be.empty;
            // Links always use the anonymous ID, matching the text shown
            expect(linkedIds).to.deep.equal(shownIds);
            shownIds.forEach((id) => expect(assignedIds).to.not.include(id));
        });

        cy.get('[data-testid="peer-participation-ungraded-link"]').first().then(($link) => {
            const anonId = $link.text().trim();
            cy.wrap($link).click();
            cy.url().should('include', `who_id=${anonId}`);
        });
    });
});
