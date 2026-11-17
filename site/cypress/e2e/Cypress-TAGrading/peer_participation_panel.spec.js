import { getCurrentSemester } from '/cypress/support/utils.js';

// These tests read the panel and compare it against itself instead of expecting specific students,
// since which peer grades exist depends on the sample data. Any grading they add is deleted again.
describe('Peer Participation panel', () => {
    const gradeableId = 'grading_pdf_peer_homework';
    const peerGraderId = 'baliss';

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

    // On a student's grading page, open the instructor's form for editing the peer grader's grades
    const openEditPeerForm = () => {
        cy.get('[data-testid="peer-info-btn"]').click();
        cy.get('[data-testid="peer-info"]').should('be.visible');
        cy.get('[data-testid="edit-peer-components-btn"]').click();
        cy.get('[data-testid="edit-peer-select"]').select(peerGraderId);
    };

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
            expect(assigned).to.be.greaterThan(0);
            expect(graded + ungraded).to.eq(assigned);
            cy.get('[data-testid="peer-participation"]').then(($panel) => {
                const gradedIds = linkTexts($panel, 'peer-participation-student-link');
                const ungradedIds = linkTexts($panel, 'peer-participation-ungraded-link');
                expect(ungradedIds).to.have.length(ungraded);
                // A student is listed as graded or as not graded, never both
                expect(gradedIds.filter((id) => ungradedIds.includes(id))).to.be.empty;
            });
        });
    });

    it('should move a student from not graded to graded once peer graded, linking to their page both times', () => {
        setAnonMode(false);
        openPanel();
        cy.get('[data-testid="peer-participation-ungraded-link"]').first().then(($link) => {
            const studentId = $link.text().trim();
            const exactId = new RegExp(`^\\s*${studentId}\\s*$`);

            // The "Not graded" link opens that student's grading page
            cy.wrap($link).click();
            cy.get('[data-testid="grading-panel-student-name"]').should('contain', `(${studentId})`);

            // Enter a grade for one peer component as the peer grader
            cy.intercept('POST', '**/save_peer_component').as('savePeerComponent');
            openEditPeerForm();
            cy.get(`[data-testid="peer-edit-mark"][data-peer-id="${peerGraderId}"]`).first().then(($mark) => {
                cy.wrap($mark).check();
                cy.get(`[data-testid="save-component-btn"][data-peer-id="${peerGraderId}"][data-component-id="${$mark.attr('data-component-id')}"]`).click();
            });
            cy.wait('@savePeerComponent').then(({ response }) => {
                const body = typeof response.body === 'string' ? JSON.parse(response.body) : response.body;
                expect(body.status, body.message).to.eq('success');
            });

            // The student now appears as graded, and that link also opens their grading page
            openPanel();
            cy.get('[data-testid="peer-participation"]').then(($panel) => {
                expect(linkTexts($panel, 'peer-participation-ungraded-link')).to.not.include(studentId);
            });
            cy.get('[data-testid="peer-participation-student-link"]').contains(exactId).click();
            cy.get('[data-testid="grading-panel-student-name"]').should('contain', `(${studentId})`);

            // Delete the grade so the student is back to not graded
            openEditPeerForm();
            cy.get(`[data-testid="clear-peer-marks-btn"][data-peer-id="${peerGraderId}"]`).click();
            cy.get('[data-testid="grading-panel-student-name"]').should('contain', `(${studentId})`);
            openPanel();
            cy.get('[data-testid="peer-participation-ungraded-link"]').contains(exactId).should('exist');
        });
    });

    it('should only show anonymous IDs when student names are anonymized', () => {
        // Collect the real IDs first, then check none of them appear in anonymous mode
        setAnonMode(false);
        openPanel();
        cy.get('[data-testid="peer-participation"]').then(($panel) => {
            const realIds = [
                ...linkTexts($panel, 'peer-participation-student-link'),
                ...linkTexts($panel, 'peer-participation-ungraded-link'),
            ];
            expect(realIds, 'listed students').to.not.be.empty;

            setAnonMode(true);
            openPanel();
            cy.get('[data-testid="peer-participation"]').then(($anonPanel) => {
                const testIds = ['peer-participation-student-link', 'peer-participation-ungraded-link'];
                const shownIds = testIds.flatMap((testId) => linkTexts($anonPanel, testId));
                const linkedIds = testIds.flatMap((testId) => linkWhoIds($anonPanel, testId));
                expect(shownIds).to.have.length(realIds.length);
                // Links always use the anonymous ID shown, never a real one
                expect(linkedIds).to.deep.equal(shownIds);
                shownIds.forEach((id) => expect(realIds).to.not.include(id));
            });
        });

        cy.get('[data-testid="peer-participation-ungraded-link"]').first().then(($link) => {
            const anonId = $link.text().trim();
            cy.wrap($link).click();
            cy.url().should('include', `who_id=${anonId}`);
        });
    });
});
