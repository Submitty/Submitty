describe('TA Grading Panel Switcher', () => {
    it('Should show correct panel options for each layout', () => {
        const panels = [
            {
                button: '[data-testid="show-autograding"]',
                selector: '[data-testid="autograding-results-panel-position-select"]',
            },
            {
                button: '[data-testid="grading-rubric-panel-toggle"]',
                selector: '[data-testid="grading-rubric-panel-position-select"]',
            },
            {
                button: '[data-testid="show-submission"]',
                selector: '[data-testid="submission-browser-panel-position-select"]',
            },
            {
                button: '[data-testid="student-info-panel-toggle"]',
                selector: '[data-testid="student-info-panel-position-select"]',
            },
            {
                button: '[data-testid="solution-ta-notes-panel-toggle"]',
                selector: '[data-testid="solution-ta-notes-panel-position-select"]',
            },
            {
                button: '[data-testid="grade-inquiry-info-panel-toggle"]',
                selector: '[data-testid="grade-inquiry-info-panel-position-select"]',
            },
        ];

        const layouts = [
            {
                name: 'Single Panel',
                selector: '[data-testid="layout-single-panel-apply"]',
                expectedOptions: null,
            },
            {
                name: 'Side by Side',
                selector: '[data-testid="layout-two-panel-equal-apply"]',
                expectedOptions: [
                    'Open as left panel',
                    'Open as right panel',
                ],
            },
            {
                name: 'Side by Side Taller Left',
                selector: '[data-testid="layout-two-panel-tall-left-apply"]',
                expectedOptions: [
                    'Open as left panel',
                    'Open as right panel',
                ],
            },
            {
                name: 'Two on Left One on Right',
                selector: '[data-testid="layout-three-panel-two-left-apply"]',
                expectedOptions: [
                    'Open as top left panel',
                    'Open as bottom left panel',
                    'Open as right panel',
                ],
            },
            {
                name: 'Two on Left One on Right Taller Left',
                selector: '[data-testid="layout-three-panel-two-left-tall-left-apply"]',
                expectedOptions: [
                    'Open as top left panel',
                    'Open as bottom left panel',
                    'Open as right panel',
                ],
            },
            {
                name: 'One on Left Two on Right',
                selector: '[data-testid="layout-three-panel-two-right-apply"]',
                expectedOptions: [
                    'Open as left panel',
                    'Open as top right panel',
                    'Open as bottom right panel',
                ],
            },
            {
                name: 'One on Left Two on Right Taller Left',
                selector: '[data-testid="layout-three-panel-two-right-tall-left-apply"]',
                expectedOptions: [
                    'Open as left panel',
                    'Open as top right panel',
                    'Open as bottom right panel',
                ],
            },
            {
                name: 'Two on Left Two on Right',
                selector: '[data-testid="layout-four-panel-equal-apply"]',
                expectedOptions: [
                    'Open as top left panel',
                    'Open as bottom left panel',
                    'Open as top right panel',
                    'Open as bottom right panel',
                ],
            },
            {
                name: 'Two on Left Two on Right Taller Left',
                selector: '[data-testid="layout-four-panel-tall-left-apply"]',
                expectedOptions: [
                    'Open as top left panel',
                    'Open as bottom left panel',
                    'Open as top right panel',
                    'Open as bottom right panel',
                ],
            },
        ];

        cy.login('grader');
        cy.visit('/courses/s26/sample/gradeable/no_due_date_no_release/grading/grade?who_id=FI9yKu3j9DrXWt5&sort=id&direction=ASC');

        cy.wrap(layouts).each((layout) => {
            cy.get('#two-panel-mode-btn').should('be.visible').click();
            cy.get(layout.selector).should('be.visible').click();

            if (!layout.expectedOptions) {
                return;
            }

            cy.wrap(panels).each((panel) => {
                cy.get(panel.button).should('be.visible').click();
                cy.get(panel.selector).should('be.visible');

                layout.expectedOptions.forEach((option) => {
                    cy.get(panel.selector).should('contain', option);
                });

                cy.get(panel.button).click();
            });
        });
    });
});
