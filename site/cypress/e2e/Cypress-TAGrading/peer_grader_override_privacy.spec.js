import { buildUrl } from '../../support/utils';

// Peer graders must never see grade override information: not the "Overridden (x / y)" button on
// the grading list, and not the "Overridden grades" banner or message on the grading page.
describe('Peer graders do not see grade overrides', () => {
    const gradeableId = 'grading_pdf_peer_homework';
    // In the sample data baliss is assigned to peer grade haleyd on this gradeable
    // (peer_panel_version_conflicts.spec.js relies on the same pair).
    const peerGraderId = 'baliss';
    const overriddenStudentId = 'haleyd';
    const overriddenAnonId = 'ED9OAlz1ndBS6Um'; // haleyd
    const overrideComment = 'Peer graders should never see this reason';

    const gradingUrl = ['sample', 'gradeable', gradeableId, 'grading', `grade?who_id=${overriddenAnonId}`];

    // Logs in as the instructor, calls the grade override 'update' or 'delete' route, then logs out
    // so the caller can log in as someone else. Both routes are safe to repeat.
    const changeOverride = (action, body = {}) => {
        cy.login('instructor');
        cy.window().then((win) => {
            cy.request({
                method: 'POST',
                url: buildUrl(['sample', 'grade_override', gradeableId, action]),
                form: true,
                body: {
                    user_id: overriddenStudentId,
                    csrf_token: win.csrfToken,
                    ...body,
                },
            }).its('status').should('eq', 200);
        });
        cy.visit('/');
        cy.logout();
    };

    beforeEach(() => {
        changeOverride('update', { marks: 3, comment: overrideComment });
    });

    afterEach(() => {
        // The test left its own user logged in, so log out first
        cy.logout();
        changeOverride('delete');
    });

    it('does not show the override on the peer grader grading list', () => {
        cy.login(peerGraderId);
        cy.visit(['sample', 'gradeable', gradeableId, 'grading', 'details']);
        cy.get('[data-testid="popup-save-button"]').click();
        // The overridden student is on this peer grader's list, but looks like any other student
        cy.get(`[data-testid="grade-button"][href*="who_id=${overriddenAnonId}"]`)
            .should('exist')
            .and('not.contain', 'Overridden');
        cy.contains('Overridden').should('not.exist');
    });

    it('does not show the override on the peer grader grading page', () => {
        cy.login(peerGraderId);
        cy.visit(gradingUrl);
        // Make sure the grading page itself loaded before checking for absences
        cy.get('#electronic-gradeable-container').should('exist');
        cy.contains('Overridden').should('not.exist');
        cy.contains(overrideComment).should('not.exist');
    });

    it('still shows the override to instructors', () => {
        // Control: proves the override really exists, so the checks above are meaningful
        cy.login('instructor');
        cy.visit(gradingUrl);
        cy.contains('Overridden grades:').should('exist');
        cy.contains(overrideComment).should('exist');
    });
});
