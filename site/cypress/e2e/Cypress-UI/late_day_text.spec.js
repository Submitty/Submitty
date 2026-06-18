import { skipOn } from '@cypress/skip-test';

skipOn(Cypress.env('run_area') === 'CI', () => {
    describe('Testing the functionality of the late days page text', () => {
        const setFlatpickrDate = (selector, year, month = 0, day = null, hour = 23, minute = 59, second = 59) => {
            const dayToUse = day === null ? new Date().getDate() : day;
            cy.get(selector).then(($el) => {
                const fp = $el[0]._flatpickr;
                if (fp && typeof fp.setDate === 'function') {
                    fp.setDate(new Date(year, month, dayToUse, hour, minute, second), true);
                }
                else {
                    cy.wrap($el).clear();
                    const monthStr = String(month + 1).padStart(2, '0');
                    const dayStr = String(dayToUse).padStart(2, '0');
                    cy.wrap($el).type(`${year}-${monthStr}-${dayStr} 23:59:59`);
                }
            });
        };

        it('Should display 0 late days', () => {
            cy.login('bitdiddle');
            cy.visit(['sample', 'late_table']);
            cy.get('.late-days-remaining').should('contain.text', 'Late days remaining: 0');
        });

        it('Should update late days', () => {
            cy.logout();
            cy.login('instructor');
            cy.visit(['sample', 'late_days']);
            cy.get('#user_id').type('bitdiddle');
            setFlatpickrDate('#datestamp', 2025);
            cy.get('#user_id').click();
            cy.get('#late_days').type('10');
            cy.get('input[type="submit"]').click();
            cy.logout();
            cy.login('bitdiddle');
            cy.visit(['sample', 'late_table']);
            cy.get('.late-days-remaining').should('contain.text', 'Late days remaining: 10');
            cy.logout();
            cy.login('instructor');
            cy.visit(['sample', 'late_days']);
            cy.get('#user_id').type('bitdiddle');
            setFlatpickrDate('#datestamp', 2025);
            cy.get('#user_id').click();
            cy.get('#late_days').type('0');
            cy.get('input[type="submit"]').click();
        });
    });
});
