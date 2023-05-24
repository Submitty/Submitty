import { getCurrentSemester } from '../support/utils.js';

import fs from 'fs';
import path from 'path';
import { execFileSync } from 'child_process';

const semester = getCurrentSemester();
const course = 'sample';

const urls = [
    `/home`,
    `/home/courses/new`,
    `/courses/{}/{}`,
    `/courses/{}/{}/gradeable/future_no_tas_homework/update?nav_tab=0`,
    `/courses/{}/{}/autograding_config?g_id=future_no_tas_homework`,
    `/courses/{}/{}/gradeable/future_no_tas_lab/grading?view=all`,
    `/courses/{}/{}/gradeable/future_no_tas_test/grading?view=all`,
    `/courses/{}/{}/gradeable/open_homework/grading/status`,
    `/courses/{}/{}/gradeable/open_homework/bulk_stats`,
    `/courses/{}/{}/gradeable/open_homework/grading/details`,
    `/courses/{}/{}/gradeable/open_homework`,
    `/courses/{}/{}/gradeable/open_team_homework/team`,
    `/courses/{}/{}/gradeable/grades_released_homework_autota`,
    `/courses/{}/{}/notifications`,
    `/courses/{}/{}/notifications/settings`,
    `/courses/{}/{}/gradeable`,
    `/courses/{}/{}/config`,
    `/courses/{}/{}/theme`,
    `/courses/{}/{}/office_hours_queue`,
    `/courses/{}/{}/course_materials`,
    `/courses/{}/{}/forum`,
    `/courses/{}/{}/forum/threads/new`,
    `/courses/{}/{}/forum/categories`,
    `/courses/{}/{}/forum/stats`,
    `/courses/{}/{}/users`,
    `/courses/{}/{}/graders`,
    `/courses/{}/{}/sections`,
    `/courses/{}/{}/student_photos`,
    `/courses/{}/{}/late_days`,
    `/courses/{}/{}/extensions`,
    `/courses/{}/{}/grade_override`,
    `/courses/{}/{}/plagiarism`,
    `/courses/{}/{}/plagiarism/configuration/new`,
    `/courses/{}/{}/reports`,
    `/courses/{}/{}/late_table`,
    `/courses/{}/{}/grades`,
    `/courses/{}/{}/polls`,
    `/courses/{}/{}/polls/newPoll`,
    `/courses/{}/{}/sql_toolbox`,
    `/admin/docker`
];

const baselinePath = path.join(__dirname, 'accessibility_baseline.json');
const baseline = new Map(Object.entries(JSON.parse(fs.readFileSync(baselinePath))));

describe('Test cases for the site\'s adherence to accessibility guidelines', () => {
    beforeEach(() => {
        cy.visit('/');
        cy.login('instructor');
    });

    for (const url of urls) {
        it('Path: ' + url, () => {
            const foundErrorMessages = [];
            const foundErrors = [];

            cy.visit(url.replace('{}/{}', semester + '/' + course));
            cy.get('html:root').eq(0).invoke('prop', 'outerHTML').then(content => {
                const docPath = path.join(__dirname, 'tmp', 'doc.html');
                fs.writeFileSync(docPath, `<!DOCTYPE html>\n${content}`);
                const output = execFile('java', [ '-jar', '/usr/bin/vnu.jar', '--exit-zero-always', '--format', 'json', docPath], { shell: true });
                cy.log(output);

                const outputData = JSON.parse(output);

                const skipMessages = [
                    "Start tag seen without seeding a doctype first",
                    "Possible misuse of “aria-label”",
                    "The “date” input type is not supported in all browsers."
                ];

                for (const error of outputData.messages) {
                    if (skipMessages.includes(error.message)
                        || baseline.get(url)?.includes(error.message)
                        || foundErrorMessages.includes(error.message))
                        continue;

                    foundErrorMessages.push(error.message);
                    foundErrors.push({
                        error: error.message.replace(/\u201c|\u201d/g, "'").trim(),
                        htmlExtract: error.extract.trim(),
                        type: error.type.trim()
                    });
                }

                cy.wrap(foundErrors).expect.to.be.an('array').that.is.empty();
            });
        });
    }
});