describe('Test cases involving the files panel', () => {
    function assertSubmissionsBrowserClosed() {
        cy.get('#div_viewer_sd1').should('not.be.visible');
    }

    function assertSubmissionsBrowserOpen() {
        // have to increase timeout so that the file can be loaded properly in the CI
        cy.get('#div_viewer_sd1', { timeout: 20000 }).should('be.visible');
    }

    function assertResultsBrowserClosed() {
        cy.get('#div_viewer_rd1').should('not.be.visible');
    }

    function assertResultsBrowserOpen() {
        // we can't check that it is visible because in the CI it's possible
        // that autograding hasn't finished yet
        cy.get('#div_viewer_rd1', { timeout: 20000 }).should('exist');
    }

    beforeEach(() => {
        cy.visit(['sample', 'gradeable', 'grading_homework', 'grading', 'details']);
        cy.login('instructor');
        cy.get('[data-testid="view-sections"]').uncheck();
        cy.get('#details-table').contains('harvec').siblings().eq(6).click();
        cy.get('#submission_browser_btn').click();
    });

    it('test the open/close submissions and results buttons', () => {
        assertSubmissionsBrowserClosed();
        assertResultsBrowserClosed();
        cy.get('#toggleSubmissionButton').click(); // open submissions browser
        assertSubmissionsBrowserOpen();
        assertResultsBrowserClosed();
        cy.get('#toggleSubmissionButton').click(); // close submissions browser
        assertSubmissionsBrowserClosed();
        assertResultsBrowserClosed();

        cy.get('#toggleResultButton').click(); // open results browser
        assertSubmissionsBrowserClosed();
        assertResultsBrowserOpen();
        cy.get('#toggleResultButton').click(); // close results browser
        assertSubmissionsBrowserClosed();
        assertResultsBrowserClosed();
    });

    it('test the auto open checkbox', () => {
        cy.get('#autoscroll_id').click();
        cy.get('#autoscroll_id').should('be.checked');

        assertSubmissionsBrowserClosed();
        assertResultsBrowserClosed();
        cy.get('#submissions').click(); // open submissions browser
        cy.get('#results').click(); // open results browser
        assertSubmissionsBrowserOpen();
        assertResultsBrowserOpen();

        cy.get('#next-student').click();
        assertSubmissionsBrowserOpen();
        assertResultsBrowserOpen();

        cy.get('#submissions').click(); // close submissions browser
        assertSubmissionsBrowserClosed();
        assertResultsBrowserOpen();

        cy.get('#prev-student').click();
        assertSubmissionsBrowserClosed();
        assertResultsBrowserOpen();

        cy.get('#results').click(); // close results browser
        assertSubmissionsBrowserClosed();
        assertResultsBrowserClosed();

        cy.get('#next-student').click();
        assertSubmissionsBrowserClosed();
        assertResultsBrowserClosed();
    });

    it('test the full panel view', () => {
        // this test is somewhat limited in what it can do because the page
        // displays an iframe which is inaccessible to Cypress for the most part
        // We just test that the full panel view opens, and that an iframe appears.

        cy.get('#submissions').click();
        cy.get('#file_viewer_full_panel_iframe').should('not.exist');
        cy.get('i[title="Show file in full panel"]').first().click();
        cy.get('#grading_file_name').should('contain', '.submit.timestamp');
        cy.get('#file_viewer_full_panel_iframe').should('be.visible');

        cy.get('[aria-label="Collapse File"]').click();
        cy.get('#file_viewer_full_panel_iframe').should('not.exist');
    });
});
