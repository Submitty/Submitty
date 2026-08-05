import { getCurrentSemester } from '/cypress/support/utils.js';

describe('Custom grading order for individual grading', () => {
    const semester = getCurrentSemester
    const FIXTURE_PATH = 'cypress/fixtures/custom_grading_order';
    const DOWNLOAD_PATH = 'cypress/downloads';
    const GRADEABLE_ID = 'open_peer_homework'; 

    beforeEach(() => {
        cy.login('Instructor');
        cy.visit(['sample', 'gradeable', GRADEABLE_ID, 'update']);
        cy.get('#page_3_nav').click();
        cy.on('window:confirm', () => true);
    });

    
    it('Toggle the Custom Grading Order Tab visibility', () => {
        cy.get('[data-testid="page-6-nav"]').should('not.be.visible');
        cy.get('#yes_enable_custom.sort').check(); //Need to change to testid
        cy.get('[data-testid="page-6-nav"]').should('not.be.visible').click();
        cy.get('[data-testid="custom-sort-status"]').should('contain.text', 'disabled');
    }); 

    //toggle
    //download
    //valid upload
    //duplicate
    //typo
    //missing
    //revert back to normal order
});

describe('Custom grading order for team gradign', () => {
    //enable 
    // test valid upload
    // confirm order
});