function validate_navigation_page_sections(sections) {
    const section_keys = Object.keys(sections);
    cy.get('.course-section-heading').should('have.length', section_keys.length);
    cy.get('.course-section-heading').each(($el, index) => {
        cy.wrap($el).invoke('attr', 'id').should('equal', section_keys[index]);
        cy.get(`#${section_keys[index]}-section`).find('.gradeable-row').should('have.length', sections[section_keys[index]]);
    });
}

const white = 'rgb(255, 255, 255)';
const blue = 'rgb(0, 99, 152)';
const red = 'rgb(220, 53, 69)';
const green = 'rgb(95, 183, 96)';
const buttonOrder = ['team-btn', 'submit-btn', 'grade-btn', 'quick-link-btn'];

function checkButtons(gradeableID, buttonText, buttonColor) {
    // assert that the course does not exist
    if (buttonText === null && buttonColor === null) {
        cy.get(`[data-testid="${gradeableID}"]`).should('not.exist');
    }
    else {
        for (let i = 0; i < buttonOrder.length; i++) {
            if (buttonText[i]) {
                cy.get(`[data-testid="${gradeableID}"]`).find(`[data-testid="${buttonOrder[i]}"]`).should('contain', buttonText[i]);
                cy.get(`[data-testid="${gradeableID}"]`).find(`[data-testid="${buttonOrder[i]}"]`).should('have.css', 'background-color', buttonColor[i]);
            }
            else {
                // assert that the button does not exist
                cy.get(`[data-testid="${gradeableID}"]`).find(`[data-testid="${buttonOrder[i]}"]`).should('not.exist');
            }
        }
    }
}

describe('tests navigation buttons for each level of access', () => {
    // Future (No TAs) Homework, Future(TAs) Homework, Open Homework, Open Team Homework, Closed Homework, Closed Team Homework
    // grading homework, Grading Lab
    // Grade Released Homework, Grade Released Test,
    it('should display instructor buttons correctly', () => {
        cy.login('instructor');
        cy.visit(['sample']);

        checkButtons('future_no_tas_homework',
            [null, 'ALPHA SUBMIT', 'PREVIEW GRADING', 'OPEN TO TAS NOW'],
            [null, white, white, blue],
        );
        checkButtons('future_tas_homework',
            [null, 'BETA SUBMIT', 'PREVIEW GRADING', 'OPEN NOW'],
            [null, white, white, blue],
        );
        checkButtons('open_homework',
            [null, 'SUBMIT', 'PREVIEW GRADING', 'CLOSE SUBMISSIONS NOW'],
            [null, blue, white, white],
        );
        checkButtons('open_team_homework',
            ['CREATE TEAM', 'MUST BE ON A TEAM TO SUBMIT', 'PREVIEW GRADING', 'CLOSE SUBMISSIONS NOW'],
            [blue, blue, white, white],
        );
        checkButtons('closed_homework',
            [null, 'LATE SUBMIT', 'PREVIEW GRADING', 'OPEN TO GRADING NOW'],
            [null, red, white, blue],
        );
        checkButtons('closed_team_homework',
            ['CREATE TEAM', 'MUST BE ON A TEAM TO SUBMIT', 'PREVIEW GRADING', 'OPEN TO GRADING NOW'],
            [red, red, white, blue],
        );
        checkButtons('grading_homework',
            [null, 'OVERDUE SUBMISSION', 'GRADE', 'RELEASE GRADES NOW'],
            [null, red, blue, blue],
        );
        checkButtons('grading_lab',
            [null, null, 'GRADE', 'RELEASE GRADES NOW'],
            [null, null, white, blue],
        );
        checkButtons('grades_released_homework_autota',
            [null, 'VIEW GRADE', 'REGRADE', null],
            [null, green, white, null],
        );
        checkButtons('grades_released_homework',
            [null, 'OVERDUE SUBMISSION', 'REGRADE', null],
            [null, red, white, null],
        );
        checkButtons('grades_released_lab',
            [null, null, 'REGRADE', null],
            [null, null, white, null],
        );
    });

    it('should display TA buttons correctly', () => {
        cy.login('ta');
        cy.visit(['sample']);

        checkButtons('future_no_tas_homework', null, null);
        checkButtons('future_tas_homework',
            [null, 'BETA SUBMIT', 'PREVIEW GRADING', null],
            [null, white, white, null],
        );
        checkButtons('open_homework',
            [null, 'SUBMIT', 'PREVIEW GRADING', null],
            [null, blue, white, null],
        );
        checkButtons('open_team_homework',
            ['CREATE TEAM', 'MUST BE ON A TEAM TO SUBMIT', 'PREVIEW GRADING', null],
            [blue, blue, white, null],
        );
        checkButtons('closed_homework',
            [null, 'LATE SUBMIT', 'PREVIEW GRADING', null],
            [null, red, white, null],
        );
        checkButtons('closed_team_homework',
            ['CREATE TEAM', 'MUST BE ON A TEAM TO SUBMIT', 'PREVIEW GRADING', null],
            [red, red, white, null],
        );
        checkButtons('grading_homework',
            [null, 'OVERDUE SUBMISSION', 'GRADE', null],
            [null, red, blue, null],
        );
        checkButtons('grading_lab',
            [null, null, 'GRADE', null],
            [null, null, white, null],
        );
        checkButtons('grades_released_homework_autota',
            [null, 'OVERDUE SUBMISSION', 'REGRADE', null],
            [null, red, white, null],
        );
        checkButtons('grades_released_homework',
            [null, 'OVERDUE SUBMISSION', 'REGRADE', null],
            [null, red, white, null],
        );
        checkButtons('grades_released_lab',
            [null, null, 'REGRADE', null],
            [null, null, white, null],
        );
    });

    it('should display student buttons correctly', () => {
        cy.login('student');
        cy.visit(['sample']);

        checkButtons('future_no_tas_homework', null, null);
        checkButtons('future_tas_homework', null, null);
        checkButtons('open_homework',
            [null, 'SUBMIT', null, null],
            [null, blue, null, null],
        );
        checkButtons('open_team_homework',
            ['MANAGE TEAM', 'SUBMIT', null, null],
            [blue, blue, null, null],
        );
        checkButtons('closed_homework',
            [null, 'LATE SUBMIT', null, null],
            [null, red, white, null],
        );
        checkButtons('closed_team_homework',
            ['VIEW TEAM', 'LATE SUBMIT', null, null],
            [white, red, null, null],
        );
        checkButtons('grading_homework',
            [null, 'OVERDUE SUBMISSION', null, null],
            [null, red, null, null],
        );
        checkButtons('grading_lab', null, null);
        checkButtons('grades_released_homework_autota',
            [null, 'OVERDUE SUBMISSION', null, null],
            [null, red, null, null],
        );
        checkButtons('grades_released_homework',
            [null, 'OVERDUE SUBMISSION', null, null],
            [null, red, null, null],
        );
        checkButtons('grades_released_lab', null, null);
    });

    ['instructor', 'ta', 'grader', 'student'].forEach((user) => {
        it(`should show the correct alert message for team homework for ${user}`, () => {
            cy.login(user);
            cy.visit(['sample']);
            cy.get('[data-testid="open_team_homework"]').find('[data-testid="submit-btn"]').click();
            // Capture and verify the alert text
            cy.on('window:alert', (alertText) => {
                expect(alertText).to.equal('You must be on a team to submit to this gradeable.');
            });
        });
    });
});

