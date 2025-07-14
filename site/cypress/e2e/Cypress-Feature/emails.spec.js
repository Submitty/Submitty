const normalizeText = (text) => {
    return text.trim().replace(/\s+/g, ' ');
};

const extractUserCount = (text) => {
    const match = text.match(/\((\d+)\s+users\)/);
    return match ? parseInt(match[1], 10) : 0;
};

describe('Test case involving the superuser email all functionality', () => {
    it('sends an email via Email All and verifies Email Status page', () => {
        cy.login('superuser');

        cy.get('[data-testid="sidebar"]')
            .contains('Email All')
            .click();

        cy.url().should('include', '/superuser/email');
        cy.get('[data-testid="system-wide-email-title"]').should('contain', 'System Wide Email');

        // Store user counts based on their access group to determine the total number of recipients
        const recipientCounts = {
            instructor: 0,
            fullAccess: 0,
            limitedAccess: 0,
            student: 0,
            faculty: 0,
        };

        // Verify the default recipients are checked and extract their user count
        cy.get('#email-faculty')
            .should('be.checked')
            .parent()
            .invoke('text')
            .then((text) => {
                const normalized = normalizeText(text);
                recipientCounts.faculty = extractUserCount(normalized);
                expect(normalized).to.match(/^Email all Faculty and all Superusers \(\d+ users\)$/);
            });

        cy.get('#email-instructor')
            .should('be.checked')
            .parent()
            .invoke('text')
            .then((text) => {
                const normalized = normalizeText(text);
                recipientCounts.instructor = extractUserCount(normalized);
                expect(normalized).to.match(/^Email all Instructors of Active\/Non-Archived Courses \(\d+ users\)$/);
            });

        cy.get('#email-full-access')
            .should('be.checked')
            .parent()
            .invoke('text')
            .then((text) => {
                const normalized = normalizeText(text);
                recipientCounts.fullAccess = extractUserCount(normalized);
                expect(normalized).to.match(/^Email all Full Access Graders of Active\/Non-Archived Courses \(\d+ users\)$/);
            });

        cy.get('#email-limited-access')
            .should('be.checked')
            .parent()
            .invoke('text')
            .then((text) => {
                const normalized = normalizeText(text);
                recipientCounts.limitedAccess = extractUserCount(normalized);
                expect(normalized).to.match(/^Email all Limited Access Graders of Active\/Non-Archived Courses \(\d+ users\)$/);
            });

        cy.get('#email-student')
            .should('be.checked')
            .parent()
            .invoke('text')
            .then((text) => {
                const normalized = normalizeText(text);
                recipientCounts.student = extractUserCount(normalized);
                expect(normalized).to.match(/^Email all Students in Active\/Non-Archived Courses \(\d+ users\)$/);
            });

        // Verify the optional recipients are unchecked
        cy.get('#email-to-secondary')
            .should('not.be.checked')
            .parent()
            .invoke('text')
            .then((text) => {
                const normalized = normalizeText(text);
                expect(normalized).to.match(/^Send email to each user's primary and secondary email addresses, regardless of the user's notification selection$/);
            });

        // Wait for the total recipients count to be fully parsed
        cy.then(() => {
            // Instructors should not be included in the total recipients as they're included in the faculty bucket
            const totalRecipients = recipientCounts.faculty + recipientCounts.fullAccess + recipientCounts.limitedAccess + recipientCounts.student;

            // Verify the email subject and content fields are present
            cy.get('[data-testid="email-subject"]').should('be.visible');
            cy.get('[data-testid="email-content"]').should('be.visible');

            const uniqueSubject = `Test Email - ${Date.now()}`;
            cy.get('[data-testid="email-subject"]').type(uniqueSubject);
            cy.get('[data-testid="email-content"]').type('This is a test email body.');
            cy.get('[data-testid="send-email"]').click();

            cy.get('[data-testid="sidebar"]')
                .contains('Email Status')
                .click();

            // Verify the email subject and content fields are present
            cy.contains('.button-container', uniqueSubject)
                .should('contain', `(${totalRecipients})`)
                .within(() => {
                    cy.get('button.status-btn').contains(/Show Details|Hide Details/).click();
                });

            // Verify each recipient is properly rendered
            cy.get('#collapse1')
                .should('exist')
                .within(() => {
                    cy.get('li.status').should('have.length', totalRecipients).each(($li) => {
                        cy.wrap($li)
                            .find('.status-message')
                            .should('have.length', 3)
                            .then(($msgs) => {
                                const texts = [...$msgs].map((el) => el.textContent.trim());
                                expect(texts[0]).to.match(/^Recipient: .+/);
                                expect(texts[1]).to.match(/^Time Sent: Not Sent$/);
                                expect(texts[2]).to.match(/^Email Address: .+@.+\..+$/);
                            });
                    });
                });
        });
    });
});
