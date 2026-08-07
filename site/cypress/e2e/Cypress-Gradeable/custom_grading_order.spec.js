import { getCurrentSemester } from '/cypress/support/utils.js';

describe('Custom grading order for individual grading', () => {
    const semester = getCurrentSemester();
    const FIXTURE_PATH = 'cypress/fixtures/custom_grading_order';
    const DOWNLOAD_PATH = 'cypress/downloads';
    const GRADEABLE_ID = 'open_peer_homework'; 

    beforeEach(() => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', GRADEABLE_ID, 'update']);
        cy.on('window:confirm', () => true);
        cy.get('#page_3_nav').click();
    });

    before(() => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', GRADEABLE_ID, 'update']);
        cy.on('window:confirm', () => true);
        cy.get('#page_3_nav').click();
        cy.get('[data-testid="yes_enable_custom_sort"]').should('be.visible').check();
        cy.get('[data-testid="page-6-nav"]').should('be.visible').click();

        cy.get('body').then(($body) => {
            if ($body.find('[data-testid="clear-custom-sort"]:visible').length) {
                cy.get('[data-testid="clear-custom-sort"]').click();
            }
        });
    });

    
    it('Toggle the Custom Grading Order Tab visibility', () => {
        cy.get('[data-testid="no_enable_custom_sort"]').check();
        cy.get('[data-testid="page-6-nav"]').should('not.be.visible');
        cy.get('[data-testid="yes_enable_custom_sort"]').check();
        cy.get('[data-testid="page-6-nav"]').should('be.visible').click();
        cy.get('[data-testid="custom-sort-control"]').should('be.visible');
        cy.get('[data-testid="custom-sort-status"]').should('contain.text','disabled'); 
    }); 

    it('Download the current grading order as a CSV unmodified', () => {
        cy.get('[data-testid="yes_enable_custom_sort"]').check();
        cy.get('[data-testid="page-6-nav"]').should('be.visible').click();

        cy.get('[data-testid="download-custom-sort-csv"]').click();

        const downloadedFile = `${DOWNLOAD_PATH}/${GRADEABLE_ID}_custom_sort.csv`;
        cy.readFile(downloadedFile).should('exist');
    });

    it('Upload the current grading order as a CSV unmodified', () => {
        cy.intercept('POST', '**/custom_sort/csv').as('uploadCsv');

        cy.get('[data-testid="yes_enable_custom_sort"]').check();
        cy.get('[data-testid="page-6-nav"]').should('be.visible').click();

        cy.get('[data-testid="download-custom-sort-csv"]').click();

        const downloadedFile = `${DOWNLOAD_PATH}/${GRADEABLE_ID}_custom_sort.csv`;
        cy.readFile(downloadedFile).should('exist');

        cy.get('[data-testid="custom-sort-csv-upload-button"]').click();
        cy.get('[data-testid="custom-sort-csv-upload-file"]').selectFile(downloadedFile, { force: true });
        cy.get('[data-testid="custom-sort-csv-upload-submit"]').click();
        cy.wait('@uploadCsv').then((interception) => {
            const body = typeof interception.response.body === 'string'
                ? JSON.parse(interception.response.body)
                : interception.response.body;

            expect(interception.response.statusCode).to.eq(200);
            expect(body.status).to.eq('success');
        });

        cy.reload();
        cy.get('[data-testid="custom-sort-status"]').should('contain.text', 'enabled');
    });


    it('Upload CSV should provide error message due to duplicate userID',() => {
        const duplicateFile = `${FIXTURE_PATH}/duplicate.csv`;
        cy.intercept('POST', '**/custom_sort/csv').as('uploadCsv');

        cy.get('[data-testid="yes_enable_custom_sort"]').check();
        cy.get('[data-testid="page-6-nav"]').should('be.visible').click();

        cy.get('[data-testid="custom-sort-csv-upload-button"]').click();
        cy.get('[data-testid="custom-sort-csv-upload-file"]').selectFile(duplicateFile, { force: true });
        cy.get('[data-testid="custom-sort-csv-upload-submit"]').click();

        cy.wait('@uploadCsv').then((interception) => {
            const body = typeof interception.response.body === 'string'
                ? JSON.parse(interception.response.body)
                : interception.response.body;

            expect(interception.response.statusCode).to.eq(200);
            expect(body.status).to.eq('error');
            expect(body.message).to.contain('Duplicate');
        });
    });

    it('Upload CSV should provide an error message due to typo in Header', () => {
        const typoFile = `${FIXTURE_PATH}/invalid_header.csv`;

        cy.intercept('POST', '**/custom_sort/csv').as('uploadCsv');

        cy.get('[data-testid="yes_enable_custom_sort"]').check();
        cy.get('[data-testid="page-6-nav"]').should('be.visible').click();


        cy.get('[data-testid="custom-sort-csv-upload-button"]').click();
        cy.get('[data-testid="custom-sort-csv-upload-file"]').selectFile(typoFile, { force: true });
        cy.get('[data-testid="custom-sort-csv-upload-submit"]').click();

        cy.wait('@uploadCsv').then((interception) => {
            const body = typeof interception.response.body === 'string'
                ? JSON.parse(interception.response.body)
                : interception.response.body;

            expect(interception.response.statusCode).to.eq(200);
            expect(body.status).to.eq('error');
             expect(body.message).to.contain('User ID');
        });        
    });

    it('Appends a missing user to the bottom of the custom order', () => {
        const missingFile = `${FIXTURE_PATH}/missing_student.csv`;
        cy.intercept('POST', '**/custom_sort/csv').as('uploadCsv');
        cy.get('[data-testid="yes_enable_custom_sort"]').check();
        cy.get('[data-testid="page-6-nav"]').should('be.visible').click();
        cy.get('[data-testid="custom-sort-csv-upload-button"]').click();
        cy.get('[data-testid="custom-sort-csv-upload-file"]').selectFile(missingFile, { force: true });
        cy.get('[data-testid="custom-sort-csv-upload-submit"]').click();

        cy.wait('@uploadCsv').then((interception) => {
            const body =
                typeof interception.response.body === 'string'
                    ? JSON.parse(interception.response.body)
                    : interception.response.body;

            expect(interception.response.statusCode).to.eq(200);
            expect(body.status).to.eq('success');
        });

        cy.reload();

        cy.get('[data-testid="custom-sort-status"]').should('contain.text', 'enabled');

        const missingUserId = 'adamsg';

        cy.visit(['sample', 'gradeable', GRADEABLE_ID, 'grading', 'details']);
        cy.get('#toggle-view-sections').uncheck({ force: true });
        cy.get('[data-testid="details-section-rows"][data-section-id="1"]').find('tr').last().should('contain.text', missingUserId);
    });

    it('Reorders User IDs on the Custom Grading Order and Grade Details pages', () => {
        const reorderFile = `${FIXTURE_PATH}/reordered.csv`;

        cy.intercept('POST', '**/custom_sort/csv').as('uploadCsv');

        cy.get('[data-testid="yes_enable_custom_sort"]').check();
        cy.get('[data-testid="page-6-nav"]').should('be.visible').click();

        cy.get('[data-testid="custom-sort-csv-upload-button"]').click();
        cy.get('[data-testid="custom-sort-csv-upload-file"]').selectFile(reorderFile, { force: true });
        cy.get('[data-testid="custom-sort-csv-upload-submit"]').click();

        cy.wait('@uploadCsv').then((interception) => {
            const body
                = typeof interception.response.body === 'string'
                    ? JSON.parse(interception.response.body)
                    : interception.response.body;

            expect(interception.response.statusCode).to.eq(200);
            expect(body.status).to.eq('success');
        });

        cy.reload();

        // Verify the uploaded order on the Custom Grading Order page.
        cy.get('[data-testid="custom-sort-control"]').find('tbody tr').eq(0).should('contain.text', 'aphacker');

        cy.get('[data-testid="custom-sort-control"]').find('tbody tr').eq(1).should('contain.text', 'abernl');

        cy.get('[data-testid="custom-sort-control"]').find('tbody tr').eq(2).should('contain.text', 'adamsg');

        // Verify the same order on the Grade Details page.
        cy.visit(['sample', 'gradeable', GRADEABLE_ID, 'grading', 'details']);

        cy.get('#toggle-view-sections').uncheck({ force: true });

        cy.get('[data-testid="details-section-rows"][data-section-id="1"]').find('tr').eq(0).should('contain.text', 'aphacker');

        cy.get('[data-testid="details-section-rows"][data-section-id="1"]').find('tr').eq(1).should('contain.text', 'adamsg');
    });

    it('Reverts the custom grading order back to the default order', () => {
        const reorderFile = `${FIXTURE_PATH}/reordered.csv`;

        cy.intercept('POST', '**/custom_sort/csv').as('uploadCsv');
        cy.intercept('POST', '**/custom_sort/clear').as('clearCustomSort');

        cy.get('[data-testid="yes_enable_custom_sort"]').check();
        cy.get('[data-testid="page-6-nav"]').should('be.visible').click();

        cy.get('[data-testid="custom-sort-csv-upload-button"]').click();
        cy.get('[data-testid="custom-sort-csv-upload-file"]').selectFile(reorderFile, { force: true });
        cy.get('[data-testid="custom-sort-csv-upload-submit"]').click();

        cy.wait('@uploadCsv').then((interception) => {
            const body
                = typeof interception.response.body === 'string'
                    ? JSON.parse(interception.response.body)
                    : interception.response.body;

            expect(interception.response.statusCode).to.eq(200);
            expect(body.status).to.eq('success');
        });

        cy.reload();

        cy.get('[data-testid="custom-sort-control"]').find('tbody tr').eq(0).should('contain.text', 'aphacker');

        cy.get('[data-testid="custom-sort-control"]').find('tbody tr').eq(1).should('contain.text', 'abernl');

        cy.get('[data-testid="custom-sort-control"]').find('tbody tr').eq(2).should('contain.text', 'adamsg');

        cy.visit(['sample', 'gradeable', GRADEABLE_ID, 'grading', 'details']);

        cy.get('#toggle-view-sections').uncheck({ force: true });

        cy.get('[data-testid="details-section-rows"][data-section-id="1"]').find('tr').eq(0).should('contain.text', 'aphacker');

        cy.get('[data-testid="details-section-rows"][data-section-id="1"]').find('tr').eq(1).should('contain.text', 'adamsg');

        cy.visit(['sample', 'gradeable', GRADEABLE_ID, 'update']);
        cy.get('#page_3_nav').click();
        cy.get('[data-testid="page-6-nav"]').should('be.visible').click();

        cy.get('[data-testid="clear-custom-sort"]').click();

        cy.wait('@clearCustomSort').its('response.statusCode').should('eq', 200);

        cy.reload();

        cy.get('[data-testid="custom-sort-status"]').should('contain.text', 'disabled');

        cy.get('[data-testid="custom-sort-control"]').find('tbody tr').eq(0).should('contain.text', 'adamsg');

        cy.get('[data-testid="custom-sort-control"]').find('tbody tr').eq(1).should('contain.text', 'aphacker');
        cy.visit(['sample', 'gradeable', GRADEABLE_ID, 'grading', 'details']);

        cy.get('#toggle-view-sections').uncheck({ force: true });

        cy.get('[data-testid="details-section-rows"][data-section-id="1"]').find('tr').eq(0).should('contain.text', 'adamsg');

        cy.get('[data-testid="details-section-rows"][data-section-id="1"]').find('tr').eq(1).should('contain.text', 'aphacker');
    });

    after(() => {  
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', GRADEABLE_ID, 'update']);
        cy.on('window:confirm', () => true);
        cy.get('#page_3_nav').click();
        cy.get('[data-testid="yes_enable_custom_sort"]').should('be.visible').check();
        cy.get('[data-testid="page-6-nav"]').should('be.visible').click();

        cy.get('body').then(($body) => {
            if ($body.find('[data-testid="clear-custom-sort"]:visible').length) {
                cy.get('[data-testid="clear-custom-sort"]').click();
                }
        });
    });
});

