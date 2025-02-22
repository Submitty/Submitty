import { buildUrl } from '../../support/utils';

const title1 = 'Attachment contains secret';
const title2 = 'Different Levels& display order';
const title3 = 'Simple C++ threading example';

const upduckPost = (thread_title, thread_number = 0, num_ducks = 0) => {
    cy.get('[data-testid="thread-list-item"]').contains(thread_title).click();
    cy.get('[data-testid="create-post-head"]').should('contain', thread_title);
    cy.get('[data-testid="like-count"]').eq(thread_number).should('have.text', num_ducks);
    cy.get('[data-testid="upduck-button"]').eq(thread_number).click();
    cy.wait('@upduck', { responseTimeout: 15000 });
    cy.get('[data-testid="like-count"]').eq(thread_number).should('have.text', num_ducks + 1);
};

const removeUpduck = (thread_title, thread_number = 0, num_ducks = 1) => {
    cy.get('[data-testid="thread-list-item"]').contains(thread_title).click();
    cy.get('[data-testid="create-post-head"]').should('contain', thread_title);
    cy.get('[data-testid="like-count"]').eq(thread_number).should('have.text', num_ducks);
    cy.get('[data-testid="upduck-button"]').eq(thread_number).click();
    cy.wait('@upduck', { responseTimeout: 15000 });
    cy.get('[data-testid="like-count"]').eq(thread_number).should('have.text', num_ducks - 1);
};

const checkStaffUpduck = (title, visible, thread_number = 0) => {
    cy.get('[data-testid="thread-list-item"]').contains(title).click();
    cy.get('[data-testid="create-post-head"]').should('contain', title);
    cy.get('[data-testid="instructor-like"]').eq(thread_number).should(visible);
};

const checkThreadduck = (title, ducks) => {
    cy.get('[data-testid="thread-list-item"]').contains(title).parents('[data-testid="thread_box"]').find('[data-testid="thread-like-count"]').should('have.text', ducks);
};

const checkStatsUpducks = (fullName, numUpducks) => {
    // Check the stats page for a user with fullName and
    // number of upducks numUpducks
    cy.get('[data-testid="more-dropdown"]').click();
    cy.get('[data-testid="forum_stats"]').click();
    cy.get('[data-testid="user-stat"]').contains(fullName).siblings('[data-testid="upduck-stat"]').should('contain.text', numUpducks);
    cy.get('[title="Back to threads"]').click();
};

const staffUpduckPost = (user, thread_title) => {
    checkStaffUpduck(thread_title, 'be.not.visible');
    upduckPost(thread_title);
    checkStaffUpduck(thread_title, 'be.visible');

    if (user === 'instructor') {
        // upduck reply
        const reply = 1;
        if (thread_title === title1) {
            checkStaffUpduck(thread_title, 'be.not.visible', reply);
            upduckPost(thread_title, reply, 0);
            checkStaffUpduck(thread_title, 'be.visible', reply);
        }
        else if (thread_title === title2) {
            checkStaffUpduck(thread_title, 'be.visible', reply);
            upduckPost(thread_title, reply, 1);
            checkStaffUpduck(thread_title, 'be.visible', reply);
        }
        else {
            checkStaffUpduck(thread_title, 'be.visible', reply);
            upduckPost(thread_title, reply, 2);
            checkStaffUpduck(thread_title, 'be.visible', reply);
        }
    }
    // TA has different numbers than instructors
    else if (user === 'ta') {
        const reply = 1;
        // upduck 2nd and 3rd post's reply, student already upducked the 3rd post's reply
        if (thread_title === title2) {
            upduckPost(thread_title, reply, 0);
            checkStaffUpduck(thread_title, 'be.visible', reply);
        }
        else if (thread_title === title3) {
            upduckPost(thread_title, reply, 1);
            checkStaffUpduck(thread_title, 'be.visible', reply);
        }
        // remove upduck from parent post and make sure staff upduck is not visible
        removeUpduck(thread_title);
        checkStaffUpduck(thread_title, 'be.not.visible');
    }
};

const studentUpduckPost = (thread_title) => {
    checkStaffUpduck(thread_title, 'be.not.visible');
    upduckPost(thread_title);
    checkStaffUpduck(thread_title, 'be.not.visible');
    removeUpduck(thread_title);
    // upduck reply, do not remove yet, for checking thread sum duck purpose
    if (thread_title === title3) {
        upduckPost(thread_title, 1, 0);
    }
};

describe('Should test upducks relating to students, TAs, and instructors', () => {
    beforeEach(() => {
        cy.intercept('POST', buildUrl(['sample', 'posts', 'likes'])).as('upduck');
    });

    it('Upducking and checking upducks', () => {
        // Student upduck. After the student is done, post #3's first reply has 1 upduck
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
        // thread 1 suppose to have 2 duck, thread 2 suppose to have 3 total ducks, thread 3 suppose to have 4 total ducks
        cy.visit(['sample', 'forum']);
        checkThreadduck(title1, 2);
        checkThreadduck(title2, 3);
        checkThreadduck(title3, 4);

        checkStatsUpducks('Instructor, Quinn', 6);
        checkStatsUpducks('TA, Jill', 2);
        checkStatsUpducks('Student, Joe', 1);
    });

    it('Delete all remaining upducks', () => {
        // instructor has 6, ta has 2, student has 1
        const post = 0, reply = 1;
        cy.login('student');
        cy.visit(['sample', 'forum']);
        removeUpduck(title3, reply, 3);

        cy.logout();
        cy.login('ta');
        cy.visit(['sample', 'forum']);
        removeUpduck(title2, reply, 2);
        removeUpduck(title3, reply, 2);

        cy.logout();
        cy.login('instructor');
        cy.visit(['sample', 'forum']);
        removeUpduck(title1, post, 1);
        removeUpduck(title1, reply, 1);
        removeUpduck(title2, post, 1);
        removeUpduck(title2, reply, 1);
        removeUpduck(title3, post, 1);
        removeUpduck(title3, reply, 1);

        cy.visit(['sample', 'forum']);
        checkThreadduck(title1, 0);
        checkThreadduck(title2, 0);
        checkThreadduck(title3, 0);

        checkStatsUpducks('Instructor, Quinn', 0);
        checkStatsUpducks('TA, Jill', 0);
        checkStatsUpducks('Student, Joe', 0);
    });
});
