import { getFullCurrentSemester, getCurrentSemester } from '../../support/utils';

const openMessage = `The course testing for ${getFullCurrentSemester()} is open to self registration`;
const openMessageFull = `The course Testing Course (testing) for ${getFullCurrentSemester()} is open to self registration`;
const selectMessage = 'You may select below to add yourself to the course.';
const notifiedMessage = 'Your instructor will be notified and can then choose to keep you in the course.';

const no_access_message = "You don't have access to this course.";

describe('Tests for self registering for courses', () => {
    before(() => {
        // Testing course defaults to having self registration enabled, so we need to disable it for the tests.
        cy.login('instructor2');
        cy.visit(['testing', 'config']);
        cy.get('[data-testid="course-name"]').clear();
        cy.get('[data-testid="all-self-registration"]').uncheck();
        cy.get('[data-testid="all-self-registration"]').should('not.be.checked');
        cy.logout();
    });

    after(() => {
        cy.login('instructor2');
        cy.visit(['testing', 'users']);
        cy.get('[data-testid="delete-student-gutmal-button"]').click();
        cy.get('[data-testid="confirm-delete-button"]').click();
        cy.get('[data-testid="popup-message"]').should('contain', 'Leonie Gutmann has been removed from your course.');
        // This wait is necessary due to the JS $.ajax request, as if the logout request is sent too quickly,
        // the login page is sent to the $.ajax request instead of the accurate data. For some reason waiting for an intercepted route
        // does not work here, when it works below.
        // eslint-disable-next-line cypress/no-unnecessary-waiting
        cy.wait(500);
        cy.logout();
    });

    it('Should enable self registration, and allow user to register for courses.', () => {
        cy.login('gutmal');
        cy.get('[data-testid="courses-list"]').should('not.contain', 'Courses Available for Self Registration');
        cy.visit(['testing']);
        cy.get('[data-testid="no-access-message"]').should('contain', no_access_message);
        cy.logout();

        // Test notifications
        cy.login('instructor2');
        cy.visit(['testing', 'notifications', 'settings']);
        cy.get('[data-testid="self-registration"]').should('not.exist');

        cy.visit(['testing', 'config']);
        cy.get('[data-testid="all-self-registration"]').check();
        cy.get('[data-testid="all-self-registration"]').should('be.checked');
        cy.get('[data-testid="default-section-id"]').select('5');
        cy.reload();
        cy.get('[data-testid="default-section-id"]').should('contain', '5');
        cy.visit(['testing', 'notifications', 'settings']);
        cy.get('[data-testid="self-registration"]').should('exist');
        cy.logout();

        // Check instructors view
        cy.login();
        cy.get('[data-testid="courses-header"]').eq(0).should('have.text', 'My Courses');
        cy.get('[data-testid="courses-header"]').eq(1).should('have.text', 'Courses Available for Self Registration');
        cy.get('[data-testid="courses-header"]').eq(2).should('have.text', 'My Archived Courses');
        cy.logout();
        // Check normal view (with no course name)
        cy.login('gutmal');
        cy.visit();
        cy.get('[data-testid="courses-list"').should('contain', 'Courses Available for Self Registration');
        cy.get('[data-testid="testing-button"]').click();
        cy.get('[data-testid="no-access-message"]').should('contain', openMessage)
            .and('contain', selectMessage)
            .and('contain', notifiedMessage);
        cy.logout();
        // Set course name
        cy.login('instructor2');
        cy.visit(['testing', 'config']);
        cy.get('[data-testid="course-name"').type('Testing Course{enter}');
        cy.logout();
        // Check with course name
        cy.login('gutmal');
        cy.visit();
        cy.get('[data-testid="courses-list"').should('contain', 'Courses Available for Self Registration');
        cy.get('[data-testid="testing-button"]').click();
        cy.get('[data-testid="no-access-message"]').should('contain', openMessageFull)
            .and('contain', selectMessage)
            .and('contain', notifiedMessage);
        cy.get('[data-testid="register-button"]').click();
        cy.get('[data-testid="open_homework"]').should('exist');
        cy.visit();
        cy.get('[data-testid="testing-button"]').should('contain', 'Section 5');
        cy.logout();
        cy.login('instructor2');
        cy.visit(['testing', 'users']);
        cy.get('[data-testid="edit-student-gutmal-button"]').click();
        cy.get('[data-testid="registration-section-dropdown"]').select('Not Registered');
        cy.get('[data-testid="submit-user-form-button"]').click();
        cy.intercept(
            {
                url: `/courses/${getCurrentSemester()}/testing/user_information`,
                times: 1,
            },
        ).as('userInformation');
        cy.get('[data-testid="popup-message"]').should('contain', "User 'gutmal' updated");
        cy.wait('@userInformation');
        cy.logout();
        cy.login('gutmal');
        cy.visit();
        cy.get('[data-testid="courses-list"').should('contain', 'Courses Available for Self Registration');
        cy.get('[data-testid="testing-button"]').click();
        cy.get('[data-testid="no-access-message"]').should('contain', openMessageFull)
            .and('contain', selectMessage)
            .and('contain', notifiedMessage);
        cy.get('[data-testid="register-button"]').click();
        cy.get('[data-testid="open_homework"]').should('exist');
        cy.visit();
        cy.get('[data-testid="testing-button"]').should('contain', 'Section 5');
        cy.logout();
    });
});
