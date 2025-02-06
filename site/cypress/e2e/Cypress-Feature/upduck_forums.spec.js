const title1 = 'Attachment contains secret';
const title2 = 'Different Levels& display order';
const title3 = 'Simple C++ threading example';

const upduckPost = (thread_title) => {
    cy.get('[data-testid="thread-list-item"]').contains(thread_title).click();
    cy.get('[data-testid="create-post-head"]').should('contain', thread_title);
    cy.get('[data-testid="like-count"]').first().should('have.text', 0);
    cy.get('[data-testid="upduck-button"]').first().click();
    // eslint-disable-next-line cypress/no-unnecessary-waiting
    cy.wait(1000);
    cy.get('[data-testid="like-count"]', { timeout: 10000 }).first().should('have.text', 1);
};

const upduckReply = (thread_title) => {
    // Upduck the first reply
    cy.get('[data-testid="thread-list-item"]').contains(thread_title).click();
    cy.get('[data-testid="create-post-head"]').should('contain', thread_title);
    cy.get('[data-testid="upduck-button"]').eq(1).click();
    // eslint-disable-next-line cypress/no-unnecessary-waiting
    cy.wait(1000);
};

const checkStatsUpducks = (fullName, numUpducks) => {
    // Check the stats page for a user with fullName and
    // number of upducks numUpducks
    cy.get('[data-testid="more-dropdown"]').click();
    cy.get('#forum_stats').click();
    cy.get('[data-testid="user-stat"]').contains(fullName).siblings('[data-testid="upduck-stat"]').should('contain.text', numUpducks);
    cy.get('[title="Back to threads"]').click();
};

const removeUpduckPost = (thread_title) => {
    cy.get('[data-testid="create-post-head"]').should('contain', thread_title);
    cy.get('[data-testid="like-count"]').first().should('have.text', 1);
    cy.get('[data-testid="upduck-button"]').first().click();
    // wait for duck like to update
    // eslint-disable-next-line cypress/no-unnecessary-waiting
    cy.wait(1000);
    cy.get('[data-testid="like-count"]', { timeout: 10000 }).first().should('have.text', 0);
};

const staffUpduckPost = (user, thread_title) => {
    checkStaffUpduck(thread_title, 'be.not.visible');
    upduckPost(thread_title);
    checkStaffUpduck(thread_title, 'be.visible');

    // Ta will upduck reply in thread 2,3 and instructor will upduck reply in thread 1, 2 and 3
    if (!(user === 'ta' && thread_title === title1)) {
        upduckReply(thread_title);
    }
    if (user !== 'instructor') {
        removeUpduckPost(thread_title);
        checkStaffUpduck(thread_title, 'be.not.visible');
    }
};

const studentUpduckPost = (thread_title) => {
    checkStaffUpduck(thread_title, 'be.not.visible');
    upduckPost(thread_title);
    checkStaffUpduck(thread_title, 'be.not.visible');
    removeUpduckPost(thread_title);
    // upduck reply, do not remove yet, for checking thread sum duck purpose
    if (thread_title === title3) {
        upduckReply(thread_title);
    }
};

const checkStaffUpduck = (title, visible) => {
    cy.get('[data-testid="thread-list-item"]').contains(title).click();
    cy.get('[data-testid="create-post-head"]').should('contain', title);
    cy.get('[data-testid="instructor-like"]').first().should(visible);
};

const checkThreadduck = (title, ducks) => {
    cy.get('[data-testid="thread-list-item"]').contains(title).parents('[data-testid="thread_box"]').find('[data-testid="thread-like-count"]').should('have.text', ducks);
};

describe('Should test upducks relating to students, TAs, and instructors', () => {
    it('Upducking and checking upducks', () => {
        // Student upduck
        cy.login('student');
        cy.visit(['sample', 'forum']);
        studentUpduckPost(title1);
        studentUpduckPost(title2);
        studentUpduckPost(title3);

        // TA upduck
        cy.logout();
        cy.login('ta');
        cy.visit(['sample', 'forum']);
        staffUpduckPost('ta', title1);
        staffUpduckPost('ta', title2);
        staffUpduckPost('ta', title3);

        // Instructor upduck and check the stats page for instructor with 3 upducks
        cy.logout();
        cy.login('instructor');
        cy.visit(['sample', 'forum']);
        staffUpduckPost('instructor', title1);
        staffUpduckPost('instructor', title2);
        staffUpduckPost('instructor', title3);

        // Check thread sum duck
        // thread 1 suppose to have 2 total duck, thread 2 suppose to have 3 total ducks, thread 3 suppose to have 4 total ducks
        cy.login('instructor');
        cy.visit(['sample', 'forum']);
        checkThreadduck(title1, 2);
        checkThreadduck(title2, 3);
        checkThreadduck(title3, 4);

        checkStatsUpducks('Instructor, Quinn', 6);
    });
});