describe('navigation page', () => {
    it('should show instructor content for instructor', () => {
        cy.login('instructor');
        cy.visit(['sample']);

        const sections = {
            future: 5,
            beta: 3,
            open: 8,
            closed: 4,
            items_being_graded: 9,
            graded: 11,
        };
        const gradeable_id = 'future_no_tas_homework';

        cy.get('.gradeable-row').each(($el) => {
            cy.wrap($el).find('.course-button').should('have.length', 4);
        });
        validate_navigation_page_sections(sections);

        cy.get(`#${gradeable_id}`).find('a[href*="quick_link?action=open_ta_now"]').then(($el) => {
            (cy.wrap($el)).find('.subtitle').should('contain.text', 'OPEN TO TAS NOW');
            (cy.wrap($el)).click();

            sections['future']--;
            sections['beta']++;
            validate_navigation_page_sections(sections);
        });

        cy.get(`#${gradeable_id}`).find('a[href*="quick_link?action=open_students_now"]').then(($el) => {
            (cy.wrap($el)).find('.subtitle').should('contain.text', 'OPEN NOW');
            (cy.wrap($el)).click();

            sections['beta']--;
            sections['open']++;
            validate_navigation_page_sections(sections);
        });

        cy.get(`#${gradeable_id}`).find('a[onclick*="quick_link?action=close_submissions"]').then(($el) => {
            (cy.wrap($el)).find('.subtitle').should('contain.text', 'CLOSE SUBMISSIONS NOW');
            (cy.wrap($el)).click();
        });

        cy.get('#close-submissions-form').find('input[value="Close Submissions"]').as('close-submissions-form-input');
        cy.get('@close-submissions-form-input').click();
        cy.get('@close-submissions-form-input').then(() => {
            sections['open']--;
            sections['closed']++;
            validate_navigation_page_sections(sections);
        });

        cy.get(`#${gradeable_id}`).find('a[href*="quick_link?action=open_grading_now"]').then(($el) => {
            (cy.wrap($el)).find('.subtitle').should('contain.text', 'OPEN TO GRADING NOW');
            (cy.wrap($el)).click();

            sections['closed']--;
            sections['items_being_graded']++;
            validate_navigation_page_sections(sections);
        });

        cy.get(`#${gradeable_id}`).find('a[href*="quick_link?action=release_grades_now"]').then(($el) => {
            (cy.wrap($el)).find('.subtitle').should('contain.text', 'RELEASE GRADES NOW');
            (cy.wrap($el)).click();

            sections['items_being_graded']--;
            sections['graded']++;
            validate_navigation_page_sections(sections);
        });

        cy.get(`#${gradeable_id}`).find(`a[href*="gradeable/${gradeable_id}/update"]`).click();
        cy.get('form[id="gradeable-form"]').find('div.tab-bar-wrapper').find('a').contains('Dates').click();
        const inputs = [
            ['#date_released', '9998-12-31 23:59:59'],
            ['#date_grade_due', '9998-12-31 23:59:59'],
            ['#date_grade', '9997-12-31 23:59:59'],
            ['#date_due', '9996-12-31 23:59:59'],
            ['#date_submit', '9995-12-31 23:59:59'],
            ['#date_ta_view', '9994-12-31 23:59:59'],
        ];
        for (const [id, value] of inputs) {
            cy.get(id).focus();
            cy.get(id).should('have.class', 'active').clear();
            cy.get(id).type(`${value}{enter}`);
            // Must wait for change to save or else next date input could get mangled
            cy.get('#save_status').should('contain.text', 'All Changes Saved');
        }
        cy.get('a[id="nav-sidebar-submitty"]').as('nav-sidebar-submitty');
        cy.get('@nav-sidebar-submitty').click();
        cy.get('@nav-sidebar-submitty').then(() => {
            sections['graded']--;
            sections['future']++;
        });

        cy.get(`#${gradeable_id}`).find('a[href*="quick_link?action=open_ta_now"]').should('have.length', 1).find('.subtitle').should('contain.text', 'OPEN TO TAS NOW');
        validate_navigation_page_sections(sections);
    });

    it('should show full access grader content for ta', () => {
        cy.login('ta');
        cy.visit(['sample']);

        const sections = {
            beta: 3,
            open: 8,
            closed: 4,
            items_being_graded: 9,
            graded: 11,
        };
        validate_navigation_page_sections(sections);
        cy.get('.gradeable-row').each(($el) => {
            cy.wrap($el).find('.course-button').should('have.length', 3);
        });
    });

    it('should show student content for student', () => {
        cy.login('student');
        cy.visit(['sample']);

        const sections = {
            open: 8,
            closed: 4,
            items_being_graded: 5,
            graded: 9,
        };
        validate_navigation_page_sections(sections);

        let found = false;
        cy.get('.gradeable-row').each(($el) => {
            cy.log($el.text());
            let count = 2;
            const text = $el.text();
            if (text.includes('Peer') && !text.includes('Open')) {
                if (text.includes('Closed') && !found) {
                    found = true;
                }
                else {
                    count = 3;
                }
            }
            cy.wrap($el).find('.course-button').should('have.length', count);
        });
    });
});

