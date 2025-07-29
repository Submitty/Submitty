import { switchOrFindVersion, submitFiles, submitGradeable, newSubmission, checkNonHiddenResults } from '../../support/electronic_gradeable_utils';

const constructFileName = (gradeable, fileName) => {
    const baseFolder = 'copy_of_more_autograding_examples';
    return `${baseFolder}/${gradeable}/submissions/${fileName}`;
};

describe('Test the development course gradeables', { env: { course: 'development' } }, () => {
    it('Should test the development gradeables with full and buggy submissions', () => {
        cy.login('aphacker');

        const cpp_cats = 'cpp_cats';
        const cpp_cats_full_score = [2, 3, 4, 4, 4, 4, 4];

        // Grab the current version, and then submit and check the gradeable
        switchOrFindVersion(cpp_cats, null).then((cpp_cats_starting_version) => {
            newSubmission(cpp_cats);
            submitFiles(constructFileName(cpp_cats, 'allCorrect.zip'), 1, true);
            submitGradeable(cpp_cats_starting_version + 1);

            // submits the half incorrect files
            newSubmission(cpp_cats);
            submitFiles(constructFileName(cpp_cats, 'extraLinesAtEnd.zip'), 1, true);
            submitGradeable(cpp_cats_starting_version + 2);

            const rust = 'rust_hello_world';
            const rust_full_score = [5, 5];

            switchOrFindVersion(rust, null).then((rust_starting_version) => {
                // submit the full score file
                newSubmission(rust);
                submitFiles(constructFileName(rust, 'correct.rs'), 1, true);
                submitGradeable(rust_starting_version + 1);

                // submit the buggy file
                newSubmission(rust);
                submitFiles(constructFileName(rust, 'incorrect.rs'), 1, true);
                submitGradeable(rust_starting_version + 2);

                // check both cpp cats results
                checkNonHiddenResults(cpp_cats, cpp_cats_starting_version + 1, cpp_cats_full_score, cpp_cats_full_score);
                checkNonHiddenResults(cpp_cats, cpp_cats_starting_version + 2, [2, 3, 2, 2, 2, 2, 0], cpp_cats_full_score);

                // check both rust results
                checkNonHiddenResults(rust, rust_starting_version + 1, rust_full_score, rust_full_score);
                checkNonHiddenResults(rust, rust_starting_version + 2, [5, 0], rust_full_score);
            });
        });
    });
});
