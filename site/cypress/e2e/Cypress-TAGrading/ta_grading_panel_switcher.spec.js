describe('TA Grading Panel Switcher', () => {
    it('Should show correct panel options for each layout', () => {
        const panels = [
            '#autograding_results_btn',
            '#grading_rubric_btn',
            '#submission_browser_btn',
            '#student_info_btn',
            '#solution_ta_notes_btn',
            '#grade_inquiry_info_btn',
        ];

        const layouts = [
            {
                name: 'Single Panel',
                selector: '#layout-option-1 > div.layout-option-cont > div > div > button',
                expectedOptions: null,
            },
            {
                name: 'Side by Side',
                selector: '#layout-option-2 > div.layout-option-cont > div:nth-child(1) > div > button',
                expectedOptions: [
                    'Open as left panel',
                    'Open as right panel',
                ],
            },
            {
                name: 'Side by Side Taller Left',
                selector: '#layout-option-2 > div.layout-option-cont > div:nth-child(2) > div > button',
                expectedOptions: [
                    'Open as left panel',
                    'Open as right panel',
                ],
            },
            {
                name: 'Two on Left One on Right',
                selector: '#layout-option-3 > div.layout-option-cont > div:nth-child(1) > div > button',
                expectedOptions: [
                    'Open as top left panel',
                    'Open as bottom left panel',
                    'Open as right panel',
                ],
            },
            {
                name: 'Two on Left One on Right Taller Left',
                selector: '#layout-option-3 > div.layout-option-cont > div:nth-child(2) > div > button',
                expectedOptions: [
                    'Open as top left panel',
                    'Open as bottom left panel',
                    'Open as right panel',
                ],
            },
            {
                name: 'One on Left Two on Right',
                selector: '#layout-option-3 > div.layout-option-cont > div:nth-child(3) > div > button',
                expectedOptions: [
                    'Open as left panel',
                    'Open as top right panel',
                    'Open as bottom right panel',
                ],
            },
            {
                name: 'One on Left Two on Right Taller Left',
                selector: '#layout-option-3 > div.layout-option-cont > div:nth-child(4) > div > button',
                expectedOptions: [
                    'Open as left panel',
                    'Open as top right panel',
                    'Open as bottom right panel',
                ],
            },
            {
                name: 'Two on Left Two on Right',
                selector: '#layout-option-4 > div.layout-option-cont > div:nth-child(1) > div > button',
                expectedOptions: [
                    'Open as top left panel',
                    'Open as bottom left panel',
                    'Open as top right panel',
                    'Open as bottom right panel',
                ],
            },
            {
                name: 'Two on Left Two on Right Taller Left',
                selector: '#layout-option-4 > div.layout-option-cont > div:nth-child(2) > div > button',
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
        layouts.forEach((layout) => {
            cy.get('#two-panel-mode-btn').click();
            cy.get(layout.selector).click();

            if (layout.expectedOptions === null) {
                return;
            };

            panels.forEach((panelBtn) => {
                cy.get(panelBtn).click();
                layout.expectedOptions.forEach((option) => {
                    cy.get('#autograding_results_select').should('contain', option);
                });
                cy.get(panelBtn).click();
            });
            cy.reload();
        });
    });
});