describe('Custom grading order for team grading', () => {
    const TEAM_GRADEABLE_ID = 'closed_peer_team_homework';

    beforeEach(() => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', TEAM_GRADEABLE_ID, 'update']);
        cy.on('window:confirm', () => true);
        cy.get('#page_3_nav').click();
    });

    before(() => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', TEAM_GRADEABLE_ID, 'update']);
        cy.on('window:confirm', () => true);
        cy.get('#page_3_nav').click();
        cy.get('[data-testid="yes_enable_custom_sort"]').should('be.visible').check();
        cy.get('[data-testid="page-6-nav"]').should('be.visible').click();

        cy.get('body').then(($body) => {
            if ($body.find('[data-testid="clear-custom-sort"]:visible').length) {
                cy.get('[data-testid="clear-custom-sort"]').click();
            }
        });
    });

    it('Toggle the Custom Grading Order Tab visibility', () => {
        cy.get('[data-testid="no_enable_custom_sort"]').check();

        cy.get('[data-testid="page-6-nav"]').should('not.be.visible');

        cy.get('[data-testid="yes_enable_custom_sort"]').check();

        cy.get('[data-testid="page-6-nav"]').should('be.visible').click();

        cy.get('[data-testid="custom-sort-control"]').should('be.visible');

        cy.get('[data-testid="custom-sort-status"]').should('contain.text', 'disabled');
    });

    it('Reorders Team IDs on the Custom Grading Order and Grade Details pages', () => {
        const reorderFile = 'cypress/fixtures/custom_grading_order/team_reordered.csv';

        cy.intercept('POST', '**/custom_sort/csv').as('uploadCsv');

        cy.get('[data-testid="yes_enable_custom_sort"]').check();
        cy.get('[data-testid="page-6-nav"]').should('be.visible').click();

        cy.get('[data-testid="custom-sort-csv-upload-button"]').click();

        cy.get('[data-testid="custom-sort-csv-upload-file"]').selectFile(reorderFile, { force: true });

        cy.get('[data-testid="custom-sort-csv-upload-submit"]').click();

        cy.wait('@uploadCsv').then((interception) => {
            const body
                = typeof interception.response.body === 'string'
                    ? JSON.parse(interception.response.body)
                    : interception.response.body;

            expect(interception.response.statusCode).to.eq(200);
            expect(body.status).to.eq('success');
        });

        cy.reload();

        cy.get('[data-testid="custom-sort-control"]').find('tbody tr').eq(0).should('contain.text', '00007_bitdiddle');

        cy.get('[data-testid="custom-sort-control"]').find('tbody tr').eq(1).should('contain.text', '00001_adamsg');

        cy.get('[data-testid="custom-sort-control"]').find('tbody tr').eq(2).should('contain.text', '00000_abernl');

        cy.visit(['sample', 'gradeable', TEAM_GRADEABLE_ID, 'grading', 'details']);

        cy.get('#toggle-view-sections').uncheck({ force: true });

        cy.get('[data-testid="details-section-rows"][data-section-id="1"]').find('tr').eq(0).should('contain.text', '00007_bitdiddle');

        cy.get('[data-testid="details-section-rows"][data-section-id="1"]').find('tr').eq(1).should('contain.text', '00001_adamsg');
    });
    it('Reverts the team custom grading order back to the default order', () => {
        const reorderFile = 'cypress/fixtures/custom_grading_order/team_reordered.csv';

        cy.intercept('POST', '**/custom_sort/csv').as('uploadCsv');
        cy.intercept('POST', '**/custom_sort/clear').as('clearCustomSort');

        cy.get('[data-testid="yes_enable_custom_sort"]').check();
        cy.get('[data-testid="page-6-nav"]').should('be.visible').click();

        cy.get('[data-testid="custom-sort-csv-upload-button"]').click();

        cy.get('[data-testid="custom-sort-csv-upload-file"]').selectFile(reorderFile, { force: true });

        cy.get('[data-testid="custom-sort-csv-upload-submit"]').click();

        cy.wait('@uploadCsv').then((interception) => {
            const body
                = typeof interception.response.body === 'string'
                    ? JSON.parse(interception.response.body)
                    : interception.response.body;

            expect(interception.response.statusCode).to.eq(200);
            expect(body.status).to.eq('success');
        });

        cy.reload();

        cy.get('[data-testid="custom-sort-control"]').find('tbody tr').eq(0).should('contain.text', '00007_bitdiddle');

        cy.get('[data-testid="custom-sort-control"]').find('tbody tr').eq(1).should('contain.text', '00001_adamsg');

        cy.visit(['sample', 'gradeable', TEAM_GRADEABLE_ID, 'grading', 'details']);

        cy.get('#toggle-view-sections').uncheck({ force: true });

        cy.get('[data-testid="details-section-rows"][data-section-id="1"]').find('tr').eq(0).should('contain.text', '00007_bitdiddle');

        cy.get('[data-testid="details-section-rows"][data-section-id="1"]').find('tr').eq(1).should('contain.text', '00001_adamsg');

        cy.visit(['sample', 'gradeable', TEAM_GRADEABLE_ID, 'update']);
        cy.get('#page_3_nav').click();

        cy.get('[data-testid="page-6-nav"]').should('be.visible').click();

        cy.get('[data-testid="clear-custom-sort"]').click();

        cy.wait('@clearCustomSort').its('response.statusCode').should('eq', 200);

        cy.reload();

        cy.get('[data-testid="custom-sort-status"]').should('contain.text', 'disabled');

        cy.get('[data-testid="custom-sort-control"]').find('tbody tr').eq(0).should('contain.text', '00001_adamsg');

        cy.get('[data-testid="custom-sort-control"]').find('tbody tr').eq(1).should('contain.text', '00007_bitdiddle');

        cy.visit(['sample', 'gradeable', TEAM_GRADEABLE_ID, 'grading', 'details']);

        cy.get('#toggle-view-sections').uncheck({ force: true });

        cy.get('[data-testid="details-section-rows"][data-section-id="1"]').find('tr').eq(0).should('contain.text', '00001_adamsg');

        cy.get('[data-testid="details-section-rows"][data-section-id="1"]').find('tr').eq(1).should('contain.text', '00007_bitdiddle');
    });

    after(() => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', TEAM_GRADEABLE_ID, 'update']);
        cy.on('window:confirm', () => true);
        cy.get('#page_3_nav').click();
        cy.get('[data-testid="yes_enable_custom_sort"]').should('be.visible').check();
        cy.get('[data-testid="page-6-nav"]').should('be.visible').click();

        cy.get('body').then(($body) => {
            if ($body.find('[data-testid="clear-custom-sort"]:visible').length) {
                cy.get('[data-testid="clear-custom-sort"]').click();
            }
        });
    });
});
