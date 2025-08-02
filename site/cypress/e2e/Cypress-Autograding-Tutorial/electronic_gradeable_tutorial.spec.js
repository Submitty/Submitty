import { runTests } from '../../support/electronic_gradeable_utils';

describe('Test the tutorial course gradeables', { env: { course: 'tutorial' } }, () => {
    it('Should test the docker network gradeable with full and buggy submissions', () => {
        cy.login('student');

        const docker_network = '16_docker_network_python';
        const fullScore = [5, 5, '?', 5];

        const submissions = [
            { submissionFiles: { 1: ['correct/server.py'] }, expected: fullScore, full: fullScore },
            { submissionFiles: { 1: ['incorrect/server.py'] }, expected: [0, 0, 0, 0], full: fullScore },
            { submissionFiles: { 1: ['infinite_response/server.py'] }, expected: [5, 5, '?', 0], full: fullScore },
            { submissionFiles: { 1: ['large_response/server.py'] }, expected: [0, 0, '?', 0], full: fullScore },
            { submissionFiles: { 1: ['syntax_error/server.py'] }, expected: [5, 5, '?', 5], full: fullScore },
            { submissionFiles: { 1: ['udp_correct/server.py'] }, expected: [5, 5, '?', 5], full: fullScore },
        ];

        const gradeables = [
            { name: docker_network, submissions: submissions },
        ];

        runTests(gradeables);
    });
});
