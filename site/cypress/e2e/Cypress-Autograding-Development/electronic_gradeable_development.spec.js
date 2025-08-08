import { switchOrFindVersion, submitFiles, submitGradeable, newSubmission, checkNonHiddenResults } from '../../support/electronic_gradeable_utils';

const constructFileName = (gradeable, fileName) => {
    const baseFolder = 'copy_of_more_autograding_examples';
    return `${baseFolder}/${gradeable}/submissions/${fileName}`;
};

describe('Test the development course gradeables', { env: { course: 'development' } }, () => {
    it('Should test the cpp cats gradeable with full and buggy submissions', () => {
        cy.login('instructor');

        const cpp_cats = 'cpp_cats';
        const cpp_cats_full_score = [2, 3, 4, 4, 4, 4, 4];

        // Grab the current version, and then submit and check the gradeable
        switchOrFindVersion(cpp_cats, null).then((startingVersion) => {
            newSubmission(cpp_cats);
            submitFiles(constructFileName(cpp_cats, 'allCorrect.zip'), 1, true);
            submitGradeable(startingVersion + 1);

            // submits the half incorrect files
            newSubmission(cpp_cats);
            submitFiles(constructFileName(cpp_cats, 'extraLinesAtEnd.zip'), 1, true);
            submitGradeable(startingVersion + 2);

            // check both results
            checkNonHiddenResults(cpp_cats, startingVersion + 1, cpp_cats_full_score, cpp_cats_full_score);
            checkNonHiddenResults(cpp_cats, startingVersion + 2, [2, 3, 2, 2, 2, 2, 0], cpp_cats_full_score);
        });
    });
});
