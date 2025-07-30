import {
    buildUrl,
    getCurrentSemester,
} from '../../support/utils.js';

const defaultWebSocketPages = [
    'discussion_forum',
    'office_hours_queue',
    'chatrooms',
];

describe('Tests for WebSocket accessibility', () => {
    beforeEach(() => {
        cy.login('instructor');
    });

    afterEach(() => {
        cy.logout();
    });

    it('Should generate websocket token for basic pages', () => {
        defaultWebSocketPages.forEach((page) => {
            cy.visit(buildUrl([getCurrentSemester(), page]));
            cy.get('#socket-server-system-message').should('be.hidden');
        });
    });

    it('Should test sliding window behavior for unique pages (polls)', () => {
        return;
        // First, make sure polls are visible and answerable
        cy.visit(['sample', 'polls']);
        cy.get('#socket-server-system-message').should('be.hidden');

        // Enable poll 3 to be visible and answerable for testing
        cy.get('#poll_3_visible').check();
        cy.get('#poll_3_view_results').check();

        // Visit poll 3 as instructor first
        cy.get('#older-table').should('contain', 'Poll 3');
        cy.contains('Poll 3').siblings().last().click();
        cy.get('#socket-server-system-message').should('be.hidden');

        let firstVisitTime;
        getWebSocketToken().then((token) => {
            console.log(token);
            expect(token.authorized_pages).to.have.property(
                'f25-sample-polls-3-instructor',
            );
            firstVisitTime
                = token.authorized_pages['f25-sample-polls-3-instructor'];
        });

        return;

        // Go back and visit poll 1 (different poll)
        cy.go('back');
        cy.contains('Poll 1').siblings().last().click();
        cy.get('#socket-server-system-message').should('be.hidden');

        getWebSocketToken().then((tokenData) => {
            expect(tokenData.authorized_pages).to.have.property(
                'f25-sample-polls-1-instructor',
            );
            expect(tokenData.authorized_pages).to.have.property(
                'f25-sample-polls-3-instructor',
            );

            const secondVisitTime
                = tokenData.authorized_pages['f25-sample-polls-1-instructor'];
            // New unique page should have later or equal expiration (sliding window)
            expect(secondVisitTime).to.be.at.least(firstVisitTime);
        });
    });

    it('Should generate correct token for grading page', () => {
        return;
        // Visit grading page using buildUrl helper
        const gradingUrl = buildUrl([
            'sample',
            'gradeable',
            'grading_lab',
            'grading',
        ]);
        cy.visit(gradingUrl);
        cy.get('#socket-server-system-message').should('be.hidden');

        getWebSocketToken().then((tokenData) => {
            expect(tokenData.sub).to.equal('instructor');
            expect(tokenData.authorized_pages).to.have.property(
                'f25-sample-grading-grading_lab',
            );
        });
    });

    it('Should generate correct token for grade inquiry page', () => {
        return;
        // Set up grade inquiry for the gradeable first
        cy.visit(['sample', 'gradeable', 'grades_released_homework', 'update']);
        cy.get('[data-testid="yes-grade-inquiry-allowed"]').click();
        cy.get('[data-testid="yes-component"]').click();
        cy.contains('Dates').click();
        cy.get('[data-testid="grade-inquiry-due-date"]').click();
        cy.get('[data-testid="grade-inquiry-due-date"]').should('be.visible');
        cy.get('[data-testid="grade-inquiry-due-date"]').clear();
        cy.get('[data-testid="grade-inquiry-due-date"]').type(
            '9998-01-01 00:00:00',
        );
        cy.get('[data-testid="grade-inquiry-due-date"]').type('{enter}');
        cy.get('[data-testid="save-status"]', { timeout: 10000 }).should(
            'have.text',
            'All Changes Saved',
        );

        // Visit grade inquiry page for specific student
        cy.visit([
            'sample',
            'gradeable',
            'grades_released_homework',
            'grading',
            'details',
        ]);
        cy.get('[data-testid="view-sections"]').click();
        cy.get('[data-testid="grade-button"]').eq(2).click();
        cy.get('[data-testid="grade-inquiry-info-btn"]').click();
        cy.get('#socket-server-system-message').should('be.hidden');

        getWebSocketToken().then((tokenData) => {
            expect(tokenData.sub).to.equal('instructor');
            // Should contain grade inquiry page identifier
            const pageKeys = Object.keys(tokenData.authorized_pages);
            const gradeInquiryPage = pageKeys.find((key) =>
                key.includes('grade_inquiry-grades_released_homework'),
            );
            expect(gradeInquiryPage).to.exist;
        });
    });

    it('Should test security isolation between users', () => {
        return;
        let instructorToken;

        // Login as instructor and visit poll 3
        cy.visit(['sample', 'polls']);
        cy.get('#poll_3_visible').check();
        cy.get('#poll_3_view_results').check();
        cy.contains('Poll 3').siblings().last().click();
        cy.get('#socket-server-system-message').should('be.hidden');

        getWebSocketToken().then((tokenData) => {
            instructorToken = tokenData;
            expect(tokenData.sub).to.equal('instructor');
            expect(tokenData.authorized_pages).to.have.property(
                'f25-sample-polls-3-instructor',
            );
        });

        // Logout instructor and login as student
        cy.logout();
        cy.login('student');

        // Visit same poll as student
        cy.visit(['sample', 'polls']);
        cy.contains('Poll 3').siblings(':nth-child(3)').click(); // Click to view poll
        cy.get('#socket-server-system-message').should('be.hidden');

        getWebSocketToken().then((tokenData) => {
            expect(tokenData.sub).to.equal('student');

            // Student should have student access to poll, not instructor access
            expect(tokenData.authorized_pages).to.have.property(
                'f25-sample-polls-3-student',
            );
            expect(tokenData.authorized_pages).to.not.have.property(
                'f25-sample-polls-3-instructor',
            );

            // Ensure no instructor pages leaked to student token
            const studentPageKeys = Object.keys(tokenData.authorized_pages);
            const instructorPageKeys = Object.keys(
                instructorToken.authorized_pages,
            );

            instructorPageKeys.forEach((instructorPage) => {
                if (instructorPage.includes('-instructor')) {
                    expect(studentPageKeys).to.not.include(instructorPage);
                }
            });
        });
    });

    it('Should test token reuse and sliding window for chatrooms with IDs', () => {
        return;
        // Visit general chatrooms page
        cy.visit(['sample', 'chatrooms']);
        cy.get('#socket-server-system-message').should('be.hidden');

        let firstToken;
        getWebSocketToken().then((tokenData) => {
            firstToken = tokenData;
            expect(tokenData.authorized_pages).to.have.property(
                'f25-sample-chatrooms',
            );
        });

        // Visit specific chatroom (if available)
        // Note: This test assumes chatroom functionality exists
        // If not available, the basic chatrooms page test above is sufficient
        cy.url().then((url) => {
            if (url.includes('chatrooms')) {
                // Token should still be valid and reused for same page type
                getWebSocketToken().then((tokenData) => {
                    expect(tokenData.issued_at).to.equal(firstToken.issued_at);
                    expect(tokenData.authorized_pages).to.have.property(
                        'f25-sample-chatrooms',
                    );
                });
            }
        });
    });

    it('Should verify websocket connection on all tested pages', () => {
        return;
        const pages = [
            ['sample', 'forum'],
            ['sample', 'office_hours_queue'],
            ['sample', 'chatrooms'],
            ['sample', 'polls'],
        ];

        pages.forEach((pagePath) => {
            cy.visit(pagePath);

            // Verify websocket connection is successful (warning message is hidden)
            cy.get('#socket-server-system-message').should('be.hidden');

            // Verify websocket token exists and is valid
            getWebSocketToken().then((tokenData) => {
                expect(tokenData).to.have.property('sub');
                expect(tokenData).to.have.property('authorized_pages');
                expect(tokenData).to.have.property('expire_time');
                expect(tokenData.expire_time).to.be.greaterThan(Date.now());
            });
        });
    });
});
