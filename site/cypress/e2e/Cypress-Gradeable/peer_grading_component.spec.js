describe('Peer Grading Component testing', () => {
    after(() => {
        cy.login();
        ['grading_homework', 'grading_homework_pdf'].forEach((option) => {
            cy.visit(['sample', 'gradeable', option, 'update?nav_tab=2']);
            cy.get('[data-testid="peer-component-container"]').should('exist');
            cy.get('[data-testid="peer-component-container"]').find('[data-testid="delete-gradable-component"]').click();
            cy.get('[data-testid="peer-component-container"]').should('not.exist');
        });
        cy.logout();
    });
    it('Test peer grading components', () => {
        cy.login();
        ['grading_homework', 'grading_homework_pdf'].forEach((option) => {
            cy.visit(['sample', 'gradeable', option, 'update?nav_tab=2']);
            cy.get('[data-testid="add-new-peer-component"]').click();
            cy.get('[data-testid="peer-component-title"]').eq(4).clear();
            cy.get('[data-testid="peer-component-title"]').eq(4).type('Peer Grading Component');
            cy.get('[data-testid="ta-comment-box"]').eq(4).type('Note to TAs');
            cy.get('[data-testid="student-comment-box"]').eq(4).type('Note to Students');
            cy.get('[data-testid="max-points-box"]').eq(4).clear();
            cy.get('[data-testid="max-points-box"]').eq(4).type(5);
            cy.get('[data-testid="add-new-mark-button"]').eq(4).should('exist');
            cy.get('[data-testid="grade-by-count-up-option"]').eq(4).should('contain', 'Grade by Count Up (from zero)');
            cy.get('[data-testid="grade-by-count-down-option"]').eq(4).should('contain', 'Grade by Count Down (from Points)');
            cy.get('.save-tools-save').click();
            cy.visit(['sample', 'gradeable', option, 'update?nav_tab=4']); // peer matrix section
            cy.get('[data-testid="peer-control"]').should('contain', 'Options for Peer Matrix');
            cy.get('[data-testid="all-grade"]').should('contain', 'All Grade All');
            cy.get('[data-testid="submit-before-grading"]').should('contain', 'Submit Before Grading');
            cy.get('[data-testid="restrict-registration-sections"]').should('contain', 'Restrict to Registration Sections');
            cy.get('[data-testid="number-to-peer-grade"]').should('exist');
            cy.get('[data-testid="add-peer-grader"]').should('exist');
            cy.get('[data-testid="clear-peer-matrix"]').should('exist');
            cy.get('[data-testid="download-peer-csv"]').should('exist');
            if (option === 'grading_homework') {
                cy.get('[data-testid="add-peer-grader"]').click();
                cy.get('[data-testid="new-peer-grader"]').click();
                cy.get('[data-testid="new-peer-grader"]').type('student');
                cy.get('[data-testid="add-user-id-0"]').click();
                cy.get('[data-testid="add-user-id-0"]').type('aphacker');
                cy.get('[data-testid="add-more-users"]').should('contain', 'Add More Users');
                cy.get('[data-testid="new-peer-grader"]').click();
                cy.get('[data-testid="admin-gradeable-add-peers-submit"]').click();
            }
            if (option === 'grading_homework_pdf') {
                cy.get('[data-testid="upload-peer-graders-list"]').selectFile('cypress/fixtures/peer_assign_file.csv');
                cy.get('[data-testid="table-wrapper"]').should('contain', 'aphacker');
            }
        });
        cy.visit('/');
        cy.logout();
        cy.login('student');
        ['grading_homework', 'grading_homework_pdf'].forEach((option) => {
            cy.visit(['sample', 'gradeable', option, 'grading', 'details']);
            cy.get('[data-testid="popup-window"]').should('exist');
            cy.get('[data-testid="agree-popup-btn"]').click();
            cy.get('[data-testid="grade-button"]').eq(0).click();
            cy.get('body').type('{G}');
            cy.get('[data-testid="grading-rubric-label"]').should('contain', 'Grading Rubric');
        });
    });
});
