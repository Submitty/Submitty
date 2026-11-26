import { buildUrl } from '/cypress/support/utils.js';

const taGradingTimeUrl = (endpoint) => buildUrl([
    'sample',
    'gradeable',
    'grading_homework',
    'grading',
    'time',
    endpoint,
]);

const expectTimeEndpointError = (endpoint, body, expectedMessage) => {
    cy.request({
        method: 'POST',
        url: taGradingTimeUrl(endpoint),
        form: true,
        failOnStatusCode: false,
        body,
    }).then((response) => {
        expect(response.status).to.equal(200);
        if (typeof response.body === 'string' && response.body.trimStart().startsWith('<!DOCTYPE')) {
            expect(response.body).to.contain(expectedMessage);
            return;
        }
        const responseBody = typeof response.body === 'string' ? JSON.parse(response.body) : response.body;
        expect(responseBody.status).to.equal('error');
        expect(responseBody.message).to.contain(expectedMessage);
    });
};

describe('TA Grading details page', () => {
    it('ta grading ui testing', () => {
        cy.login();
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);
        cy.get('[data-testid="view-sections-label"]').should('contain', 'Only Assigned Sections');
        cy.get('[data-testid="view-sections"]').click();
        cy.get('[data-testid="random-order-label"]').should('contain', 'Randomize Order');
        cy.get('[data-testid="anon-students-label"]').should('contain', 'Anonymize Student Name');
        cy.get('[data-testid="inquiry-only-label"]').should('contain', 'Grade Inquiries Only');
        cy.get('[data-testid="stats-and-charts"]').should('contain', 'Statistics & Charts');
        cy.get('[data-testid="grade-button"]').should('be.visible');
        cy.get('[data-testid="toggle-all-sections"]').should('contain', 'Collapse All Sections');
        cy.get('[data-testid="toggle-all-sections"]').click();
        cy.get('[data-testid="grade-button"]').should('be.not.visible');
        cy.get('[data-testid="toggle-all-sections"]').should('contain', 'Expand All Sections');
        cy.get('[data-testid="toggle-all-sections"]').click();
        cy.get('[data-testid="grade-button"]').should('be.visible');
        // randomly re-assign will be present only for rotating section
        cy.visit(['sample', 'gradeable', 'grading_homework_team_pdf', 'grading', 'details']);
        cy.get('[data-testid="randomly-reassign-teams"]').should('contain', 'Randomly Re-Assign Teams to Rotating Sections');
        cy.get('[data-testid="export-team-members"]').should('contain', 'Export Teams Members');
        cy.visit(['sample', 'gradeable', 'grading_team_homework', 'grading', 'details']);
        cy.get('[data-testid="export-team-members"]').should('contain', 'Export Teams Members');
    });

    it('hides TA Time by default and allows graders to enable the column', () => {
        cy.login('instructor');
        cy.setCookie('grading_details_columns', '{}', { path: '/' });
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);
        cy.get('#details-table th[data-col-title="TA Time"]').should('not.exist');

        cy.get('[data-testid="toggle-columns"]').click();
        cy.get('[data-testid="toggle-ta_time"]').should('not.be.checked').check();
        cy.get('[data-testid="popup-save-button"]').click();
        cy.get('#details-table th[data-col-title="TA Time"]').should('exist');
    });

    it('closes an idle session and starts a new session when grading resumes', () => {
        cy.login('instructor');
        cy.intercept('POST', '**/grading/time/activity', {
            statusCode: 200,
            body: { status: 'success', data: null },
        }).as('taGradingTimeActivity');
        cy.intercept('POST', '**/grading/time/close', {
            statusCode: 200,
            body: { status: 'success', data: null },
        }).as('taGradingTimeClose');
        cy.intercept('POST', '**/grading/time/start', {
            statusCode: 200,
            body: { status: 'success', data: { session_id: 'a'.repeat(64) } },
        }).as('taGradingTimeStart');
        cy.clock(Date.now());
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'grade?who_id=Oith0AebfRyC8xK&sort=id&direction=ASC']);
        cy.window().then((window) => {
            cy.stub(window.navigator, 'sendBeacon').returns(false);
            window.dispatchEvent(new Event('click'));
        });
        cy.wait('@taGradingTimeActivity').its('response.body.status').should('equal', 'success');

        cy.tick(5 * 60 * 1000);
        cy.wait('@taGradingTimeClose').its('response.body.status').should('equal', 'success');
        cy.window().then((window) => {
            window.dispatchEvent(new Event('click'));
        });
        cy.wait('@taGradingTimeStart').its('response.body.status').should('equal', 'success');
        cy.wait('@taGradingTimeActivity').its('response.body.status').should('equal', 'success');
    });

    it('rejects TA grading time requests with an invalid CSRF token', () => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);
        const sessionId = 'a'.repeat(64);
        ['close', 'activity'].forEach((endpoint) => {
            expectTimeEndpointError(endpoint, {
                csrf_token: 'invalid-token',
                session_id: sessionId,
            }, 'Invalid CSRF token.');
        });
        expectTimeEndpointError('start', {
            csrf_token: 'invalid-token',
            submitter_id: 'Oith0AebfRyC8xK',
        }, 'Invalid CSRF token.');
    });

    it('rejects malformed session IDs for TA grading time updates and closes', () => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);
        cy.window().then((window) => {
            ['close', 'activity'].forEach((endpoint) => {
                expectTimeEndpointError(endpoint, {
                    csrf_token: window.csrfToken,
                    session_id: 'not-a-valid-session-id',
                }, 'Invalid grading time session.');
            });
        });
    });

    it('rejects TA grading time requests from a student account', () => {
        cy.login('bitdiddle');
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);
        cy.window().then((window) => {
            const sessionId = 'a'.repeat(64);
            expectTimeEndpointError('close', {
                csrf_token: window.csrfToken,
                session_id: sessionId,
            }, 'permission to close');
            expectTimeEndpointError('activity', {
                csrf_token: window.csrfToken,
                session_id: sessionId,
            }, 'permission to update');
            expectTimeEndpointError('start', {
                csrf_token: window.csrfToken,
                submitter_id: 'Oith0AebfRyC8xK',
            }, 'permission to start');
        });
    });

    it('does not expose TA grading or regrade actions to a student', () => {
        cy.login('bitdiddle');
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);
        cy.get('body').should(($body) => {
            const gradeActions = $body.find('[data-testid="grade-button"]').filter((_, button) => (
                /^Grade(?:\s|$)/.test(button.textContent.trim())
            ));
            expect(gradeActions).to.have.length(0);
        });
        cy.get('[data-testid="regrade-btn"]').should('not.exist');
    });
});
