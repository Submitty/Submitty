describe('TA grading hotkey testing', () => {
    after(() => {
        cy.login();
        cy.visit(['sample', 'gradeable', 'grading_homework', 'update?nav_tab=2']);
        cy.get('[data-testid="peer-component-container"]').should('exist');
        cy.get('[data-testid="peer-component-container"]').find('[data-testid="delete-gradable-component"]').click();
        cy.get('[data-testid="peer-component-container"]').should('not.exist');
        cy.logout();
    });

    it('toggle keyboard shortcut', () => {
        cy.login();
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);
        cy.get('[data-testid="view-sections"]').click();
        cy.get('[data-testid="grade-button"]').eq(12).click();
        cy.get('body').type('{A}');
        cy.get('[data-testid="autograding-results"]').should('contain', 'Autograding Testcases');
        cy.get('body').type('{G}');
        cy.get('[data-testid="grading-rubric"]').should('contain', 'Grading Rubric');
        cy.get('#edit-mode-enabled').should('not.be.checked');
        cy.get('body').type('{E}');
        cy.get('#edit-mode-enabled').should('be.checked');
        cy.get('body').type('{O}');
        cy.get('[data-testid="submission-browser"]').should('contain', 'Submissions and Results Browser');
        cy.get('body').type('{S}');
        cy.get('[data-testid="student-info"]').should('contain', 'Student Information');
        cy.get('body').type('{X}');
        cy.get('[data-testid="grade-inquiry-inner-info"]').should('contain', 'Grade Inquiry');
        cy.get('body').type('{T}');
        cy.get('[data-testid="solution-ta-notes"]').should('contain', 'Solution/TA Notes');
    });

    it('testing discussion, peer and notebook', () => {
        cy.login();
        cy.visit(['sample', 'config']);
        cy.get('[data-testid="forum-enabled"]').should('be.checked');
        cy.visit(['sample', 'gradeable', 'grading_homework', 'update']);
        // adding the peer grading panel, discussion panel, notebook panel
        cy.get('[data-testid="yes-discussion"]').check();
        cy.get('[data-testid="yes-discussion"]').should('be.checked');
        cy.get('[data-testid="page-2-nav"]').click();
        cy.get('[data-testid="add-new-peer-component"]').click();
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);
        cy.get('[data-testid="grade-button"]').eq(12).click();
        cy.get('body').type('{P}');
        cy.get('[data-testid="peer-info"]').should('contain', 'Peer Grading');
        cy.get('body').type('{D}');
        cy.get('[data-testid="posts-list"]').should('contain', 'Discussion Posts');
    });

    it('allows user to remove hotkey', () => {
        cy.login();
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);
        cy.get('[data-testid="view-sections"]').click();
        cy.get('[data-testid="grade-button"]').eq(12).click();
        cy.get('[data-testid="grading-setting-btn"]').click();
        cy.get('[data-testid="remap-unset-0"]').click();
        cy.get('[data-testid="remap-0"]').should('contain', 'Unassigned');
        cy.get('[data-testid="remove-all-hotkeys"]').click();
        cy.get('[data-testid="remap-1"]').should('contain', 'Unassigned');
        cy.get('[data-testid="remap-2"]').should('contain', 'Unassigned');
        cy.get('[data-testid="restore-all-hotkeys"]').click();
        cy.get('[data-testid="remap-0"]').should('contain', 'ArrowLeft');
        cy.get('[data-testid="remap-1"]').should('contain', 'ArrowRight');
        cy.get('[data-testid="remap-2"]').should('contain', 'KeyA');
    });
});
