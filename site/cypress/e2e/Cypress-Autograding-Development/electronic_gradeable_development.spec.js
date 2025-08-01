import { submitSubmissions, checkSubmissions } from '../../support/electronic_gradeable_utils';

describe('Test the development course gradeables', { env: { course: 'development' } }, () => {
    it('Should test the development gradeables with full and buggy submissions', () => {
        cy.login('student');

        const rust = [
            { submissionFiles: { 1: ['correct.rs'] }, expected: [5, 5], full: [5, 5] },
            { submissionFiles: { 1: ['incorrect.rs'] }, expected: [5, 0], full: [5, 5] },
        ];

        const cppCats = [
            { submissionFiles: { 1: ['allCorrect.zip'] }, expected: [2, 3, 4, 4, 4, 4, 4], full: [2, 3, 4, 4, 4, 4, 4] },
            { submissionFiles: { 1: ['extraLinesAtEnd.zip'] }, expected: [2, 3, 2, 2, 2, 2, 0], full: [2, 3, 4, 4, 4, 4, 4] },
        ];

        const gradeables = [
            { name: 'cpp_cats', submissions: cppCats },
            { name: 'rust_hello_world', submissions: rust },
        ];

        // Loop through each gradeable and submit the submissions. This should not change if we add more gradeables.
        gradeables.forEach((gradeable, index) => {
            submitSubmissions(gradeable.name, gradeable.submissions);

            // if at end then loop of submitting submissions then through each gradeables and check the results
            if (index === gradeables.length - 1) {
                gradeables.forEach((gradeable) => {
                    checkSubmissions(gradeable.name, gradeable.submissions);
                });
            }
        });
    });
});
