describe('Test cases for grading stats', () => {
    ['instructor','ta','grader'].forEach((user) => {
        beforeEach(() => {
            cy.login(user);
            cy.visit(['sample']);
        });
        it(`${user} view should be accurate for teams.`, () => {
            cy.get('a[href*="/sample/gradeable/grading_team_homework/grading/details"]').click();

            /*
            if (user === 'ta' || user === 'grader') { // Close Grader Responsibilities popup.
                cy.get('.popup-window .form-title .btn.btn-default.close-button.key_to_click').click();
            }
            */

            cy.get('a[href*="/sample/gradeable/grading_team_homework/grading/status"]').click();


            const text = cy.get('#left-grading-stats');
            text.should('contain', 'Students on a team: 101/101 (100%)');
            text.should('contain', 'Number of teams: 36');
            text.should('contain', 'Teams who have submitted on time: 25 / 36 (69.4%)');
            text.should('contain', 'Section 1: 1 / 3 (33.3%)');
        });

        it(`${user} view should be accurate for grades.`, () => {
            cy.get('a[href*="/sample/gradeable/grading_homework/grading/details"]').click();

            /*
            if (user === 'ta' || user === 'grader') { // Close Grader Responsibilities popup.
                cy.get('.popup-window .form-title .btn.btn-default.close-button.key_to_click').click();
            }
            */

            cy.get('a[href*="/sample/gradeable/grading_homework/grading/status"]').click();

            const text = cy.get('#left-grading-stats');
            text.should('contain', 'Students who have submitted on time: 59 / 101 (58.4%)');
            text.should('contain', 'Current percentage of TA grading done: 30 / 59 (50.8%)');
            text.should('contain', 'Section 1: 4 / 9 (44.4%)');
        });

        it(`${user} viewshould be accurate for released grades.`, () => {
            cy.get('a[href*="/sample/gradeable/grades_released_homework/grading/details"]').click();

            /*
            if (user === 'ta' || user === 'grader') { // Close Grader Responsibilities popup.
                cy.get('.popup-window .form-title .btn.btn-default.close-button.key_to_click').click();
            }
            */

            cy.get('a[href*="/sample/gradeable/grades_released_homework/grading/status"]').click();

            const text = cy.get('#left-grading-stats');
            text.should('contain', 'Students who have submitted on time: 64 / 101 (63.4%)');
            text.should('contain', 'Current percentage of TA grading done: 64 / 64 (100.0%)');
            text.should('contain', 'Section 1: 10 / 10 (100.0%)');
            text.should('contain', 'Number of students who have viewed their grade: 49 / 71 (69.0%)');
        });
    });
});

/*

describe('Grading stats as a ta', () => {
    beforeEach(() => {
        cy.visit(['sample']);
        cy.login('ta');
    });
    it('should be accurate for teams.', () => {
        cy.get('a[href*="/sample/gradeable/grading_team_homework/grading/details"]').click();

        // Close Grader Responsibilities popup.
        //cy.get('.popup-window .form-title .btn.btn-default.close-button.key_to_click').click();

        cy.get('a[href*="/sample/gradeable/grading_team_homework/grading/status"]').click();

        const text = cy.get('#numerical-data');
        text.should('contain', 'Students on a team: 101/101 (100%)');
        text.should('contain', 'Number of teams: 36');
        text.should('contain', 'Teams who have submitted: 32 / 36 (88.9%)');
        text.should('contain', 'Section 1: 5 / 5 (100.0%)');
    });
    it('should be accurate for grades.', () => {
        cy.get('a[href*="/sample/gradeable/grading_homework/grading/details"]').click();

        // Close Grader Responsibilities popup.
        cy.get('.popup-window .form-title .btn.btn-default.close-button.key_to_click').click();

        cy.get('a[href*="/sample/gradeable/grading_homework/grading/status"]').click();

        const text = cy.get('#numerical-data');
        text.should('contain', 'Students who have submitted: 66 / 101 (65.3%)');
        text.should('contain', 'Current percentage of TA grading done: 30.75 / 66 (46.6%)');
        text.should('contain', 'Section 1: 2 / 7 (28.6%)');
    });
    it('should be accurate for released grades.', () => {
        cy.get('a[href*="/sample/gradeable/grades_released_homework/grading/details"]').click();

        // Close Grader Responsibilities popup.
        cy.get('.popup-window .form-title .btn.btn-default.close-button.key_to_click').click();

        cy.get('a[href*="/sample/gradeable/grades_released_homework/grading/status"]').click();

        const text = cy.get('#numerical-data');
        text.should('contain', 'Students who have submitted: 73 / 101 (72.3%)');
        text.should('contain', 'Current percentage of TA grading done: 73 / 73 (100.0%)');
        text.should('contain', 'Section 1: 12 / 12 (100.0%)');
        text.should('contain', 'Number of students who have viewed their grade: 50 / 73 (68.5%)');
    });
});

describe('Grading stats as a grader', () => {
    beforeEach(() => {
        cy.visit(['sample']);
        cy.login('grader');
    });
    it('should be accurate for teams.', () => {
        cy.get('a[href*="/sample/gradeable/grading_team_homework/grading/details"]').click();

        // Close Grader Responsibilities popup.
        cy.get('.popup-window .form-title .btn.btn-default.close-button.key_to_click').click();

        cy.get('a[href*="/sample/gradeable/grading_team_homework/grading/status"]').click();

        const text = cy.get('#numerical-data');
        text.should('contain', 'Students on a team: 20/20 (100%)');
        text.should('contain', 'Number of teams: 8');
        text.should('contain', 'Teams who have submitted: 5 / 8 (62.5%)');
        text.should('contain', 'Section 4: 3 / 3 (100.0%)');
    });
    it('should be accurate for grades.', () => {
        cy.get('a[href*="/sample/gradeable/grading_homework/grading/details"]').click();

        // Close Grader Responsibilities popup.
        cy.get('.popup-window .form-title .btn.btn-default.close-button.key_to_click').click();

        cy.get('a[href*="/sample/gradeable/grading_homework/grading/status"]').click();

        const text = cy.get('#numerical-data');
        text.should('contain', 'Students who have submitted: 10 / 20 (50%)');
        text.should('contain', 'Current percentage of TA grading done: 7 / 10 (70.0%)');
        text.should('contain', 'Section 4: 4 / 6 (66.7%)');
    });
    it('should be accurate for released grades.', () => {
        cy.get('a[href*="/sample/gradeable/grades_released_homework/grading/details"]').click();

        // Close Grader Responsibilities popup.
        cy.get('.popup-window .form-title .btn.btn-default.close-button.key_to_click').click();

        cy.get('a[href*="/sample/gradeable/grades_released_homework/grading/status"]').click();

        const text = cy.get('#numerical-data');
        text.should('contain', 'Students who have submitted: 13 / 20 (65%)');
        text.should('contain', 'Current percentage of TA grading done: 13 / 13 (100.0%)');
        text.should('contain', 'Section 4: 6 / 6 (100.0%)');
        text.should('contain', 'Number of students who have viewed their grade: 10 / 13 (76.9%)');
    });
});
*/
