import { buildUrl } from '/cypress/support/utils.js';

describe('Test Rainbow Grading', () => {
    beforeEach(() => {
        // instructor2 has access to the testing course
        cy.login('instructor2');
        cy.visit(['testing', 'config']);
        cy.get('[data-testid="display-rainbow-grades-summary"]').check();
        cy.visit(['testing', 'reports', 'rainbow_grades_customization']);
        cy.get('[data-testid="display-grade-summary"]').should('be.visible'); // Ensure page is loaded
        reset();
    });
    it('Test Web-Based Rainbow Grades Customization', () => {
        // Ensure that elements requiring a manual_customization.json are only visible if file exists
        cy.window().its('manualCustomizationExists').then((manualCustomizationExists) => {
            if (manualCustomizationExists === true) {
                cy.get('[data-testid="ask-which-customization"]').should('not.be.hidden');
            }
            else {
                cy.get('[data-testid="ask-which-customization"]').should('be.hidden');
            }
        });

        // Ensure all checkboxes work and toggle visibility of related elements
        checkCheckbox('[data-testid="display-grade-summary"]');
        checkCheckbox('[data-testid="display-grade-details"]');
        checkCheckbox('[data-testid="display-exam-seating"]');
        checkCheckbox('[data-testid="display-final-cutoff"]');

        cy.get('[data-testid="display-section"]').should('not.be.checked');
        cy.get('[data-testid="section-labels"]').should('not.be.visible');
        checkCheckbox('[data-testid="display-section"]');
        cy.get('[data-testid="section-labels"]').should('be.visible');

        cy.get('[data-testid="display-messages"]').should('not.be.checked');
        cy.get('[data-testid="cust-messages"]').should('not.be.visible');
        checkCheckbox('[data-testid="display-messages"]');
        cy.get('[data-testid="cust-messages"]').should('be.visible');

        // TODO: add checks for warning once feature is implemented
        checkCheckbox('[data-testid="display-warning"]');

        cy.get('[data-testid="display-final-grade"]').should('not.be.checked');
        cy.get('[data-testid="final-grade-cutoffs"]').should('not.be.visible');
        cy.get('[data-testid="manual-grading"]').should('not.be.visible');
        checkCheckbox('[data-testid="display-final-grade"]');
        cy.get('[data-testid="final-grade-cutoffs"]').should('be.visible');
        cy.get('[data-testid="manual-grading"]').should('be.visible');

        // TODO: add checks for instructor notes once feature is implemented
        checkCheckbox('[data-testid="display-instructor-notes"]');

        cy.get('[data-testid="display-benchmarks-lowest_a-"]').should('not.be.checked');
        cy.get('[data-testid="display-benchmarks-lowest_b-"]').should('not.be.checked');
        cy.get('[data-testid="display-benchmarks-lowest_c-"]').should('not.be.checked');
        cy.get('[data-testid="display-benchmarks-lowest_d"]').should('not.be.checked');
        cy.get('[data-testid="benchmark-percents"]').should('not.be.visible');
        checkCheckbox('[data-testid="display-benchmarks-average"]');
        checkCheckbox('[data-testid="display-benchmarks-stddev"]');
        checkCheckbox('[data-testid="display-benchmarks-perfect"]');
        checkCheckbox('[data-testid="display-benchmarks-lowest_a-"]');
        checkCheckbox('[data-testid="display-benchmarks-lowest_b-"]');
        checkCheckbox('[data-testid="display-benchmarks-lowest_c-"]');
        checkCheckbox('[data-testid="display-benchmarks-lowest_d"]');
        cy.get('[data-testid="benchmark-percents"]').should('be.visible');

        // Ensure gradeables can be added
        // TODO: test drag/drop functionality
        cy.get('[data-testid="gradeables"]').should('be.visible');
        cy.get('[data-testid="buckets-used-list"]').should('be.visible');
        cy.get('[data-testid="buckets-available-list"]').should('be.visible');
        cy.get('[data-testid="gradeable-config"]').should('be.visible');

        // Ensure textboxes have correct initial values and can be modified
        checkTextbox('[data-testid="cust-messages-textarea"]', '', 'message');
        checkTextbox('[data-testid="benchmark-lowest_a-"]', '0.9', '0.8');
        checkTextbox('[data-testid="benchmark-lowest_b-"]', '0.8', '0.7');
        checkTextbox('[data-testid="benchmark-lowest_c-"]', '0.7', '0.6');
        checkTextbox('[data-testid="benchmark-lowest_d"]', '0.6', '0.5');
        checkTextbox('[data-testid="section-and-labels-1"]', '1', 'TA 1');
        checkTextbox('[data-testid="section-and-labels-2"]', '2', 'TA 2');
        checkTextbox('[data-testid="section-and-labels-3"]', '3', 'TA 3');
        checkTextbox('[data-testid="section-and-labels-4"]', '4', 'TA 4');
        checkTextbox('[data-testid="section-and-labels-5"]', '5', 'TA 5');
        checkTextbox('[data-testid="section-and-labels-6"]', '6', 'TA 6');
        checkTextbox('[data-testid="section-and-labels-7"]', '7', 'TA 7');
        checkTextbox('[data-testid="section-and-labels-8"]', '8', 'TA 8');
        checkTextbox('[data-testid="section-and-labels-9"]', '9', 'TA 9');
        checkTextbox('[data-testid="section-and-labels-10"]', '10', 'TA 10');
        checkTextbox('[data-testid="cutoff-A"]', '93', '87');
        checkTextbox('[data-testid="cutoff-A-"]', '90', '80');
        checkTextbox('[data-testid="cutoff-B+"]', '87', '77');
        checkTextbox('[data-testid="cutoff-B"]', '83', '73');
        checkTextbox('[data-testid="cutoff-B-"]', '80', '70');
        checkTextbox('[data-testid="cutoff-C+"]', '77', '67');
        checkTextbox('[data-testid="cutoff-C"]', '73', '63');
        checkTextbox('[data-testid="cutoff-C-"]', '70', '60');
        checkTextbox('[data-testid="cutoff-D+"]', '67', '55');
        checkTextbox('[data-testid="cutoff-D"]', '60', '50');

        // Ensure tables can be added to and removed from
        // TODO: add checks for other tables once features are implemented
        cy.get('[data-testid="manual-grading-user-id"]').type('adamsg');
        cy.get('[data-testid="manual-grading-grade"]').select('A');
        cy.get('[data-testid="manual-grading-note"]').type('MESSAGE');
        cy.get('[data-testid="manual-grading-submit"]').click();
        cy.get('[data-testid="manual-grading-table-body"] > tr > td').as('manual-grading-table-elements');
        cy.get('@manual-grading-table-elements').eq(0).should('contain', 'adamsg');
        cy.get('@manual-grading-table-elements').eq(1).should('contain', 'A');
        cy.get('@manual-grading-table-elements').eq(2).should('contain', 'MESSAGE');
        cy.get('@manual-grading-table-elements').eq(3).find('a').click();

        cy.get('[data-testid="plagiarism"]').should('be.visible'); // Visibility not based on checkbox
        cy.get('[data-testid="plagiarism-user-id"]').type('adamsg');
        cy.get('[data-testid="plagiarism-gradeable-id"]').select('numeric');
        cy.get('[data-testid="plagiarism-marks"]').type('1');
        cy.get('[data-testid="plagiarism-submit"]').click();
        cy.get('[data-testid="plagiarism-table-body"] > tr > td').as('plagiarism-table-elements');
        cy.get('@plagiarism-table-elements').eq(0).should('contain', 'adamsg');
        cy.get('@plagiarism-table-elements').eq(1).should('contain', 'numeric');
        cy.get('@plagiarism-table-elements').eq(2).should('contain', '1');
        cy.get('@plagiarism-table-elements').eq(3).find('a').click();
    });
    it('Upload Manual Customization', () => {
        // Upload manual customization
        cy.get('[data-testid="btn-upload-customization"]').should('exist');
        cy.get('[data-testid="config-upload"]').should('exist');
        // TODO: select file using the Upload button instead of force clicking a hidden element
        cy.get('[data-testid="config-upload"]').selectFile('cypress/fixtures/manual_customization.json', { force: true });
        // Ensure that elements requiring a manual_customization.json appear
        cy.get('[data-testid="ask-which-customization"]').should('not.be.hidden');

        // Ensure radio input works correctly with manual customization selected initially
        cy.get('[data-testid="manual-customization-option"]').should('be.checked');
        cy.get('[data-testid="gui-customization-option"]').check();
        cy.get('[data-testid="manual-customization-option"]').should('not.be.checked');
        cy.get('[data-testid="gui-customization-option"]').should('be.checked');

        // Ensure manual_customization and gui_customization can be downloaded
        // TODO: expand the expected values of this test
        cy.get('[data-testid="btn-download-manual-customization"]').click();
        cy.readFile('cypress/downloads/manual_customization.json').then((manual_json) => {
            expect(manual_json.display).to.exist;
            expect(manual_json.gradeables).to.exist;
        });
        cy.get('[data-testid="btn-download-gui-customization"]').click();
        cy.readFile('cypress/downloads/gui_customization.json').then((gui_json) => {
            expect(gui_json.display).to.exist;
            expect(gui_json.display_benchmark).to.exist;
            expect(gui_json.section).to.exist;
            expect(gui_json.messages).to.exist;
            expect(gui_json.final_cutoff).to.exist;
            expect(gui_json.gradeables).to.exist;
            expect(gui_json.plagiarism).to.exist;
            expect(gui_json.manual_grade).to.exist;
            expect(gui_json.benchmark_percent).to.exist;
        });
    });
    it('Build Rainbow Grades and View Table', () => {
        // Add grades to numeric gradeable
        const gradesfile = 'cypress/fixtures/rainbowgrades_ci_numeric.csv';
        cy.visit(['testing', 'gradeable', 'numeric', 'grading']);
        cy.get('#csvUpload').selectFile(gradesfile, { action: 'drag-drop' });
        cy.on('window:confirm', () => true);
        cy.visit(['testing', 'gradeable', 'numeric', 'quick_link?action=open_grading_now']);
        cy.visit(['testing', 'gradeable', 'numeric', 'quick_link?action=release_grades_now']);

        cy.visit(['testing', 'reports', 'rainbow_grades_customization']);
        cy.get('[data-testid="display-grade-summary"]').should('be.visible'); // Ensure page is loaded

        cy.get('[data-testid="display-grade-summary"]').check();
        cy.get('[data-testid="display-grade-details"]').check();
        cy.get('[data-testid="display-benchmarks-average"]').check();
        cy.get('[data-testid="display-benchmarks-stddev"]').check();
        cy.get('[data-testid="display-benchmarks-perfect"]').check();

        // Generate grade summaries
        cy.intercept('GET', buildUrl(['testing', 'reports', 'summaries'])).as('generate-grade-summaries');
        cy.get('[data-testid="grade-summaries-button"]').click();
        cy.wait('@generate-grade-summaries', { timeout: 30000 });

        cy.visit(['testing', 'reports', 'rainbow_grades_customization']);
        cy.get('[data-testid="btn-build-customization"]').click();
        cy.get('[data-testid="save-status"]', { timeout: 30000 }).should('contain', 'Rainbow grades successfully generated!');
        cy.visit(['testing', 'grades']);
        ['USERNAME', 'NUMERIC ID', 'AVERAGE', 'STDDEV', 'PERFECT'].forEach((fields) => {
            cy.get('[data-testid="rainbow-grades"]').should('contain', fields);
        });
        cy.get('[data-testid="rainbow-grades"]').should('contain', 'Information last updated');
        ['aphacker', 'bitdiddle', 'student'].forEach((username) => {
            cy.logout();
            cy.login(username);
            cy.visit(['testing', 'grades']);
            if (username === 'aphacker') {
                checkRainbowGrades('aphacker', 121238953, 'Alyssa P', 'Hacker');
                checkRainbowGradesOption();
            }
            else if (username === 'bitdiddle') {
                checkRainbowGrades('bitdiddle', 141574736, 'Ben', 'Bitdiddle');
                checkRainbowGradesOption();
            }
            else if (username === 'student') {
                checkRainbowGrades('student', 410853871, 'Joe', 'Student');
                checkRainbowGradesOption();
            }
        });

        // turn off rainbow grades and view pages
        cy.logout();
        cy.login('instructor2');
        cy.visit(['testing', 'config']);
        cy.get('[data-testid="display-rainbow-grades-summary"]').uncheck();
        cy.get('[data-testid="display-rainbow-grades-summary"]').should('not.be.checked');
        cy.visit(['testing', 'grades']);

        // rainbow grades should be visible to the instructor
        cy.get('[data-testid="rainbow-grades"]').should('not.contain.text', 'No grades are available...');
        cy.get('[data-testid="rainbow-grades-not-active-banner"]').should('be.visible');

        // rainbow grades should not be visible to the student
        cy.logout();
        cy.login('student');
        cy.visit(['testing', 'grades']);
        cy.get('[data-testid="popup-message"]').should('contain.text', 'Rainbow Grades are not enabled for this course.');
    });
});
describe('Test Automatic Nightly Processing for Rainbow Grades', () => {
    it('should be toggled on by default with error message', () => {
        cy.login('instructor');
        cy.visit(['sample', 'config']);
        cy.get('[data-testid="auto-rainbow-grades"]').as('nightly-processing-checkbox');
        cy.get('[data-testid="customization-exists-warning"]').as('warning-message');

        // Ensure Nightly Processing is on by default
        cy.get('@nightly-processing-checkbox').should('be.checked');

        // Ensure Nightly Processing warning only exists when Nightly Processing is on and there is no customization.json
        cy.window().its('customizationExists').then((customizationExists) => {
            // TODO: delete customization.json so that both possibilities are examined
            if (customizationExists === true) {
                cy.get('@warning-message').should('not.be.visible');
            }
            else {
                cy.get('@warning-message').should('be.visible');
            }
        });
        cy.get('@nightly-processing-checkbox').uncheck();
        cy.get('@warning-message').should('not.be.visible');
        cy.get('@nightly-processing-checkbox').check();
    });
});
const checkCheckbox = (testId) => {
    cy.get(testId).as('checkbox');
    cy.get('@checkbox').check();
    cy.get('@checkbox').should('be.checked');
    cy.get('@checkbox').uncheck();
    cy.get('@checkbox').should('not.be.checked');
    cy.get('@checkbox').check();
    cy.get('@checkbox').should('be.checked');
};
const checkTextbox = (testId, expectedInitial, input) => {
    cy.get(testId).as('textbox');
    cy.get('@textbox').should('have.value', expectedInitial);
    cy.get('@textbox').clear();
    cy.get('@textbox').type(input);
    cy.get('@textbox').should('have.value', input);
    cy.get('@textbox').clear();
    if (expectedInitial !== '') {
        cy.get('@textbox').type(expectedInitial);
    }
    cy.get('@textbox').should('have.value', expectedInitial);
};
const checkRainbowGrades = (username, numericId, givenName, familyName) => {
    [username, numericId, givenName, familyName].forEach((value) => {
        cy.get('[data-testid="rainbow-grades"]').should('contain', value);
    });
};
const checkRainbowGradesOption = () => {
    ['USERNAME', 'NUMERIC ID', 'GIVEN', 'FAMILY', 'OVERALL', 'AVERAGE', 'STDDEV', 'PERFECT'].forEach((element) => {
        cy.get('[data-testid="rainbow-grades"]').should('contain', element);
    });
};
const reset = () => {
    cy.get('[data-testid="display-grade-summary"]').uncheck();
    cy.get('[data-testid="display-grade-details"]').uncheck();
    cy.get('[data-testid="display-exam-seating"]').uncheck();
    cy.get('[data-testid="display-section"]').uncheck();
    cy.get('[data-testid="display-messages"]').uncheck();
    cy.get('[data-testid="display-warning"]').uncheck();
    cy.get('[data-testid="display-final-grade"]').uncheck();
    cy.get('[data-testid="display-final-cutoff"]').uncheck();
    cy.get('[data-testid="display-instructor-notes"]').uncheck();
    cy.get('[data-testid="display-benchmarks-average"]').uncheck();
    cy.get('[data-testid="display-benchmarks-stddev"]').uncheck();
    cy.get('[data-testid="display-benchmarks-perfect"]').uncheck();
    cy.get('[data-testid="display-benchmarks-lowest_a-"]').uncheck();
    cy.get('[data-testid="display-benchmarks-lowest_b-"]').uncheck();
    cy.get('[data-testid="display-benchmarks-lowest_c-"]').uncheck();
    cy.get('[data-testid="display-benchmarks-lowest_d"]').uncheck();
};
