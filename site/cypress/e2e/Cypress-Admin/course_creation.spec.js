describe('Test cases revolving around course creation through UI', () => {
    let valid_course_code;

    before(() => {
        valid_course_code = `${Math.random().toString(36).substring(2, 8)}_course_creation_test`;
    });

    beforeEach(() => {
        cy.login();
    });

    it('Should see all default courses on home page', () => {
        // List of all default courses that should be on home page for instructor
        const default_courses = ['blank', 'development', 'sample', 'tutorial', 'testing', 'archived'];
        for (const default_course of default_courses) {
            cy.get(`[data-testid=${default_course}-button]`).should('exist');
        }
    });

    it('Should fail to make a course when using an invalid course code', () => {
        // Requirements for course code: only lowercase letters (a-z), digits (0-9), and the underscore character.
        const invalid_course_codes = ['UPPER', 'special!@#', 'sp a ces', 'hyph-en', '_punc.,', 'other%`~'];
        cy.visit('/home/courses/new');
        for (const invalid_course_code of invalid_course_codes) {
            cy.get('[data-testid="course-title-input"]').type(invalid_course_code);
            cy.get('[data-testid="course-group-select"]').select('sample_tas_www');
            cy.get('[data-testid="create-course-submit"]').click();
            cy.get('[data-testid="popup-message"]').should('contain', 'The course code must contain only lowercase letters (a-z), digits (0-9), and the underscore character.');
        }
    });

    it('Should see course on home page once it is created', () => {
        cy.visit('/home/courses/new');
        cy.get('[data-testid="course-title-input"]').type(valid_course_code);
        cy.get('[data-testid="course-group-select"]').select('sample_tas_www');
        cy.get('[data-testid="create-course-submit"]').click();
        // eslint-disable-next-line no-restricted-syntax
        cy.waitAndReloadUntil(() => {
            return cy.get('body').then(($body) => {
                return $body.find(`[data-testid="${valid_course_code}-button"]`).length > 0;
            });
        }, 5000, 100);
    });

    it('Archived course should be moved to archive section on home page', () => {
        // archive the course
        cy.visit([valid_course_code, 'config']);
        cy.get('[data-testid="course-archive"]').click();
        cy.visit('/home');
        // new course should be within archived section
        cy.get('[data-testid="archived_courses-button-list"]')
            .find(`[data-testid=${valid_course_code}-button]`).should('exist');

        // unarchive the course
        cy.visit([valid_course_code, 'config']);
        cy.get('[data-testid="course-archive"]').click();
        cy.visit('/home');
        // new course should be within unarchived section
        cy.get('[data-testid="unarchived_courses-button-list"]')
            .find(`[data-testid=${valid_course_code}-button]`).should('exist');
    });

    it('Once removed from a course, you should not see that course on the home page', () => {
        // instructor can see the course because they're in it
        cy.get(`[data-testid="${valid_course_code}-button"]`).should('exist');
        cy.logout();

        // remove instructor from the course as submitty-admin
        cy.login('submitty-admin');
        cy.visit([valid_course_code, 'users']);
        // cy.get('.content').find('[data-testid="delete-student-instructor-button"]').scrollIntoView();
        cy.get('[data-testid="delete-student-instructor-button"]').click();
        cy.get('[data-testid="confirm-delete-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain', 'Quinn Instructor has been removed from your course.');
        cy.visit('/home');
        cy.logout();

        // instructor shouldn't see the course on their homepage anymore
        cy.login();
        cy.get(`[data-testid="${valid_course_code}-button"]`).should('not.exist');
    });
});
