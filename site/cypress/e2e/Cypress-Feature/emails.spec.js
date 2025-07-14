import { getApiKey } from '../../support/utils.js';

const normalizeText = (text) => {
    return text.trim().replace(/\s+/g, ' ');
};

const extractUserCount = (text) => {
    const match = text.match(/\((\d+)\s+users\)/);
    return match ? parseInt(match[1], 10) : 0;
};

describe('Test cases involving the superuser email all functionality', () => {
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

            let uniqueSubject = `Test Email - ${Date.now()}`;
            cy.get('[data-testid="email-subject"]').type(uniqueSubject);
            cy.get('[data-testid="email-content"]').type('This is a test email body.');
            cy.get('[data-testid="send-email"]').click();

            cy.get('[data-testid="sidebar"]')
                .contains('Email Status')
                .click();

            // Verify the email subject and content fields are present with the proper subject prefix
            uniqueSubject = `[Submitty Admin Announcement]: ${uniqueSubject}`;
            cy.contains('.button-container', uniqueSubject)
                .should('contain', `(${totalRecipients})`)
                .within(() => {
                    cy.get('button.status-btn.btn-primary') // btn-primary implies awaiting to be sent
                        .contains(/Show Details|Hide Details/)
                        .click();
                });

            // Verify each recipient is properly rendered
            cy.get('#collapse1')
                .should('exist')
                .within(() => {
                    cy.get('li.status.status-warning').should('have.length', totalRecipients).each(($li) => {
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

    it('sends an email via Email All and verifies emails that are queued, failed to send, and have been sent', () => {
        cy.login('superuser');

        cy.get('[data-testid="sidebar"]')
            .contains('Email All')
            .click();

        let uniqueSubject = `Test Email - ${Date.now()}`;
        cy.get('[data-testid="email-subject"]').type(uniqueSubject);
        cy.get('[data-testid="email-content"]').type('This is a test email body.');
        cy.get('[data-testid="send-email"]').click();

        cy.get('[data-testid="sidebar"]')
            .contains('Email Status')
            .click();

        // Mock an email error
        uniqueSubject = `[Submitty Admin Announcement]: ${uniqueSubject}`;
        cy.contains('.button-container', uniqueSubject)
            .closest('.status-container')
            .then(() => {
                getApiKey('superuser', 'superuser').then((apiKey) => {
                    // Add the testing error message to the email
                    cy.request({
                        method: 'PUT',
                        url: `${Cypress.config('baseUrl')}/api/superuser/email/error`,
                        headers: {
                            'Authorization': apiKey,
                            'Content-Type': 'application/json',
                        },
                        body: {
                            subject: uniqueSubject,
                            message: 'This is a test error message',
                        },
                    }).then((res) => {
                        // Verify the server response is successful
                        expect(res.status).to.eq(200);
                        expect(res.body).to.have.property('status', 'success');
                        expect(res.body).to.have.property('data', null);

                        // Verify the email error is displayed after a full page reload
                        cy.reload().then(() => {
                            cy.contains('.button-container', uniqueSubject)
                                .within(() => {
                                    cy.get('button.status-btn.btn-danger') // btn-danger implies email error
                                        .contains(/Show Details|Hide Details/)
                                        .click();
                                });

                            cy.get('#collapse1')
                                .should('exist')
                                .within(() => {
                                    cy.get('li.status.status-error')
                                        .each(($li) => {
                                            cy.wrap($li)
                                                .find('.status-message')
                                                .should('have.length', 4)
                                                .then(($msgs) => {
                                                    const texts = [...$msgs].map((el) => el.textContent.trim());
                                                    expect(texts[0]).to.match(/^Recipient: .+/);
                                                    // Email may be sent by the system before setting an existing error
                                                    expect(texts[1]).to.match(/^Time Sent: (Not Sent|\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})$/);
                                                    expect(texts[2]).to.match(/^Email Address: .+@.+\..+$/);
                                                    expect(texts[3]).to.equal('Error: This is a test error message');
                                                });
                                        });
                                });
                        });
                    });
                });
            }).as('email-error');

        // Mock an email sent
        cy.get('@email-error').then(() => {
            getApiKey('superuser', 'superuser').then((apiKey) => {
                cy.request({
                    method: 'PUT',
                    url: `${Cypress.config('baseUrl')}/api/superuser/email/sent`,
                    headers: {
                        'Authorization': apiKey,
                        'Content-Type': 'application/json',
                    },
                    body: {
                        subject: uniqueSubject,
                    },
                }).then((res) => {
                    // Verify the server response is successful
                    expect(res.status).to.eq(200);
                    expect(res.body).to.have.property('status', 'success');
                    expect(res.body).to.have.property('data', null);

                    // Verify the email sent is displayed after a full page reload
                    cy.reload().then(() => {
                        cy.contains('.button-container', uniqueSubject)
                            .within(() => {
                                cy.get('button.status-btn.btn-success') // btn-success implies email sent
                                    .contains(/Show Details|Hide Details/)
                                    .click();
                            });

                        cy.get('#collapse1')
                            .should('exist')
                            .within(() => {
                                cy.get('li.status.status-success')
                                    .each(($li) => {
                                        cy.wrap($li)
                                            .find('.status-message')
                                            .should('have.length', 4)
                                            .then(($msgs) => {
                                                const texts = [...$msgs].map((el) => el.textContent.trim());
                                                expect(texts[0]).to.match(/^Recipient: .+/);
                                                // Email sent time should be set
                                                expect(texts[1]).to.match(/^Time Sent: \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/);
                                                expect(texts[2]).to.match(/^Email Address: .+@.+\..+$/);
                                                // Error should be propagated from the email error mock
                                                expect(texts[3]).to.equal('Error: This is a test error message');
                                            });
                                    });
                            });
                    });
                });
            });
        });
    });
});

describe('Test cases involving instructor send email via thread announcement functionality', () => {
    it('sends an email via thread announcement and verifies the email is queued or sent', () => {
        cy.login('instructor');
        cy.visit(['sample', 'forum', 'threads', 'new']);

        const announcement = 'This is a test announcement';
        const content = 'This is a test content';

        // Enter the thread announcement details
        cy.get('[data-testid="title"]').type(announcement);
        cy.get('[data-testid="reply_box_1"]').type(content);

        // Pick an arbitrary category
        cy.get('[data-testid="categories-pick-list"] .cat-buttons').first().click({ force: true });

        // Ensure the announcement checkbox is checked
        cy.get('#Announcement').click();

        // Pin thread should automatically be checked based on the announcement checkbox selection
        cy.get('#pinThread').should('be.checked');

        // Submit the thread announcement
        cy.get('[data-testid="forum-publish-thread"]').click({ force: true });

        // Expect a redirection
        cy.url().should('not.include', '/sample/forum/threads/new').then(() => {
            cy.visit(['sample', 'email_status']).then(() => {
                // Verify the email was sent or is queued without any prefix
                const uniqueSubject = `New Announcement: ${announcement}`;
                cy.contains('.button-container', uniqueSubject)
                    .within(() => {
                        cy.get('button.status-btn')
                            .should('exist')
                            .and(($btn) => {
                                const classList = $btn[0].classList;
                                const isQueued = classList.contains('btn-primary');
                                const hasBeenSent = classList.contains('btn-success');
                                const hasError = classList.contains('btn-danger');
                                expect((isQueued || hasBeenSent) && !hasError).to.be.true;

                                return $btn;
                            })
                            .contains(/Show Details|Hide Details/)
                            .click();
                    });

                cy.get('#collapse1')
                    .should('exist')
                    .within(() => {
                        cy.get('li.status')
                            .each(($li) => {
                                cy.wrap($li)
                                    .find('.status-message')
                                    .should('have.length', 3)
                                    .then(($msgs) => {
                                        const texts = [...$msgs].map((el) => el.textContent.trim());
                                        expect(texts[0]).to.match(/^Recipient: .+/);
                                        expect(texts[1]).to.match(/^Time Sent: (Not Sent|\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})$/);
                                        expect(texts[2]).to.match(/^Email Address: .+@.+\..+$/);
                                    });
                            });
                    });
            });
        });
    });
});
