import { buildUrl } from '/cypress/support/utils.js';

describe('Test that instructors are able to properly clear version conflicts', () => {
    const conflictedAnonId = 'ED9OAlz1ndBS6Um'; // haleyd
    const componentIds = [84, 85, 86, 87];

    const createConflict = () => {
        cy.window().then((win) => {
            cy.request({
                method: 'POST',
                url: buildUrl(['sample', 'gradeable', 'grading_pdf_peer_homework', 'grading', 'graded_gradeable', 'change_grade_version']),
                form: true,
                body: {
                    anon_id: conflictedAnonId,
                    graded_version: 2, // creates conflict
                    component_ids: componentIds,
                    csrf_token: win.csrfToken,
                },
            });
        });
    };

    beforeEach(() => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', 'grading_pdf_peer_homework', 'grading', 'grade?who_id=ED9OAlz1ndBS6Um&sort=id&direction=ASC']);
        createConflict();
        cy.reload();
    });

    it('Clear conflict button should appear only when there is a version conflict, and work', () => {
        cy.log('Button should exist if there is a version conflict');
        cy.get('[title="Show/Hide Peer Information (Press P)"]').click();
        cy.contains('button', 'Clear All Peer Version Conflicts').should('exist');

        cy.log('Button should not exist after clearing');
        cy.contains('button', 'Clear All Peer Version Conflicts').click();
        cy.reload();
        cy.contains('button', 'Clear All Peer Version Conflicts').should('not.exist');
    });

    it('Version conflict warning text should only appear on conflicted components', () => {
        cy.log('Warning should not appear on a clean student');
        cy.visit(['sample', 'gradeable', 'grading_pdf_peer_homework', 'grading', 'grade?who_id=JRtD1R2PcLNC3WL&sort=id&direction=ASC']);
        cy.get('[title="Show/Hide Peer Information (Press P)"]').click();
        cy.get('[data-testid="peer-version-warning"]').should('not.exist');

        cy.log('Warning should appear on the conflicted student');
        cy.visit(['sample', 'gradeable', 'grading_pdf_peer_homework', 'grading', 'grade?who_id=ED9OAlz1ndBS6Um&sort=id&direction=ASC']);
        cy.get('[title="Show/Hide Peer Information (Press P)"]').click();
        cy.get('[data-testid="peer-version-warning"]').should('exist');
    });

    it('Instructor can clear a specific version conflict without affecting others', () => {
        cy.get('[title="Show/Hide Peer Information (Press P)"]').click();

        componentIds.forEach((id) => {
            cy.get(`[data-component_id="${id}"]`).parent()
                .find('[data-testid="peer-version-warning"]').should('exist');
        });

        cy.get('[aria-label="Edit Peer Components"]').click();
        cy.get('.peer-edit-mark[data-component-id="84"][data-peer-id="baliss"]').first().check();
        cy.get('.peer-save-component[data-component-id="84"][data-peer-id="baliss"]').click();
        cy.get('[data-testid="close-button"]:visible').click();
        cy.reload();

        cy.get('body').then(($body) => {
            if (!$body.find('[data-component_id="84"]').is(':visible')) {
                cy.get('[title="Show/Hide Peer Information (Press P)"]').click();
            }
        });

        cy.get('[data-component_id="84"]').parent()
            .find('[data-testid="peer-version-warning"]').should('not.exist');

        [85, 86, 87].forEach((id) => {
            cy.get(`[data-component_id="${id}"]`).parent()
                .find('[data-testid="peer-version-warning"]').should('exist');
        });
    });

    it('Instructor can clear all version conflicts', () => {
        cy.get('[title="Show/Hide Peer Information (Press P)"]').click();
        cy.contains('button', 'Clear All Peer Version Conflicts').should('exist');

        componentIds.forEach((id) => {
            cy.get(`[data-component_id="${id}"]`).parent()
                .find('[data-testid="peer-version-warning"]').should('exist');
        });

        cy.contains('button', 'Clear All Peer Version Conflicts').click();
        cy.reload();

        cy.get('body').then(($body) => {
            if (!$body.find('[data-component_id="84"]').is(':visible')) {
                cy.get('[title="Show/Hide Peer Information (Press P)"]').click();
            }
        });

        cy.contains('button', 'Clear All Peer Version Conflicts').should('not.exist');

        componentIds.forEach((id) => {
            cy.get(`[data-component_id="${id}"]`).parent()
                .find('[data-testid="peer-version-warning"]').should('not.exist');
        });
    });
    it('Instructor can edit another peer graders component grades', () => {
        cy.get('[title="Show/Hide Peer Information (Press P)"]').click();
        cy.get('[aria-label="Edit Peer Components"]').click();

        cy.get('.peer-edit-mark[data-component-id="84"][data-peer-id="baliss"]').first().check();
        cy.get('.peer-save-component[data-component-id="84"][data-peer-id="baliss"]').click();

        cy.get('[data-testid="close-button"][onclick*="edit-peer-components-form"]').click();
        cy.reload();

        cy.get('[aria-label="Edit Peer Components"]').click();
        cy.get('[data-component_id="84"]').parent().then(($el) => {
            cy.log($el.text());
        });
    });

    it('Instructor can delete a peer graders grade', () => {
        cy.get('[title="Show/Hide Peer Information (Press P)"]').click();
        cy.get('[aria-label="Edit Peer Components"]').click();

        cy.get('.peer-edit-mark[data-component-id="84"][data-peer-id="baliss"]').first().check();
        cy.get('.peer-save-component[data-component-id="84"][data-peer-id="baliss"]').click();

        cy.get('[data-testid="close-button"][onclick*="edit-peer-components-form"]').should('have.length', 1).should('be.visible').click();

        cy.get('[aria-label="Edit Peer Components"]').should('be.visible').click();

        cy.get('button[onclick*="clearPeerMarks"][onclick*="\'baliss\'"]').first().click();

        cy.get('.peer-edit-mark[data-component-id="84"][data-peer-id="baliss"]').first().should('not.be.checked');
    });
});
