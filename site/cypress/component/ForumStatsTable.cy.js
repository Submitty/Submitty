import ForumStatsTable from '@/components/forum/ForumStatsTable.vue';
import { mountWithEmitSpy } from '../support/component_test_utils';

const defaultProps = {
    users: [
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
    ],
};

const instructor = defaultProps.users[0];
const student = defaultProps.users[1];

describe('ForumStatsTable', () => {
    it('renders a row per user with name and stat values in default order', () => {
        cy.mount(ForumStatsTable, { props: defaultProps });
        cy.get('[data-testid="user-stat"]').should('have.length', 2);
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', `${instructor.familyName}, ${instructor.givenName}`);
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', String(instructor.postCount));
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', String(instructor.totalThreads));
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', String(instructor.numDeleted));
        cy.get('[data-testid="user-stat"]').eq(0).find('[data-testid="upduck-stat"]').should('contain', String(instructor.totalUpducks));
    });

    it('shows "No Posts" for users without posts', () => {
        cy.mount(ForumStatsTable, { props: defaultProps });
        cy.get('[data-testid="user-stat"]').eq(1).should('contain', 'No Posts');
    });

    it('expands and collapses post detail rows on button click', () => {
        cy.mount(ForumStatsTable, { props: defaultProps });
        cy.get('[data-testid="post-detail-row"]').should('not.exist');
        cy.get('[data-testid="expand-button"]').eq(0).click();
        cy.get('[data-testid="post-detail-row"]').should('have.length', instructor.posts.length);
        cy.get('[data-testid="post-detail-row"]').eq(0).should('contain', instructor.posts[0].threadTitle);
        cy.get('[data-testid="post-detail-row"]').eq(0).should('contain', instructor.posts[0].content);
        cy.get('[data-testid="post-detail-row"]').eq(0).should('contain', instructor.posts[0].timestamp);
        cy.get('[data-testid="expand-button"]').eq(0).click();
        cy.get('[data-testid="post-detail-row"]').should('not.exist');
    });

    it('emits navigate-to-thread when a post cell is clicked', () => {
        mountWithEmitSpy(ForumStatsTable, 'navigate-to-thread', defaultProps, 'navigateHandler');
        cy.get('[data-testid="expand-button"]').eq(0).click();
        cy.get('[data-testid="post-thread-cell"]').eq(1).click();
        cy.get('@navigateHandler').should('have.been.calledOnceWith', { threadId: instructor.posts[1].threadId });
        cy.get('[data-testid="post-content-cell"]').eq(0).click();
        cy.get('@navigateHandler').should('have.been.calledTwice');
    });

    it('sorts ascending on first click, toggles direction on second', () => {
        cy.mount(ForumStatsTable, { props: defaultProps });
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', `${instructor.familyName}, ${instructor.givenName}`);
        cy.get('[data-testid="sortable-header-link"]').contains('Total Upducks').click();
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', `${student.familyName}, ${student.givenName}`);
        cy.get('[data-testid="sortable-header-link"]').contains('Total Upducks').click();
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', `${instructor.familyName}, ${instructor.givenName}`);
    });

    it('collapses expanded rows when a sortable header is clicked', () => {
        cy.mount(ForumStatsTable, { props: defaultProps });
        cy.get('[data-testid="expand-button"]').eq(0).click();
        cy.get('[data-testid="post-detail-row"]').should('have.length', instructor.posts.length);
        cy.get('[data-testid="sortable-header-link"]').contains('Total Upducks').click();
        cy.get('[data-testid="post-detail-row"]').should('not.exist');
    });

    it('highlights the active sort header with correct icon direction', () => {
        cy.mount(ForumStatsTable, { props: defaultProps });
        cy.get('[data-testid="sortable-header-link"]').contains('Total Upducks').click();
        cy.get('[data-testid="sortable-header-link"]').contains('Total Upducks').should('have.class', 'active-sort');
        cy.get('[data-testid="sortable-header-link"]').contains('Total Upducks').find('i').should('have.class', 'fa-sort-up');
        cy.get('[data-testid="sortable-header-link"]').contains('Total Upducks').click();
        cy.get('[data-testid="sortable-header-link"]').contains('Total Upducks').find('i').should('have.class', 'fa-sort-down');
    });

    it('sorts correctly by all 5 columns', () => {
        cy.mount(ForumStatsTable, { props: defaultProps });
        cy.get('[data-testid="sortable-header-link"]').contains('User').click();
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', `${instructor.familyName}, ${instructor.givenName}`);
        cy.get('[data-testid="sortable-header-link"]').contains('Total Posts (not deleted)').click();
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', `${student.familyName}, ${student.givenName}`);
        cy.get('[data-testid="sortable-header-link"]').contains('Total Threads').click();
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', `${student.familyName}, ${student.givenName}`);
        cy.get('[data-testid="sortable-header-link"]').contains('Total Deleted Posts').click();
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', `${instructor.familyName}, ${instructor.givenName}`);
        cy.get('[data-testid="sortable-header-link"]').contains('Total Upducks').click();
        cy.get('[data-testid="user-stat"]').eq(0).should('contain', `${student.familyName}, ${student.givenName}`);
    });

    it('has accessible markup on expand button and sortable headers', () => {
        cy.mount(ForumStatsTable, { props: defaultProps });
        cy.get('[data-testid="expand-button"]').eq(0).should('have.attr', 'title').and('not.be.empty');
        cy.get('[data-testid="sortable-header-link"]').eq(0).should('have.attr', 'aria-label', 'Sort by User');
    });
});
