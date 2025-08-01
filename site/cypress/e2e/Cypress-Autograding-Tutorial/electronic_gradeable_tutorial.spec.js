import { switchOrFindVersion, submitFiles, submitGradeable, newSubmission, checkNonHiddenResults } from '../../support/electronic_gradeable_utils';

const constructFileName = (gradeable, folderName, fileName) => {
    const baseFolder = 'copy_of_tutorial';
    return `${baseFolder}/examples/${gradeable}/submissions/${folderName}/${fileName}`;
};

describe('Test the tutorial course gradeables', { env: { course: 'tutorial' } }, () => {
    it('Should test the docker network gradeable with full and buggy submissions', () => {
        cy.login('instructor');

        const docker_network = '16_docker_network_python';
        const docker_network_full_score = [5, 5, '?', 5];

        // Grab the current version, and then submit and check the gradeable
        switchOrFindVersion(docker_network, null).then((startingVersion) => {
            newSubmission(docker_network);
            submitFiles(constructFileName(docker_network, 'correct', 'server.py'), 1, true);
            submitGradeable(startingVersion + 1);

            // submits the half incorrect files
            newSubmission(docker_network);
            submitFiles(constructFileName(docker_network, 'incorrect', 'server.py'), 1, true);
            submitGradeable(startingVersion + 2);

            newSubmission(docker_network);
            submitFiles(constructFileName(docker_network, 'infinite_response', 'server.py'), 1, true);
            submitGradeable(startingVersion + 3);

            newSubmission(docker_network);
            submitFiles(constructFileName(docker_network, 'large_response', 'server.py'), 1, true);
            submitGradeable(startingVersion + 4);

            newSubmission(docker_network);
            submitFiles(constructFileName(docker_network, 'syntax_error', 'server.py'), 1, true);
            submitGradeable(startingVersion + 5);

            newSubmission(docker_network);
            submitFiles(constructFileName(docker_network, 'udp_correct', 'server.py'), 1, true);
            submitGradeable(startingVersion + 6);

            // check both results
            checkNonHiddenResults(docker_network, startingVersion + 1, docker_network_full_score, docker_network_full_score);
            checkNonHiddenResults(docker_network, startingVersion + 2, [0, 0, 0, 0], docker_network_full_score);
            checkNonHiddenResults(docker_network, startingVersion + 3, [5, 5, '?', 0], docker_network_full_score);
            checkNonHiddenResults(docker_network, startingVersion + 4, [0, 0, '?', 0], docker_network_full_score);
            checkNonHiddenResults(docker_network, startingVersion + 5, [5, 5, '?', 5], docker_network_full_score);
            checkNonHiddenResults(docker_network, startingVersion + 6, [5, 5, '?', 5], docker_network_full_score);
        });
    });
});
