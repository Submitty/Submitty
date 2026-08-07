import ForumStatsTable from '@/components/forum/ForumStatsTable.vue';
import { mountWithEmitSpy } from '../support/component_test_utils';

const users = [
    {
        userId: 'instructor1',
        familyName: 'Instructor',
        givenName: 'Quinn',
        postCount: 2,
        totalThreads: 1,
        numDeleted: 0,
        totalUpducks: 6,
        posts: [
            { id: 1, timestamp: '7/1 10:00 AM', threadId: 101, threadTitle: 'Thread One', content: 'First post body' },
            { id: 2, timestamp: '7/2 2:30 PM', threadId: 102, threadTitle: 'Thread Two', content: 'Second post body' },
        ],
    },
    {
        userId: 'student1',
        familyName: 'Student',
        givenName: 'Joe',
        postCount: 0,
        totalThreads: 0,
        numDeleted: 1,
        totalUpducks: 1,
        posts: [],
    },
];

describe('ForumStatsTable', () => {
    it('renders a row per user with name and stat values', () => {
        cy.mount(ForumStatsTable, { props: { users } });
        cy.get('[data-testid="user-stat"]').should('have.length', 2);
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', 'Instructor, Quinn');
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', '2'); // post count
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', '1'); // total threads
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', '0'); // deleted
        cy.get('[data-testid="user-stat"]').eq(0).find('[data-testid="upduck-stat"]').should('contain', '6');
    });

    it('shows "No Posts" for users without posts', () => {
        cy.mount(ForumStatsTable, { props: { users } });
        cy.get('[data-testid="user-stat"]').eq(1).should('contain', 'No Posts');
    });

    it('expands and collapses post detail rows on button click', () => {
        cy.mount(ForumStatsTable, { props: { users } });
        cy.get('[data-testid="post-detail-row"]').should('not.exist');
        cy.get('[data-testid="expand-button"]').eq(0).click();
        cy.get('[data-testid="post-detail-row"]').should('have.length', 2);
        cy.get('[data-testid="post-detail-row"]').eq(0).should('contain', 'Thread One');
        cy.get('[data-testid="post-detail-row"]').eq(0).should('contain', 'First post body');
        cy.get('[data-testid="post-detail-row"]').eq(0).should('contain', '7/1 10:00 AM');
        cy.get('[data-testid="expand-button"]').eq(0).click();
        cy.get('[data-testid="post-detail-row"]').should('not.exist');
    });

    it('emits navigate-to-thread with the thread id when a thread title is clicked', () => {
        mountWithEmitSpy(ForumStatsTable, 'navigate-to-thread', { users }, 'navigateHandler');
        cy.get('[data-testid="expand-button"]').eq(0).click();
        cy.get('[data-testid="post-thread-cell"]').eq(1).click();
        cy.get('@navigateHandler').should('have.been.calledOnceWith', { threadId: 102 });
    });

    it('emits navigate-to-thread with the thread id when a post content cell is clicked', () => {
        mountWithEmitSpy(ForumStatsTable, 'navigate-to-thread', { users }, 'navigateHandler');
        cy.get('[data-testid="expand-button"]').eq(0).click();
        cy.get('[data-testid="post-content-cell"]').eq(0).click();
        cy.get('@navigateHandler').should('have.been.calledOnceWith', { threadId: 101 });
    });

    it('sorts rows ascending when a sortable header is clicked', () => {
        cy.mount(ForumStatsTable, { props: { users } });
        // Default order is prop order (Instructor first).
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', 'Instructor, Quinn');
        cy.get('[data-testid="sortable-header-link"]').contains('Total Upducks').click();
        // Ascending: Student (1) before Instructor (6).
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', 'Student, Joe');
    });

    it('toggles sort direction when the same header is clicked again', () => {
        cy.mount(ForumStatsTable, { props: { users } });
        cy.get('[data-testid="sortable-header-link"]').contains('Total Upducks').click();
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', 'Student, Joe');
        cy.get('[data-testid="sortable-header-link"]').contains('Total Upducks').click();
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', 'Instructor, Quinn');
    });

    it('collapses expanded rows when a sortable header is clicked', () => {
        cy.mount(ForumStatsTable, { props: { users } });
        cy.get('[data-testid="expand-button"]').eq(0).click();
        cy.get('[data-testid="post-detail-row"]').should('have.length', 2);
        cy.get('[data-testid="sortable-header-link"]').contains('Total Upducks').click();
        cy.get('[data-testid="post-detail-row"]').should('not.exist');
    });

    it('marks the active sort header', () => {
        cy.mount(ForumStatsTable, { props: { users } });
        cy.get('[data-testid="sortable-header-link"]').contains('Total Upducks').click();
        cy.get('[data-testid="sortable-header-link"]').contains('Total Upducks').should('have.class', 'active-sort');
        cy.get('[data-testid="sortable-header-link"]').contains('Total Upducks').find('i').should('have.class', 'fa-sort-up');
    });

    it('has accessible markup: expand button title and sortable header aria-label', () => {
        cy.mount(ForumStatsTable, { props: { users } });
        cy.get('[data-testid="expand-button"]').eq(0).should('have.attr', 'title').and('not.be.empty');
        cy.get('[data-testid="sortable-header-link"]').eq(0).should('have.attr', 'aria-label', 'Sort by User');
    });

    it('supports keyboard activation of the expand button', () => {
        cy.mount(ForumStatsTable, { props: { users } });
        cy.get('[data-testid="expand-button"]').eq(0).focus();
        cy.get('[data-testid="expand-button"]').eq(0).type('{enter}');
        cy.get('[data-testid="post-detail-row"]').should('have.length', 2);
    });

    it('sorts by user name and reverses direction on a second click', () => {
        cy.mount(ForumStatsTable, { props: { users } });
        cy.get('[data-testid="sortable-header-link"]').contains('User').click();
        // ASC: Instructor before Student.
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', 'Instructor, Quinn');
        cy.get('[data-testid="sortable-header-link"]').contains('User').click();
        // DESC: Student before Instructor.
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', 'Student, Joe');
    });

    it('sorts by total posts ascending', () => {
        cy.mount(ForumStatsTable, { props: { users } });
        cy.get('[data-testid="sortable-header-link"]').contains('Total Posts (not deleted)').click();
        // Student has 0 posts, Instructor has 2.
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', 'Student, Joe');
        cy.get('[data-testid="user-stat"]').eq(1).should('contain', 'Instructor, Quinn');
    });

    it('sorts by total threads ascending', () => {
        cy.mount(ForumStatsTable, { props: { users } });
        cy.get('[data-testid="sortable-header-link"]').contains('Total Threads').click();
        // Student has 0 threads, Instructor has 1.
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', 'Student, Joe');
        cy.get('[data-testid="user-stat"]').eq(1).should('contain', 'Instructor, Quinn');
    });

    it('sorts by total deleted posts ascending', () => {
        cy.mount(ForumStatsTable, { props: { users } });
        cy.get('[data-testid="sortable-header-link"]').contains('Total Deleted Posts').click();
        // Instructor has 0 deleted, Student has 1.
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', 'Instructor, Quinn');
        cy.get('[data-testid="user-stat"]').eq(1).should('contain', 'Student, Joe');
    });

    it('returns to ascending direction on a third click of the same header', () => {
        cy.mount(ForumStatsTable, { props: { users } });
        cy.get('[data-testid="sortable-header-link"]').contains('Total Upducks').click();
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', 'Student, Joe'); // ASC: 1 before 6.
        cy.get('[data-testid="sortable-header-link"]').contains('Total Upducks').click();
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', 'Instructor, Quinn'); // DESC: 6 before 1.
        cy.get('[data-testid="sortable-header-link"]').contains('Total Upducks').click();
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', 'Student, Joe'); // ASC again.
    });
});
