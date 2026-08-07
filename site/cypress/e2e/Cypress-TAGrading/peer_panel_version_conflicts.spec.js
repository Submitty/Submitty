import { buildUrl } from '/cypress/support/utils.js';

describe('Peer panel version conflicts', () => {
    const gradeableId = 'grading_pdf_peer_homework';
    const conflictedAnonId = 'ED9OAlz1ndBS6Um'; // haleyd
    const cleanAnonId = 'JRtD1R2PcLNC3WL';
    const peerGraderId = 'baliss';
    const componentIds = [84, 85, 86, 87];

    const gradingUrl = (anonId) => ['sample', 'gradeable', gradeableId, 'grading', `grade?who_id=${anonId}&sort=id&direction=ASC`];

    const peerComponent = (componentId) => cy.get(`#peer-component-${componentId}`, { timeout: 10000 });

    const openEditPeerComponentsForm = () => {
        cy.get('[data-testid="edit-peer-components-btn"]').click();
        cy.get('#edit-peer-components-form').should('be.visible');
    };

    const createConflictForPeerGrader = () => {
        cy.login(peerGraderId);
        cy.window().then((win) => {
            cy.request({
                method: 'POST',
                url: buildUrl(['sample', 'gradeable', gradeableId, 'grading', 'graded_gradeable', 'change_grade_version']),
                form: true,
                body: {
                    anon_id: conflictedAnonId,
                    graded_version: 2,
                    component_ids: componentIds,
                    csrf_token: win.csrfToken,
                },
            }).its('status').should('eq', 200);
        });
        cy.visit('/');
        cy.logout();
    };

    beforeEach(() => {
        createConflictForPeerGrader();
        cy.login('instructor');
    });

    it('Clear conflict button should appear only when there is a version conflict, and work', () => {
        cy.visit(gradingUrl(conflictedAnonId));
        cy.get('[data-testid="peer-info-btn"]').click();
        cy.get('[data-testid="peer-info"]').should('be.visible');
        cy.get('[data-testid="clear-all-peer-version-conflicts-btn"]').should('exist').click();
        cy.reload();
        cy.get('[data-testid="peer-info"]').should('be.visible');
        cy.get('[data-testid="clear-all-peer-version-conflicts-btn"]').should('not.exist');
    });

    it('Version conflict warning text should only appear on conflicted components', () => {
        cy.visit(gradingUrl(conflictedAnonId));
        cy.get('[data-testid="peer-info-btn"]').click();
        cy.get('[data-testid="peer-info"]').should('be.visible');
        componentIds.forEach((componentId) => {
            peerComponent(componentId).find('[data-testid="peer-version-warning"]').should('exist');
        });
        cy.visit(gradingUrl(cleanAnonId));
        cy.get('[data-testid="peer-info"]').should('be.visible');
        cy.get('[data-testid="peer-version-warning"]').should('not.exist');
    });

    it('Instructor can clear a specific version conflict without affecting others', () => {
        cy.visit(gradingUrl(conflictedAnonId));
        cy.get('[data-testid="peer-info-btn"]').click();
        cy.get('[data-testid="peer-info"]').should('be.visible');
        componentIds.forEach((componentId) => {
            peerComponent(componentId).find('[data-testid="peer-version-warning"]').should('exist');
        });
        openEditPeerComponentsForm();
        cy.get(`[data-testid="save-component-btn"][data-component-id="84"][data-peer-id="${peerGraderId}"]`).click();
        cy.get('[data-testid="close-button"]:visible').click();
        cy.reload();
        cy.get('[data-testid="peer-info"]').should('be.visible');
        peerComponent(84).find('[data-testid="peer-version-warning"]').should('not.exist');
        [85, 86, 87].forEach((componentId) => {
            peerComponent(componentId).find('[data-testid="peer-version-warning"]').should('exist');
        });
    });

    it('Instructor can clear all version conflicts', () => {
        cy.visit(gradingUrl(conflictedAnonId));
        cy.get('[data-testid="peer-info-btn"]').click();
        cy.get('[data-testid="peer-info"]').should('be.visible');
        componentIds.forEach((componentId) => {
            peerComponent(componentId).find('[data-testid="peer-version-warning"]').should('exist');
        });
        cy.get('[data-testid="clear-all-peer-version-conflicts-btn"]').click();
        cy.reload();
        cy.get('[data-testid="peer-info"]').should('be.visible');
        cy.get('[data-testid="clear-all-peer-version-conflicts-btn"]').should('not.exist');
        componentIds.forEach((componentId) => {
            peerComponent(componentId).find('[data-testid="peer-version-warning"]').should('not.exist');
        });
    });

    it('Instructor can edit another peer graders component grades', () => {
        cy.visit(gradingUrl(conflictedAnonId));
        cy.get('[data-testid="peer-info-btn"]').click();
        cy.get('[data-testid="peer-info"]').should('be.visible');
        openEditPeerComponentsForm();
        const markSelector = `[data-testid="peer-edit-mark"][data-component-id="84"][data-peer-id="${peerGraderId}"]`;
        let initiallyChecked;

        cy.get(markSelector, { timeout: 10000 }).first().then(($mark) => {
            initiallyChecked = $mark.is(':checked');
            if (initiallyChecked) {
                cy.wrap($mark).uncheck();
            }
            else {
                cy.wrap($mark).check();
            }
        });
        cy.get(`[data-testid="save-component-btn"][data-component-id="84"][data-peer-id="${peerGraderId}"]`, { timeout: 10000 }).click();
        cy.get('[data-testid="close-button"]:visible').click();
        cy.reload();
        cy.get('[data-testid="peer-info"]').should('be.visible');
        openEditPeerComponentsForm();
        cy.get(markSelector).first().should(($mark) => {
            expect($mark.is(':checked')).to.equal(!initiallyChecked);
        });
    });

    it('Instructor can delete a peer graders grade', () => {
        cy.visit(gradingUrl(conflictedAnonId));
        cy.get('[data-testid="peer-info-btn"]').click();
        cy.get('[data-testid="peer-info"]').should('be.visible');
        openEditPeerComponentsForm();
        const marksSelector = `[data-testid="peer-edit-mark"][data-peer-id="${peerGraderId}"]`;
        cy.get(marksSelector).should(($marks) => {
            expect([...$marks].some((mark) => mark.checked)).to.equal(true);
        });
        cy.get(`[data-testid="clear-peer-marks-btn"][data-peer-id="${peerGraderId}"]`).click();
        cy.get(marksSelector).should('not.be.checked');
    });
});
