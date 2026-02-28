import { skipOn } from '@cypress/skip-test';

skipOn(Cypress.env('run_area') === 'CI', () => {
    describe('Testing functionality of Autograder Results', () => {

        beforeEach(() => {
            cy.visit(['sample', 'gradeable']);
        });

        // Function to consistently set flatpickr dates
        const setFlatpickrDate = (selector, year, month = 0, day = 1, hour = 23, minute = 59, second = 59) => {
            cy.get(selector).then($el => {
                const fp = $el[0]._flatpickr;
                if (fp && typeof fp.setDate === 'function') {
                    fp.setDate(new Date(year, month, day, hour, minute, second), true);
                } else {
                    cy.wrap($el).clear().type(`${year}-01-01 23:59:59`);
                }
            });
        };

        it('Should set the due date to the future and verify hidden testcase has no show button', () =>{
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

            // Ensure details can be toggled for visible testcases
            cy.get('#testcase_1').scrollIntoView();
            cy.get('#testcase_1').should('not.be.visible');
            cy.get('.loading-tools-show').eq(1).as('loading-tool-show');
            cy.get('@loading-tool-show').scrollIntoView();
            cy.get('@loading-tool-show').click();
            cy.get('#testcase_1').scrollIntoView();
            cy.get('#testcase_1').should('be.visible');

            // Hidden testcase (4) should not have a "show details" control
            cy.get('#testcase_4').scrollIntoView();
            cy.get('#testcase_4').parent().find('.loading-tools-show').should('not.exist');

            // Open all and verify testcase_4 remains hidden
            cy.get('.loading-tools-show').first().click();
            cy.get('#testcase_4').scrollIntoView();
            cy.get('#testcase_4').should('not.be.visible');
        })


        it('Should set due date back to normal and verify hidden testcase can be shown', ()=>{
            // Reset dates as instructor
            cy.login('instructor');
            cy.visit(['sample', 'gradeable', 'grades_released_homework_autohiddenEC', 'update']);
            cy.get('#page_5_nav').click();
            setFlatpickrDate('#date_due', 1972);
            setFlatpickrDate('#date_grade', 1973);
            setFlatpickrDate('#date_grade_due', 1974);
            setFlatpickrDate('#date_released', 1974);

            // As student, verify testcase_4 now has a show button and can expand
            cy.login('bitdiddle');
            cy.visit(['sample', 'gradeable', 'grades_released_homework_autohiddenEC']);
            cy.scrollTo('bottom');
            cy.get('#testcase_4').scrollIntoView();
            cy.get('#testcase_4').parent().find('.loading-tools-show').should('exist').click();
            cy.get('#testcase_4').should('be.visible');
        })
        it('Should display confetti', () => {
            cy.login('bitdiddle');
            cy.get('#confetti_canvas').should('have.css', 'display').and('match', /none/);
            cy.get('.box-title-total').first().click();
            cy.get('#confetti_canvas').should('have.css', 'display').and('not.match', /none/);
        });

        it('Should show and hide details', () => {
            cy.login('bitdiddle');
            cy.scrollTo('bottom');
            //  Open
            cy.get('#testcase_1').scrollIntoView();
            cy.get('#testcase_1').should('not.be.visible');
            cy.get('.loading-tools-show').eq(1).as('loading-tool-show');
            cy.get('@loading-tool-show').scrollIntoView();
            cy.get('@loading-tool-show').click();
            cy.get('#testcase_1').scrollIntoView();
            cy.get('#testcase_1').should('be.visible');
            //  Close
            cy.get('.loading-tools-hide').eq(1).as('loading-tool-hide');
            cy.get('@loading-tool-hide').scrollIntoView();
            cy.get('@loading-tool-hide').click();
            cy.get('#testcase_1').scrollIntoView();
            cy.get('#testcase_1').should('not.be.visible');
        });

        it('Should expand and collapse all test cases', () => {
            cy.login('bitdiddle');
            cy.scrollTo('bottom');
            // Open all
            cy.get('.loading-tools-show').first().click();
            cy.get('#testcase_1').scrollIntoView();
            cy.get('#testcase_1').should('be.visible');
            cy.get('#testcase_2').scrollIntoView();
            cy.get('#testcase_2').should('be.visible');
            cy.get('#testcase_3').scrollIntoView();
            cy.get('#testcase_3').should('be.visible');
            cy.get('#testcase_4').scrollIntoView();
            cy.get('#testcase_4').should('be.visible');
            cy.get('#testcase_6').scrollIntoView();
            cy.get('#testcase_6').should('be.visible');
            cy.get('#testcase_8').scrollIntoView();
            cy.get('#testcase_8').should('be.visible');
            //  Close all
            cy.get('.loading-tools-hide').first().click();
            cy.get('#testcase_1').scrollIntoView();
            cy.get('#testcase_1').should('not.be.visible');
            cy.get('#testcase_2').scrollIntoView();
            cy.get('#testcase_2').should('not.be.visible');
            cy.get('#testcase_3').scrollIntoView();
            cy.get('#testcase_3').should('not.be.visible');
            cy.get('#testcase_4').scrollIntoView();
            cy.get('#testcase_4').should('not.be.visible');
            cy.get('#testcase_6').scrollIntoView();
            cy.get('#testcase_6').should('not.be.visible');
            cy.get('#testcase_8').scrollIntoView();
            cy.get('#testcase_8').should('not.be.visible');
        });

        it('Should cancel loading', () => {
            cy.login('bitdiddle');
            cy.scrollTo('bottom');
            // Open
            cy.get('#testcase_1').should('not.be.visible');
            cy.get('.loading-tools-show').eq(1).click();
            cy.get('.loading-tools-in-progress').first().click(); // cancel
            cy.get('#testcase_1').should('not.be.visible');
        });
    });
});
