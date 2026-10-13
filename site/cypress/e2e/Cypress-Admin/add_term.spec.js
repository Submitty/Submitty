import { getFullCurrentSemester } from '../../support/utils.js';

describe('Test cases for the Add New Term form on the course creation page', () => {
    beforeEach(() => {
        cy.login('superuser');
        cy.visit('/home/courses/new');
        cy.get('[onclick="addNewTerm()"]').click();
        cy.get('#add-new-term').should('be.visible');
    });

    it('Should show the existing terms table with the correct headers and legend', () => {
        cy.get('#add-new-term').within(() => {
            cy.contains('.option-title', 'Existing Terms').should('be.visible');

            cy.get('table.table thead th').should('have.length', 4);
            cy.get('table.table thead th').eq(0).should('contain', 'Term');
            cy.get('table.table thead th').eq(1).should('contain', 'Start Date');
            cy.get('table.table thead th').eq(2).should('contain', 'End Date');
            cy.get('table.table thead th').eq(3).should('contain', 'Status');

            cy.get('.term-legend-item').should('have.length', 3);
        });
    });

    it('Should mark the current term Active in the table', () => {
        cy.get('#add-new-term').within(() => {
            cy.contains('tbody tr', getFullCurrentSemester())
                .should('have.class', 'term-row-active')
                .and('contain', 'Active')
                .find('.term-status-dot')
                .should('have.class', 'term-status-active');
        });
    });

    it('Should flag an invalid date range', () => {
        cy.get('#start-date').type('2026-02-14');
        cy.get('#end-date').type('2026-01-15');
        cy.get('#end-date').blur();
        cy.get('#term-length-msg').should('contain', 'End date is before start date');
    });

    it('Should flag a term longer than 360 days', () => {
        cy.get('#start-date').type('2026-01-01');
        cy.get('#end-date').type('2027-01-01');
        cy.get('#end-date').blur();
        cy.get('#term-length-msg').should('contain', 'exceeds the 360 day maximum');
    });

    it('Should create a new term and reflect it, correctly sorted, in the existing terms table', () => {
        const termId = `cy${Date.now().toString(36)}`;
        const termName = `Cypress Old Term ${Date.now()}`;

        cy.get('#term-id').type(termId);
        cy.get('#term-name').type(termName);
        cy.get('#start-date').type('2000-01-01');
        cy.get('#end-date').type('2000-06-01');

        cy.waitPageChange(() => {
            cy.get('#add-term-submit').click();
        });

        cy.get('[data-testid="popup-message"]').should('contain', 'Term added successfully');

        cy.get('[onclick="addNewTerm()"]').click();
        cy.get('#add-new-term').within(() => {
            cy.contains('tbody tr', termName)
                .should('have.class', 'term-row-ended')
                .and('contain', 'Ended');

            // A term from the year 2000 should sort below the current (active) term.
            cy.get('tbody tr').then(($rows) => {
                const rowTexts = [...$rows].map((row) => row.textContent);
                const activeIndex = rowTexts.findIndex((text) => text.includes(getFullCurrentSemester()));
                const oldIndex = rowTexts.findIndex((text) => text.includes(termName));
                expect(activeIndex).to.be.lessThan(oldIndex);
            });
        });
    });
});
