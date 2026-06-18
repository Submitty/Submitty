import { skipOn } from '@cypress/skip-test';

skipOn(Cypress.env('run_area') === 'CI', () => {
    describe('Testing functionality of Autograder Results', () => {
        beforeEach(() => {
            cy.visit(['sample', 'gradeable']);
        });

        // Function to consistently set flatpickr dates
        const setFlatpickrDate = (
            selector,
            year,
            month = 0,
            day = 1,
            hour = 23,
            minute = 59,
            second = 59,
        ) => {
            cy.get(selector).then(($el) => {
                const fp = $el[0]._flatpickr;
                if (fp && typeof fp.setDate === 'function') {
                    fp.setDate(new Date(year, month, day, hour, minute, second), true);
                }
                else {
                    cy.wrap($el).clear();
                    cy.wrap($el).type(`${year}-01-01 23:59:59`);
                }
            });
        };

        it('Should set the due date to the future and verify hidden testcase has no show button', () => {
            // Set dates forward as instructor
            cy.login('instructor');
            cy.visit(['sample', 'gradeable', 'grades_released_homework_autohiddenEC', 'update']);
            cy.get('#page_5_nav').click();

            setFlatpickrDate('#date_due', 2100);
            setFlatpickrDate('#date_grade', 2100);
            setFlatpickrDate('#date_grade_due', 2100);
            setFlatpickrDate('#date_released', 2100);

            // As student, open gradeable and verify behavior
            cy.login('bitdiddle');
            cy.visit(['sample', 'gradeable', 'grades_released_homework_autohiddenEC']);
            cy.scrollTo('bottom');
            // Hidden testcase (4) should not have a "show details" control
            cy.get('#details_tc_4').should('not.exist');
        });

        it('Should set due date back to normal and verify hidden testcase can be shown', () => {
            // Reset dates as instructor
            cy.login('instructor');
            cy.visit(['sample', 'gradeable', 'grades_released_homework_autohiddenEC', 'update']);
            cy.get('#page_5_nav').click();
            setFlatpickrDate('#date_due', 1972);
            setFlatpickrDate('#date_grade', 1973);
            setFlatpickrDate('#date_grade_due', 1974);
            setFlatpickrDate('#date_released', 1974);
            cy.logout();

            // As student, verify testcase_4 now has a show button and can expand
            cy.login('bitdiddle');
            cy.visit(['sample', 'gradeable', 'grades_released_homework_autohiddenEC']);
            cy.scrollTo('bottom');
            cy.get('#details_tc_4').scrollIntoView();
            cy.get('#details_tc_4').parent().find('.loading-tools-show').should('exist').click();
            cy.get('#details_tc_4').should('be.visible');
        });
    });
});
