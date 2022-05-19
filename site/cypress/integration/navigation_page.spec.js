import {getCurrentSemester} from '../support/utils.js';

function validate_navigation_page_sections(sections) {
    const section_keys = Object.keys(sections);
    cy.get('.course-section-heading').should('have.length', section_keys.length);
    cy.get('.course-section-heading').each(($el, index) => {
        cy.wrap($el).invoke('attr', 'id').should('equal', section_keys[index]);
        cy.get(`#${section_keys[index]}-section`).find('.gradeable-row').should('have.length', sections[section_keys[index]]);
    });
}

describe('navigation page', () => {
    before(() => {
        cy.visit('/');
        cy.wait(500);
        cy.viewport(1920,1200);
    });

    it('should show instructor content for instructor', () => {
        cy.login('instructor');
        cy.visit(`/courses/${getCurrentSemester()}/sample`);

        const sections = {
            future: 4,
            beta: 3,
            open: 5,
            closed: 3,
            items_being_graded: 9,
            graded: 10,
        };
        const gradeable_id = 'future_no_tas_homework';

        cy.get('.gradeable-row').each(($el) => {
            cy.wrap($el).find('.course-button').should('have.length', 4);
        });
        validate_navigation_page_sections(sections);

        cy.get(`#${gradeable_id}`).find('a[href*="quick_link?action=open_ta_now"]').then(($el) => {
            const el = cy.wrap($el);
            el.find('.subtitle').should('contain.text', 'OPEN TO TAS NOW');
            el.click();

            sections['future']--;
            sections['beta']++;
            validate_navigation_page_sections(sections);
        });

        cy.get(`#${gradeable_id}`).find('a[href*="quick_link?action=open_students_now"]').then(($el) => {
            const el = cy.wrap($el);
            el.find('.subtitle').should('contain.text', 'OPEN NOW');
            el.click();

            sections['beta']--;
            sections['open']++;
            validate_navigation_page_sections(sections);
        });

        cy.get(`#${gradeable_id}`).find('a[onclick*="quick_link?action=close_submissions"]').then(($el) => {
            const el = cy.wrap($el);
            el.find('.subtitle').should('contain.text', 'CLOSE SUBMISSIONS NOW');
            el.click();
        });

        cy.get('#close-submissions-form').find('input[value="Close Submissions"]').click().then(() => {
            sections['open']--;
            sections['closed']++;
            validate_navigation_page_sections(sections);
        });

        cy.get(`#${gradeable_id}`).find('a[href*="quick_link?action=open_grading_now"]').then(($el) => {
            const el = cy.wrap($el);
            el.find('.subtitle').should('contain.text', 'OPEN TO GRADING NOW');
            el.click();

            sections['closed']--;
            sections['items_being_graded']++;
            validate_navigation_page_sections(sections);
        });

        cy.get(`#${gradeable_id}`).find('a[href*="quick_link?action=release_grades_now"]').then(($el) => {
            const el = cy.wrap($el);
            el.find('.subtitle').should('contain.text', 'RELEASE GRADES NOW');
            el.click();

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
            cy.get(id).focus().should('have.class', 'active').clear().type(`${value}{enter}`);
            // Must wait for change to save or else next date input could get mangled
            cy.get('#save_status').should('contain.text', 'All Changes Saved');
        }
        cy.get('a[id="nav-sidebar-submitty"]').click().then(() => {
            sections['graded']--;
            sections['future']++;
        });

        cy.get(`#${gradeable_id}`).find('a[href*="quick_link?action=open_ta_now"]').should('have.length', 1).find('.subtitle').should('contain.text', 'OPEN TO TAS NOW');
        validate_navigation_page_sections(sections);
    });

    it('should show full access grader content for ta', () => {
        cy.login('ta');
        cy.visit(`/courses/${getCurrentSemester()}/sample`);

        const sections = {
            beta: 3,
            open: 5,
            closed: 3,
            items_being_graded: 9,
            graded: 10,
        };
        validate_navigation_page_sections(sections);
        cy.get('.gradeable-row').each(($el) => {
            cy.wrap($el).find('.course-button').should('have.length', 3);
        });
    });

    it('should show student content for student', () => {
        cy.login('student');
        cy.visit(`/courses/${getCurrentSemester()}/sample`);

        const sections = {
            open: 5,
            closed: 3,
            items_being_graded: 5,
            graded: 8,
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