describe('locked gradeables', () => {
    ['instructor', 'ta', 'grader', 'student'].forEach((user) => {
        it(`should show the locked gradeable for ${user} and message`, () => {
            cy.login(user);
            cy.visit(['sample']);
            cy.get('[data-testid="locked_homework"]').should('exist');
            cy.get('[data-testid="locked_homework"]').find('[data-testid="submit-btn"]').then(($button) => {
                // Get the text from the onclick attribute
                const onclickText = $button.attr('onclick'); // e.g., alert('Please complete Prerequisite.')
                // Extract the prerequisite text
                const prerequisite = onclickText.match(/Please complete (.*?)\./)[1]; // Extracts 'Prerequisite'
                cy.on('window:alert', (alertText) => {
                    // Validate the alert text
                    expect(alertText).to.equal(`Please complete ${prerequisite}.`);
                });
                // Trigger the button click
                cy.wrap($button).click();
            });
        });
    });
    

    it('should show the locked gradeable for the TA and message', () => {
        cy.login('ta');
        cy.visit(['sample']);
        cy.get('[data-testid="locked_homework"]').should('exist');
        cy.get('[data-testid="locked_homework"]').find('[data-testid="submit-btn"]').then(($button) => {
            // Get the text from the onclick attribute
            const onclickText = $button.attr('onclick'); // e.g., alert('Please complete Prerequisite.')
            // Extract the prerequisite text
            const prerequisite = onclickText.match(/Please complete (.*?)\./)[1]; // Extracts 'Prerequisite'
            cy.on('window:alert', (alertText) => {
                // Validate the alert text
                expect(alertText).to.equal(`Please complete ${prerequisite}.`);
            });
            // Trigger the button click
            cy.wrap($button).click();
        });
    });
});
