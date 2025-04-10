// Note: This test requires you have Java and JDK installed on your machine as well as added to your path if on Windows.

import { getCurrentSemester } from '../../support/utils.js';

import vnu from 'vnu-jar';

const semester = getCurrentSemester();
const course = 'sample';

// Remove command is different on Windows.
const rm_command = Cypress.platform === 'win32' ? 'rd /s /q cypress\\tmp' : 'rm -r cypress/tmp';

const urls = [
    '/home',
    '/home/courses/new',
    '/courses/{}/{}',
    '/courses/{}/{}/gradeable/future_no_tas_homework/update?nav_tab=0',
    '/courses/{}/{}/autograding_config?g_id=future_no_tas_homework',
    '/courses/{}/{}/gradeable/future_no_tas_lab/grading?view=all',
    '/courses/{}/{}/gradeable/future_no_tas_test/grading?view=all',
    '/courses/{}/{}/gradeable/open_homework/grading/status',
    '/courses/{}/{}/gradeable/open_homework/bulk_stats',
    '/courses/{}/{}/gradeable/open_homework/grading/details',
    '/courses/{}/{}/gradeable/open_homework',
    '/courses/{}/{}/gradeable/open_team_homework/team',
    '/courses/{}/{}/gradeable/grades_released_homework_autota',
    '/courses/{}/{}/notifications',
    '/courses/{}/{}/notifications/settings',
    '/courses/{}/{}/gradeable',
    '/courses/{}/{}/config',
    '/courses/{}/{}/theme',
    '/courses/{}/{}/office_hours_queue',
    '/courses/{}/{}/course_materials',
    '/courses/{}/{}/forum',
    '/courses/{}/{}/forum/threads/new',
    '/courses/{}/{}/forum/categories',
    '/courses/{}/{}/forum/stats',
    '/courses/{}/{}/users',
    '/courses/{}/{}/graders',
    '/courses/{}/{}/sections',
    '/courses/{}/{}/student_photos',
    '/courses/{}/{}/late_days',
    '/courses/{}/{}/extensions',
    '/courses/{}/{}/grade_override',
    '/courses/{}/{}/plagiarism',
    '/courses/{}/{}/plagiarism/configuration/new',
    '/courses/{}/{}/reports',
    '/courses/{}/{}/reports/rainbow_grades_customization',
    '/courses/{}/{}/late_table',
    '/courses/{}/{}/grades',
    '/courses/{}/{}/polls',
    '/courses/{}/{}/polls/newPoll',
    '/courses/{}/{}/sql_toolbox',
    '/admin/docker',
];

describe('Test cases for the site\'s adherence to accessibility guidelines', () => {
    let baseline;

    before(() => {
        cy.fixture('accessibility_baseline').then((data) => {
            expect(data).to.be.an('object');
            baseline = new Map(Object.entries(data));
        });

        cy.exec(rm_command, { failOnNonZeroExit: false });
    });

    beforeEach(() => {
        cy.login();
    });

    afterEach(() => {
        cy.exec(rm_command);
    });

    for (const url of urls) {
        it(`Path: "${url}"`, () => {
            cy.visit(url.replace('{}/{}', `${semester}/${course}`));
            cy.get('html:root').eq(0).invoke('prop', 'outerHTML').then((content) => {
                cy.writeFile('cypress/tmp/doc.html', `<!DOCTYPE html>\n${content}`, 'utf8').then(() => {
                    cy.exec(`java -jar "${vnu}" --format json cypress/tmp/doc.html`, { failOnNonZeroExit: false }).then((result) => {
                        console.log(result.stderr);
                        const output = JSON.parse(result.stderr);

                        const foundErrorMessages = [];
                        const foundErrors = [];

                        const skipMessages = [
                            'Start tag seen without seeding a doctype first',
                            'Possible misuse of “aria-label”',
                            'The “date” input type is not supported in all browsers.',
                            'The “type” attribute is unnecessary for JavaScript resources.',
                        ];

                        for (const error of output.messages) {
                            if (skipMessages.some((txt) => error.message.startsWith(txt))
                                || (baseline.get(url) || []).includes(error.message)
                                || foundErrorMessages.includes(error.message)) {
                                continue;
                            }

                            foundErrorMessages.push(error.message);
                            foundErrors.push({
                                error: error.message.replace(/\u201c|\u201d/g, "'").trim(),
                                htmlExtract: error.extract.trim(),
                                type: error.type.trim(),
                            });
                        }

                        if (foundErrors.length > 0) {
                            throw new Error(`Accessibility errors at ${url}:\n${foundErrorMessages.join('\n')}`);
                        }
                    });
                });
            });
        });
    }
});
