import { skipOn } from '@cypress/skip-test';

skipOn(Cypress.env('run_area') === 'CI', () => {
    describe('Grading stats as an instructor', () => {
        beforeEach(() => {
            cy.visit(['sample']);
            cy.login('instructor');
        });
        afterEach(() => {
            cy.logout();
        });
        it('should be accurate for teams.', () => {
            cy.get('a[href*="/sample/gradeable/grading_team_homework/grading/details"]').click();
            cy.get('a[href*="/sample/gradeable/grading_team_homework/grading/status"]').click();

            const text = cy.get('#numerical-data');
            text.contains('Students on a team: 101/101 (100%)');
            text.contains('Number of teams: 36');
            text.contains('Teams who have submitted: 32 / 36 (88.9%)');
            text.contains('Section 1: 5 / 5 (100.0%)');
        });
        it('should be accurate for grades.', () => {
            cy.get('a[href*="/sample/gradeable/grading_homework/grading/details"]').click();
            cy.get('a[href*="/sample/gradeable/grading_homework/grading/status"]').click();

            const text = cy.get('#numerical-data');
            text.contains('Students who have submitted: 66 / 101 (65.3%)');
            text.contains('Current percentage of TA grading done: 30.75 / 66 (46.6%)');
            text.contains('Section 1: 2 / 7 (28.6%)');
        });
        it('should be accurate for released grades.', () => {
            cy.get('a[href*="/sample/gradeable/grades_released_homework/grading/details"]').click();
            cy.get('a[href*="/sample/gradeable/grades_released_homework/grading/status"]').click();

            const text = cy.get('#numerical-data');
            text.contains('Students who have submitted: 73 / 101 (72.3%)');
            text.contains('Current percentage of TA grading done: 73 / 73 (100.0%)');
            text.contains('Section 1: 12 / 12 (100.0%)');
            text.contains('Number of students who have viewed their grade: 50 / 73 (68.5%)');
        });
    });

    describe('Grading stats as a ta', () => {
        beforeEach(() => {
            cy.visit(['sample']);
            cy.login('ta');
        });
        afterEach(() => {
            cy.logout();
        });
        it('should be accurate for teams.', () => {
            cy.get('a[href*="/sample/gradeable/grading_team_homework/grading/details"]').click();
            cy.get('a[href*="/sample/gradeable/grading_team_homework/grading/status"]').click();

            const text = cy.get('#numerical-data');
            text.contains('Students on a team: 101/101 (100%)');
            text.contains('Number of teams: 36');
            text.contains('Teams who have submitted: 32 / 36 (88.9%)');
            text.contains('Section 1: 5 / 5 (100.0%)');
        });
        it('should be accurate for grades.', () => {
            cy.get('a[href*="/sample/gradeable/grading_homework/grading/details"]').click();
            cy.get('a[href*="/sample/gradeable/grading_homework/grading/status"]').click();

            const text = cy.get('#numerical-data');
            text.contains('Students who have submitted: 66 / 101 (65.3%)');
            text.contains('Current percentage of TA grading done: 30.75 / 66 (46.6%)');
            text.contains('Section 1: 2 / 7 (28.6%)');
        });
        it('should be accurate for released grades.', () => {
            cy.get('a[href*="/sample/gradeable/grades_released_homework/grading/details"]').click();
            cy.get('a[href*="/sample/gradeable/grades_released_homework/grading/status"]').click();

            const text = cy.get('#numerical-data');
            text.contains('Students who have submitted: 73 / 101 (72.3%)');
            text.contains('Current percentage of TA grading done: 73 / 73 (100.0%)');
            text.contains('Section 1: 12 / 12 (100.0%)');
            text.contains('Number of students who have viewed their grade: 50 / 73 (68.5%)');
        });
    });

    describe('Grading stats as a grader', () => {
        beforeEach(() => {
            cy.visit(['sample']);
            cy.login('grader');
        });
        afterEach(() => {
            cy.logout();
        });
        it('should be accurate for teams.', () => {
            cy.get('a[href*="/sample/gradeable/grading_team_homework/grading/details"]').click();
            cy.get('a[href*="/sample/gradeable/grading_team_homework/grading/status"]').click();

            const text = cy.get('#numerical-data');
            text.contains('Students on a team: 20/20 (100%)');
            text.contains('Number of teams: 8');
            text.contains('Teams who have submitted: 5 / 8 (62.5%)');
            text.contains('Section 4: 3 / 3 (100.0%)');
        });
        it('should be accurate for grades.', () => {
            cy.get('a[href*="/sample/gradeable/grading_homework/grading/details"]').click();
            cy.get('a[href*="/sample/gradeable/grading_homework/grading/status"]').click();

            const text = cy.get('#numerical-data');
            text.contains('Students who have submitted: 10 / 20 (50%)');
            text.contains('Current percentage of TA grading done: 7 / 10 (70.0%)');
            text.contains('Section 4: 4 / 6 (66.7%)');
        });
        it('should be accurate for released grades.', () => {
            cy.get('a[href*="/sample/gradeable/grades_released_homework/grading/details"]').click();
            cy.get('a[href*="/sample/gradeable/grades_released_homework/grading/status"]').click();

            const text = cy.get('#numerical-data');
            text.contains('Students who have submitted: 13 / 20 (65%)');
            text.contains('Current percentage of TA grading done: 13 / 13 (100.0%)');
            text.contains('Section 4: 6 / 6 (100.0%)');
            text.contains('Number of students who have viewed their grade: 10 / 13 (76.9%)');
        });
    });
});
