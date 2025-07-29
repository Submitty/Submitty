import { submitSubmissions, checkSubmissions } from '../../support/electronic_gradeable_utils';

describe('Test the development course gradeables', { env: { course: 'development' } }, () => {
    it('Should test the development gradeables with full and buggy submissions', () => {
        cy.login('instructor');

        const rust = [
            { submissionFiles: { 1: ['correct.rs'] }, expected: [5, 5], full: [5, 5] },
            { submissionFiles: { 1: ['incorrect.rs'] }, expected: [5, 0], full: [5, 5] },
        ];

        const cppCats = [
            { submissionFiles: { 1: ['allCorrect.zip'] }, expected: [2, 3, 4, 4, 4, 4, 4], full: [2, 3, 4, 4, 4, 4, 4] },
            { submissionFiles: { 1: ['extraLinesAtEnd.zip'] }, expected: [2, 3, 2, 2, 2, 2, 0], full: [2, 3, 4, 4, 4, 4, 4] },
        ];

        submitSubmissions('cpp_cats', cppCats)
            .then(() => submitSubmissions('rust_hello_world', rust))
            .then(() => checkSubmissions('cpp_cats', cppCats))
            .then(() => checkSubmissions('rust_hello_world', rust));
    });
});
