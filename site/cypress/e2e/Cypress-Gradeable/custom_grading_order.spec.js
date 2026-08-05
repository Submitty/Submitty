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
        cy.get('[data-testid="custom-sort-status"]', { timeout: 10000 }).should('contain.text', 'enabled');
 
    });


    //valid upload
    //duplicate
    //typo
    //missing
    //revert back to normal order

//describe('Custom grading order for team gradign', () => {
    //enable 
    // test valid upload
    // confirm order

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