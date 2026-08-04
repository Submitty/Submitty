describe('Should be able to apply peer grader file restrictions', () => {
    const gradeableId = 'grading_pdf_peer_homework';
    const visitGraderAssignment = () => {
        cy.visit(`/courses/f26/sample/gradeable/${gradeableId}/update`);
        cy.get('[data-testid="grader-assignment-tab"]').should('be.visible').click();
        cy.get('[data-testid="peer-file-restriction-container"]').should('be.visible');
    };
    const enableFileRestrictions = () => {
        cy.get('[data-testid="peer-files-panel-option"]').should('be.checked');
        cy.get('[data-testid="peer-files-restricted"]').check();
        cy.get('[data-testid="peer-file-pattern-controls"]').should('be.visible');
    };
    const deleteAllPatterns = () => {
        cy.get('body').then(($body) => {
            const deleteSelector = '[data-testid="delete-peer-file-pattern"]';
            if ($body.find(deleteSelector).length > 0) {
                cy.get(deleteSelector).each(($button) => {
                    cy.wrap($button).click();
                });
            }
        });
        cy.get('[data-testid="peer-file-pattern-row"]').should('not.exist');
    };
    const addPattern = (pattern) => {
        cy.get('[data-testid="peer-file-pattern-input"]').clear();
        cy.get('[data-testid="peer-file-pattern-input"]').type(pattern);
        cy.get('[data-testid="add-peer-file-pattern"]').click();
        cy.get('[data-testid="peer-file-pattern-row"]').contains('[data-testid="peer-file-pattern-value"]', pattern).should('exist');
    };
    const openFirstAssignedPeerSubmission = () => {
        cy.get('[data-testid="grade-button"]').first().click();
        cy.get('[data-testid="submission-browser"]', { timeout: 10000 }).should('be.visible');
    };
    beforeEach(() => {
        cy.login('instructor');
    });
    afterEach(() => {
        cy.visit('/');
        cy.logout();
    });
    it('saves and deletes peer file regular expressions', () => {
        visitGraderAssignment();
        enableFileRestrictions();
        deleteAllPatterns();
        addPattern('/\\.pdf$/');
        cy.get('[data-testid="peer-files-restricted"]').should('be.checked');
        cy.get('[data-testid="peer-file-pattern-row"]').should('have.length', 1);
        cy.get('[data-testid="peer-file-pattern-value"]').should('have.text', '/\\.pdf$/');
        cy.get('[data-testid="delete-peer-file-pattern"]').click();
        cy.get('[data-testid="peer-file-pattern-row"]').should('not.exist');
        cy.get('[data-testid="peer-file-pattern-empty-row"]').should('exist');
        cy.get('[data-testid="peer-file-pattern-row"]').should('not.exist');
    });
    it('shows only whitelisted files to a student peer grader', () => {
        visitGraderAssignment();
        enableFileRestrictions();
        addPattern('/\\.pdf$/');
        cy.visit('/');
        cy.logout();
        cy.login('student');
        cy.visit(`/courses/f26/sample/gradeable/${gradeableId}/grading/details`);
        cy.get('[data-testid="popup-save-button"]').click();
        cy.get('[data-testid="grade-button"]').first().click();
        cy.get('[data-testid="show-submission"]', { timeout: 10000 }).click();
        cy.get('[data-testid="submission-browser"]').should('be.visible');
        cy.get('[data-testid="submission-browser-file"]').should('exist').each(($file) => {
            const fileName = $file.attr('data-file-name');
            expect(fileName).to.match(/\.pdf$/);
        });
        cy.get('[data-testid="submission-browser-file"]' + '[data-file-name="user_assignment_settings.json"]').should('not.exist');
        cy.get('[data-testid="submission-browser-folder"]' + '[data-folder-name="submissions_processed"]').should('not.exist');
        cy.get('[data-testid="no-matching-peer-files-warning"]').should('not.exist');
    });
    it('shows a warning when no submitted files match', () => {
        visitGraderAssignment();
        enableFileRestrictions();
        deleteAllPatterns();
        addPattern('/^file-that-does-not-exist\\.txt$/');
        cy.visit('/');
        cy.logout();
        cy.login('student');
        cy.visit(`/courses/f26/sample/gradeable/${gradeableId}/grading/details`);
        cy.get('[data-testid="popup-save-button"]').click();
        cy.get('[data-testid="grade-button"]').first().click();
        cy.get('[data-testid="show-submission"]', { timeout: 10000 }).click();
        cy.get('[data-testid="submission-browser"]').should('be.visible');
        cy.get('[data-testid="no-matching-peer-files-warning"]').should('be.visible')
            .and(
                'contain.text',
                'has not submitted any files matching',
            );
        cy.get('[data-testid="submission-browser-folder"]' + '[data-folder-name="submissions"]').should('not.exist');
        cy.get('[data-testid="submission-browser-folder"]' + '[data-folder-name="submissions_processed"]').should('not.exist');
        cy.get('[data-testid="submission-browser-file"]' + '[data-file-name="user_assignment_settings.json"]').should('not.exist');
    });
    it('does not filter files when restrictions are disabled', () => {
        visitGraderAssignment();
        cy.get('[data-testid="peer-files-restricted"]').uncheck();
        cy.get('[data-testid="peer-file-pattern-controls"]').should('not.be.visible');
        cy.get('[data-testid="save-status"]', { timeout: 10000 }).should('contain.text', 'All Changes Saved');
        cy.reload();
        cy.get('[data-testid="grader-assignment-tab"]').click();
        cy.get('[data-testid="peer-files-restricted"]').should('not.be.checked');
        cy.visit('/');
        cy.logout();
        cy.login('student');
        cy.visit(`/courses/f26/sample/gradeable/${gradeableId}/grading/details`);
        cy.get('[data-testid="popup-save-button"]').click();
        cy.get('[data-testid="grade-button"]').first().click();
        cy.get('[data-testid="show-submission"]', { timeout: 10000 }).click();
        cy.get('[data-testid="submission-browser"]').should('be.visible');
        cy.get('[data-testid="no-matching-peer-files-warning"]').should('not.exist');
        cy.get('[data-testid="submission-browser-file"]').should('exist');
    });
});
